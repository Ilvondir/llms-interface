# Plan implementacji: Trwałość rozmów na koncie

## Przegląd

Dostarczamy S-02 / FR-008: zalogowany użytkownik ma rozmowy i prompty w magazynie konta z pełną parzystością pól względem guest `localStorage` (`llms.guest.v1`), w tym parametrami użytego odpytania. Gość nadal używa tylko localStorage i nigdy nie tworzy rekordów serwerowych. Proxy LLM (`chat.stream`, `chat.models`) pozostaje publiczne; powierzchnia CRUD jest wyłącznie za `auth`.

## Analiza stanu obecnego

- S-01 zakończone: publiczny `/`, ChatLayout, stream SSE, model picker, guest store w `useGuestChatStore.js`.
- `Chat/Index.vue` **zawsze** woła `useGuestChatStore()` — `auth.user` nie przełącza persistencji (tylko nav w `ChatLayout.vue`).
- Brak tabel/modeli `conversations` / `prompts`; `GuestChatSchemaGuardTest` asertuje ich nieobecność.
- Brak `app/Policies`; FormRequesty czatu mają `authorize(): true`.
- Auth (Fortify/Jetstream/Sanctum) i flat `User` bez teams są gotowe; `DeleteUser` nie czyści domeny czatu (cascade FK wystarczy po migracjach).

## Pożądany stan końcowy

Po zalogowaniu lub rejestracji użytkownik pracuje wyłącznie na DB: lista rozmów, create/rename/delete, edycja URL/params/system prompt/model, wysyłka wiadomości ze streamem oraz zapis tur (user od razu; assistant + reasoning/stats/params/partial/error po finish) — wszystkie pola obecne dziś w guest store. Gość bez zmian na localStorage. Cudze rozmowy → 403. Guest wywołanie tras CRUD → 401/302. Reasoning nadal nie wraca do historii modelu (bez zmian w composerze).

### Kluczowe odkrycia:

- Shape gościa do zmirrorowania: `settings.apiBaseUrl` + `defaultParams`; per conversation: `title`, `systemPrompt`, `model`, `params`, timestamps; per message: `role`, `content`, `reasoning`, `stats`, `error`, `model`, `sentAt`, `receivedAt`, `requestPayload` (`useGuestChatStore.js`).
- Stream musi zostać `fetch` — Inertia nie streamuje SSE; Inertia obejmuje home + CRUD trwałości (decyzja 5A).
- Po loginie **bez** auto-importu guest→DB (decyzja 4B); światy rozdzielone.
- PK: bigint `$table->id()` jak reszta app; ownership = `user_id` (teams off).

### Addendum (impl review 2026-08-05)

- **Hybrid transport (amends 5A):** nawigacja (select / create / delete) zostaje na Inertia; mutacje pól rozmowy, ustawień i promptów idą przez `fetch` + JSON (`Accept: application/json`), bo pełne wizyty Inertia przy debounce psuły focus/remount podczas edycji system promptu. Kontrolery mają dual response (`wantsJson()` vs Inertia render).
- Field/settings JSON ack zwraca slim props (bez `messages`); pełny wątek wraca po mutacjach promptów.
- Debounced PATCH: epoch + flush przed nawigacją; toast przy błędzie zapisu; auth nie inicjuje guest `localStorage`.

## Czego NIE robimy

- Auto-migracja / merge localStorage → konto.
- Zmiana publicznego proxy LLM (auth na stream/models).
- Vision (S-03), RAG, teams, soft deletes, UUID jako PK.
- Osobny publiczny JSON REST API (poza dual-response na tych samych trasach Inertia/auth).
- E2E Playwright w tej zmianie.
- TTL 1 dzień na rozmowach konta (TTL dotyczy tylko guest store).

## Podejście do implementacji

1. Migracje + Eloquent (`UserChatSettings`, `Conversation`, `Prompt`) + `ConversationPolicy` + factories.
2. Trasy w grupie `auth:sanctum` + Jetstream session + `verified`: Inertia home z props dla authed; CRUD rozmów/promptów/ustawień przez FormRequests + policy.
3. Front: jeśli `auth.user` → composable konta napędzany props + `router` mutacjami; w przeciwnym razie istniejący guest store. Wspólny UI (sidebar/thread/composer) bez duplikacji layoutu.
4. Feature testy dual-path (8B); usunąć/zastąpić schema guard „brak tabel”.

## Krytyczne szczegóły implementacji

- **Sekwencjonowanie stanu:** podczas streamu aktualizuj treść asystenta lokalnie w pamięci UI (jak dziś `persist: false`); trwały zapis assistant promptu dopiero w `onFinish` / `onError` (partial + error). User prompt Inertia-POST przed startem streamu; trzymaj mapowanie tymczasowe client id → server id jeśli UI potrzebuje id przed reload props.
- **Czas i cykl życia:** mutacje Inertia (`router.post`/`put`/`patch`/`delete`) z `preserveScroll` / `only` props tam, gdzie reload listy/wątku nie powinien resetować composer/stream; nie blokuj UI streamu oczekiwaniem na pełny visit po każdym tokenie.
- **Inertia vs XHR:** FormRequests muszą działać dla XHR Inertia (422 JSON); policy przez `$this->user()->can(...)`.

## Faza 1: Schemat i modele

### Przegląd

Utrwalić w Postgres/SQLite pełny kontrakt guest store jako tabele Eloquent z ownership i policy — bez jeszcze podłączonego UI.

### Wymagane zmiany:

#### 1. Migracje

**Pliki**: nowe migracje w `database/migrations/`

**Cel**: Trwałe magazyny ustawień użytkownika, rozmów i promptów z kaskadą usuwania.

**Kontrakt**:
- `user_chat_settings`: `id`, `user_id` unique FK cascade, `api_base_url` string nullable/default '', `default_params` JSON (`temperature`, `max_tokens`, `top_p`), `active_conversation_id` nullable FK → conversations (nullOnDelete lub ustawiane w kodzie), timestamps.
- `conversations`: `id`, `user_id` FK cascade, `title`, `system_prompt` text, `model` string, `params` JSON, timestamps; index `(user_id, updated_at)`.
- `prompts`: `id`, `conversation_id` FK cascade, `role` string (`user`|`assistant`), `content` text, `reasoning` text nullable, `stats` JSON nullable, `error` text nullable, `model` string nullable, `params` JSON nullable (wypełnione dla tur asystenta = parametry użytego odpytania), `sent_at` / `received_at` nullable timestamps, `request_payload` JSON nullable, `position` unsigned integer (kolejność w wątku), timestamps; unique `(conversation_id, position)` lub index kolejności.
- Kolejność migracji: conversations przed FK `active_conversation_id` w settings (lub najpierw settings bez FK, potem FK w osobnej migracji).

#### 2. Modele, relacje, factories

**Pliki**: `app/Models/UserChatSettings.php`, `Conversation.php`, `Prompt.php`; aktualizacja `User.php`; `database/factories/*`

**Cel**: Eloquent API + fabryki pod testy.

**Kontrakt**: `User::chatSettings()`, `conversations()`; `Conversation::prompts()` ordered by `position`; casts JSON/datetime; mass assignment świadome. Factories: Conversation dla usera, Prompt z role/content; stany pomocnicze wg potrzeby.

#### 3. Policy + DeleteUser

**Pliki**: `app/Policies/ConversationPolicy.php` (auto-discover); ewentualnie Prompt przez parent conversation; `app/Actions/Jetstream/DeleteUser.php` bez zmian jeśli FK cascade pokrywa settings/conversations/prompts.

**Cel**: `view`/`update`/`delete` tylko właściciel; `create` dla authenticated.

**Kontrakt**: Policy methods używane z FormRequests / kontrolerów. Prompt mutations authorize via parent `Conversation`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `php artisan migrate --no-interaction` przechodzi (sqlite test + lokalnie)
- Modele/factories ładują się; Feature/Unit factory smoke: `Conversation::factory()->for($user)->create()`
- `vendor/bin/pint --dirty --format agent`

#### Weryfikacja ręczna:

- `php artisan db:table conversations` (lub schema dump) pokazuje oczekiwane kolumny
- Usunięcie usera w tinker/test usuwa jego conversations/prompts (cascade)

**Uwaga implementacyjna**: Po Fazie 1 zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Faza 2: Inertia CRUD i home dla zalogowanego

### Przegląd

Serwerowe trasy auth oddają props czatu i mutują DB; gość nadal dostaje pusty Inertia `Chat/Index` bez danych konta.

### Wymagane zmiany:

#### 1. Home / show rozmowy

**Pliki**: `routes/web.php`, kontroler np. `app/Http/Controllers/Chat/ChatPageController.php`

**Cel**: Zalogowany ładuje czat z DB; gość bez zmian (bez props konta).

**Kontrakt**:
- `GET /` (`home`): jeśli guest → `Inertia::render('Chat/Index')` jak dziś; jeśli auth → props: `chatSettings`, `conversations` (lista: id, title, updated_at, …), `activeConversation` (pełny wątek z `prompts` zmapowanymi do shape UI: camelCase zgodny z obecnym frontem albo mapper w Vue).
- Opcjonalnie `GET /conversations/{conversation}` named `conversations.show` — to samo z wybraną rozmową (Inertia visit przy select); aktualizuje `active_conversation_id` w settings.
- Eager-load prompts ordered; scope zawsze `user_id = auth.id`.
- Middleware group jak dashboard: `auth:sanctum`, Jetstream session, `verified` **tylko** na trasach CRUD/show konta — **nie** na publicznym `/` jeśli ma obsługiwać obu; branch wewnątrz kontrolera home jest OK. Alternatywa: home publiczny z branch; osobne auth routes dla mutacji.

#### 2. Mutacje Conversation + Settings

**Pliki**: kontrolery + FormRequests pod `app/Http/Controllers/Chat/`, `app/Http/Requests/Chat/`

**Cel**: create / rename / delete / update settings & conversation fields (api URL, model, params, system prompt).

**Kontrakt** (nazwy orientacyjne):
- `POST /conversations` → create (title default, skopiuj default params / model z settings)
- `PATCH /conversations/{conversation}` → title, system_prompt, model, params
- `DELETE /conversations/{conversation}`
- `PATCH /chat-settings` → api_base_url, default_params, active_conversation_id
- Wszystkie: `auth` middleware + policy/`authorize` na conversation; redirect/back lub Inertia reload `only: [...]`.
- Walidacja zakresów params spójna z `ChatStreamRequest` (temperature 0–2, top_p 0–1, max_tokens min 1 nullable).

#### 3. Mutacje Prompt (tury)

**Pliki**: np. `PromptController` lub nested under Conversation

**Cel**: Zapis tur wg decyzji 3B.

**Kontrakt**:
- `POST /conversations/{conversation}/prompts` — body: role, content, opcjonalnie reasoning/stats/error/model/params/sent_at/received_at/request_payload; serwer ustala `position` = max+1; dla `assistant` wymagane/oczekiwane `params` (snapshot z rozmowy w momencie odpytania — front przesyła).
- `PATCH /conversations/{conversation}/prompts/{prompt}` — update content/reasoning/stats/error/received_at/request_payload (partial stream finalize).
- Authorize via conversation ownership.
- Brak tras prompt dla guest (middleware auth).

#### 4. Schema guard

**Plik**: `tests/Feature/Chat/GuestChatSchemaGuardTest.php`

**Cel**: Zastąpić „tabele nie istnieją” testami „guest nie insertuje”.

**Kontrakt**: Usunąć asercje `hasTable === false`. Nowe/przeniesione testy w Fazie 4 sprawdzają zero wierszy po guest stream.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Feature: auth user `GET /` dostaje props z conversations (Inertia assert)
- Feature: guest `GET /` bez props konta / bez wycieku cudzych danych
- Feature: auth CRUD create/rename/delete conversation
- Feature: ownership — inny user → 403 na show/patch/delete
- Feature: guest `POST /conversations` → 401 lub redirect login (nie 200, nie insert)
- Pint dirty

#### Weryfikacja ręczna:

- Zalogowany: odświeżenie strony zachowuje rozmowy z DB
- Przełączenie rozmowy przez Inertia visit ładuje właściwy wątek
- Gość: brak nowych wierszy w `conversations` po korzystaniu z UI (smoke)

**Uwaga implementacyjna**: Zatrzymaj się na ręczne potwierdzenie przed Fazą 3.

---

## Faza 3: Front — branch auth vs guest

### Przegląd

Ten sam Chat UI; źródło prawdy zależy od `auth.user`. Parzystość pól: wszystko co guest trzyma lokalnie, auth czyta/pisze przez Inertia.

### Wymagane zmiany:

#### 1. Composable konta

**Plik**: np. `resources/js/composables/useAccountChatStore.js` (lub adapter nad props)

**Cel**: API powierzchniowo zbliżone do guest store (`createConversation`, `selectConversation`, setters, `appendMessage`, `updateMessage`), ale mutacje → `router` + lokalny optimistic state zsynchronizowany z props po reload.

**Kontrakt**: Nie czytać/zapisywać `llms.guest.v1` gdy user zalogowany. `selectConversation` → `router.get(route('conversations.show', id))` (lub home z query). Po append user: `router.post` prompts; po stream finish/error: `router.post` lub `patch` assistant prompt z pełnym shape (reasoning, stats, error, model, params, requestPayload, sentAt/receivedAt).

#### 2. Branch w Index

**Plik**: `resources/js/Pages/Chat/Index.vue` (+ ewentualnie thin wrapper store)

**Cel**: `const store = authUser ? accountStore : guestStore`.

**Kontrakt**: Props Inertia zdefiniowane dla auth (`chatSettings`, `conversations`, `activeConversation`); guest ignoruje brakujące. Stream/`useChatModels` bez zmian ścieżki URL proxy. Przy braku aktywnej rozmowy auth — utwórz przez Inertia POST (nie localStorage).

#### 3. Mapowanie shape

**Plik**: mapper util (JS) lub Resource PHP → camelCase UI

**Cel**: Vue komponenty (`ChatSidebar`, `MessageThread`, …) nie wymagają osobnych ścieżek danych.

**Kontrakt**: UI nadal widzi `systemPrompt`, `params.max_tokens`, `messages[].reasoning`, `stats`, `requestPayload` itd. Snake_case tylko na granicy HTTP jeśli wolisz — jedna warstwa mapowania, nie rozsiane `??`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `npm run build` sukces
- Istniejące Feature chat stream/models nadal przechodzą
- (Opcjonalnie) brak regresji AuthenticationTest

#### Weryfikacja ręczna:

- Gość: pełny flow S-01 bez zmian (localStorage, TTL, stream)
- Auth: nowa rozmowa, rename, delete, reload — dane z DB
- Auth: wyślij wiadomość — user prompt w DB przed/na starcie streamu; po finish assistant z reasoning/stats/params/requestPayload
- Auth: mid-stream error — partial + error zapisane
- Auth: follow-up — payload stream bez reasoning (Network)
- Po loginie UI nie pokazuje guest conversations jako kontowych (osobne światy)

**Uwaga implementacyjna**: Zatrzymaj się na ręczne potwierdzenie przed Fazą 4.

---

## Faza 4: Testy Feature dual-path

### Przegląd

Domknięcie guardrailów PRD w CI zgodnie z decyzją 8B.

### Wymagane zmiany:

#### 1. Suite Account persistence

**Pliki**: `tests/Feature/Chat/AccountConversation*` (phpunit), aktualizacja starych guardów

**Cel**: Pokryć happy path, ownership, guest isolation, zapis params/reasoning.

**Kontrakt** (minimum):
- Guest nie tworzy Conversation/Prompt przy `POST chat.stream` ani przy próbie CRUD
- Auth: create conversation + store user/assistant prompts z `params` i `reasoning`
- Auth: inny user → 403
- Guest CRUD routes → unauthenticated response
- Lista/show zwraca tylko własne rozmowy
- (Regresja) composer/stream strip reasoning nadal zielony

#### 2. Pint + filtr testów

**Cel**: Czysty styl i zielony filtr Chat/Account.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `php artisan test --compact --filter=Chat` (lub szerszy filtr obejmujący nowe testy) przechodzi
- `vendor/bin/pint --dirty --format agent`
- `npm run build` sukces

#### Weryfikacja ręczna:

- Smoke: register → puste konto DB → czat → logout → login → te same rozmowy
- Smoke: guest równolegle w oknie prywatnym nie zapisuje do DB

---

## Strategia testowania

### Testy jednostkowe:

- (Opcjonalnie) mapper position / default params — tylko jeśli logika nie trywialna
- Regresja `ChatHistoryComposer` bez zmian zachowania

### Testy integracyjne (Feature):

- Inertia props auth vs guest
- CRUD + policy 403
- Prompt persist z pełnym shape
- Guest isolation (zero rows)
- Istniejące proxy/throttle/validation

### Kroki testowania ręcznego:

1. Guest: rozmowa + reload localStorage
2. Register/login: potwierdź puste konto (brak importu guest)
3. Auth: multi-conv, params, system prompt, stream, stats, reasoning
4. Drugi user: brak dostępu do URL cudzej rozmowy
5. Usuń konto Jetstream — brak osieroconych conversations

## Uwagi dotyczące wydajności

- Lista rozmów: bez ładowania wszystkich prompts — tylko aktywna rozmowa z prompts; lista: id/title/updated_at.
- Eager-load `prompts` tylko dla active conversation.
- Unikać N+1 w Resource/mapowaniu.

## Uwagi dotyczące migracji

- Nowe tabele puste — brak backfill.
- `GuestChatSchemaGuardTest` musi być zaktualizowany w tej samej zmianie co migracje (inaczej CI czerwone).
- Wersja localStorage guest bez zmian (`v2` / klucz `llms.guest.v1`).

## Referencje

- Roadmap S-02: `context/foundation/roadmap.md`
- PRD: `context/foundation/prd.md` (FR-008, FR-009, Access Control, guardrails)
- Archiwum S-01: `context/archive/2026-08-04-guest-first-chat/plan.md`
- Guest shape: `resources/js/composables/useGuestChatStore.js`
- Proxy: `app/Http/Controllers/Chat/*`, `routes/web.php`
- Delete user: `app/Actions/Jetstream/DeleteUser.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Faza 1: Schemat i modele

#### Automated

- [x] 1.1 Migracje stosują się czysto (`migrate --no-interaction`) — 7413afc
- [x] 1.2 Factory smoke Conversation/Prompt dla User — 7413afc
- [x] 1.3 Pint dirty na PHP Fazy 1 — 7413afc

#### Manual

- [x] 1.4 Schema kolumn zgodna z kontraktem (conversations/prompts/settings) — 7413afc
- [x] 1.5 Cascade delete user → conversations/prompts — 7413afc

### Faza 2: Inertia CRUD i home dla zalogowanego

#### Automated

- [x] 2.1 Auth `GET /` Inertia props z conversations — d8d8847
- [x] 2.2 Guest `GET /` bez wycieku danych konta — d8d8847
- [x] 2.3 Auth CRUD create/rename/delete conversation — d8d8847
- [x] 2.4 Ownership 403 dla cudzej rozmowy — d8d8847
- [x] 2.5 Guest POST CRUD → unauthenticated (brak insertu) — d8d8847
- [x] 2.6 Pint dirty Fazy 2 — d8d8847

#### Manual

- [x] 2.7 Auth reload zachowuje rozmowy z DB — c40b961
- [x] 2.8 Inertia select rozmowy ładuje właściwy wątek — c40b961
- [x] 2.9 Guest smoke: brak wierszy DB po użyciu UI — c40b961

### Faza 3: Front — branch auth vs guest

#### Automated

- [x] 3.1 `npm run build` sukces — c40b961
- [x] 3.2 Regresja Feature stream/models — c40b961

#### Manual

- [x] 3.3 Guest flow S-01 bez regresji — c40b961
- [x] 3.4 Auth multi-conv CRUD + reload z DB — c40b961
- [x] 3.5 Auth zapis user+assistant (reasoning/stats/params/requestPayload) — c40b961
- [x] 3.6 Auth partial+error mid-stream zapisane — c40b961
- [x] 3.7 Follow-up bez reasoning w historii upstream — c40b961
- [x] 3.8 Po loginie brak auto-importu guest store — c40b961

### Faza 4: Testy Feature dual-path

#### Automated

- [x] 4.1 Filtr testów Chat/Account zielony
- [x] 4.2 Pint dirty
- [x] 4.3 `npm run build` sukces

#### Manual

- [ ] 4.4 Smoke register→chat→re-login persistence
- [ ] 4.5 Smoke guest w prywatnym oknie bez zapisów DB
