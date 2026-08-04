# Plan implementacji: Pierwsza rozmowa gościa

## Przegląd

Dostarczamy pionowy wycinek S-01: gość na `/` konfiguruje URL API LM Studio (OpenAI-compatible), parametry i system prompt, prowadzi rozmowy w UI wzorowanym na ChatGPT, widzi strumieniowaną odpowiedź z osobnym reasoningiem i statsami; backend składa historię bez reasoningu i proxy’uje stream; trwałość wyłącznie w `localStorage` z TTL 1 dzień — bez rekordów Conversation/Prompt na serwerze.

## Analiza stanu obecnego

- Aplikacja to scaffold Jetstream (Inertia + Vue): auth, Dashboard, Profile. Brak domeny czatu/LLM.
- `/` przekierowuje na `dashboard` chroniony `auth:sanctum` — gość nie ma produktu.
- `AppLayout` zakłada `auth.user` — nie nadaje się na shell gościa.
- Brak `localStorage`, streamingu, CSRF meta w `app.blade.php`, paczek OpenAI/SSE.
- Deploy: FrankenPHP + Caddy z globalnym `encode zstd br gzip` (`Dockerfile`) — ryzyko buforowania SSE.
- S-02 (trwałość konta) i S-03 (vision) są poza tą zmianą.

## Pożądany stan końcowy

Gość otwiera aplikację, wpisuje base URL API, wybiera model z listy (lub wpisuje id przy awarii pickera), ustawia temperature / max_tokens / top_p i system prompt, tworzy/przełącza lokalne rozmowy, wysyła wiadomość i widzi napływające tokeny; reasoning (gdy jest) osobno; stats (tokeny, t/s, TTFT) pod odpowiedzią. Po odświeżeniu rozmowy wracają z localStorage (≤1 dzień). Zalogowany widzi w nawigacji Profil (Jetstream). Serwer nigdy nie zapisuje rozmów gościa; reasoning nigdy nie wraca do historii modelu.

### Kluczowe odkrycia:

- Tech-stack wymaga server-composed proxy — nie wołaj LM Studio z przeglądarki (`context/foundation/tech-stack.md`).
- Passthrough OpenAI SSE przez `response()->stream()`; **nie** `eventStream()` / `@laravel/stream-vue` (przepakowuje format).
- `Http::withOptions(['stream' => true])` + `toPsrResponse()->getBody()`; nigdy `->body()` na streamie (buforuje całość).
- Domyślny timeout Http (30s) jest za krótki — ustawić długi timeout (np. 300s) na chat completions.
- CSRF: dodać meta w `resources/views/app.blade.php`; `fetch` z `credentials: 'same-origin'` + `X-XSRF-TOKEN` / `X-CSRF-TOKEN`.
- Testy: `Http::fake` + `assertStreamed` / `assertStreamedContent` (`tests/Feature/` wzorzec Jetstream).

## Czego NIE robimy

- Modele/migracje Conversation / Prompts ani zapis DB dla gościa lub zalogowanego (S-02).
- Pole API key w UI; hosty inne niż LM Studio / OpenAI-compatible bez auth.
- Render markdown odpowiedzi; wejście obrazów (S-03).
- `@laravel/stream-vue`, WebSocket/Reverb, Pinia/Vuex.
- Allowlista hostów URL (świadomie: solo self-host; dowolny URL użytkownika).
- E2E Playwright w tej zmianie.
- Zmiana Fortify `home` na `/` (zalogowany może nadal lądować na `/dashboard` po loginie; produktowy home to `/`).

## Podejście do implementacji

Cienkie proxy Laravel na trasach `web` (bez `auth`): walidacja payloadu → kompozycja wiadomości bez reasoningu → upstream stream → passthrough SSE. Osobny endpoint listy modeli. Frontend: Inertia `Chat/Index` + `ChatLayout`, stan gościa w composable localStorage, stream przez `fetch` + `ReadableStream` (nie Inertia visit). UI: lewy panel + wątek + composer.

## Krytyczne szczegóły implementacji

- **Czas i cykl życia:** przed rozpoczęciem odczytu upstream zwolnij session lock (`session()->save()` / nie trzymaj blokady przez cały stream), inaczej równoległe requesty tej samej sesji się zablokują. Sprawdzaj `connection_aborted()` i zamykaj upstream.
- **Ograniczenia wydajności / deploy:** w Caddy wyłącz `encode` dla ścieżki streamu (lub typu `text/event-stream`); ustaw `X-Accel-Buffering: no`, `Cache-Control: no-cache, no-transform`. Http client: `timeout` ≫ 30s.
- **Sekwencjonowanie stanu (klient):** TTFT = czas do pierwszego delta content/reasoning; po błędzie mid-stream zachowaj częściową treść asystenta w localStorage i pokaż błąd przy wiadomości.
- **Debugowanie:** Feature testy nie mierzą timing chunków — tylko kompletność SSE body; prawdziwy stream weryfikować ręcznie vs LM Studio.

## Faza 1: Routing i shell czatu

### Przegląd

Publiczny home produktu z layoutem ChatGPT-like i nawigacją gość vs zalogowany; bez jeszcze działającego proxy.

### Wymagane zmiany:

#### 1. Trasy web

**Plik**: `routes/web.php`

**Cel**: `/` renderuje czat dla wszystkich; `dashboard` zostaje za auth dla zalogowanych.

**Kontrakt**: `GET /` → `Inertia::render('Chat/Index')`, named `home` (lub `chat.index`). Usunąć `to_route('dashboard')` z `/`. Grupa auth bez zmian dla `dashboard`.

#### 2. Layout i strona czatu

**Pliki**: `resources/js/Layouts/ChatLayout.vue`, `resources/js/Pages/Chat/Index.vue`, komponenty szkieletu pod `resources/js/Components/Chat/` (`ChatSidebar`, `ConversationList`, `MessageThread`, `ChatComposer`, `ReasoningBlock`, `ResponseStats` — mogą być placeholdery UI).

**Cel**: Pełny układ lewy panel + główny wątek + composer; chrome bezpieczny dla `auth.user === null`.

**Kontrakt**: Nawigacja: gość → Login (opcjonalnie Register); zalogowany → `route('profile.show')` (+ opcjonalnie Dashboard), Logout. Logo → `route('home')`. Nie używać `AppLayout` jako root czatu.

#### 3. CSRF meta

**Plik**: `resources/views/app.blade.php`

**Cel**: Umożliwić późniejszy `fetch` POST ze streamem.

**Kontrakt**: `<meta name="csrf-token" content="{{ csrf_token() }}">` w `<head>`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `php artisan route:list --path=/` pokazuje publiczny home bez middleware auth
- Istniejące testy Feature auth nadal przechodzą: `php artisan test --compact tests/Feature/AuthenticationTest.php`
- Nowy/zaktualizowany Feature test: gość dostaje 200 na `GET /` z Inertia component `Chat/Index`

#### Weryfikacja ręczna:

- Gość widzi shell czatu na `/` bez logowania
- Zalogowany widzi link do Profilu; przejście do Profile działa
- Layout: lewy panel + obszar wiadomości + composer czytelne na desktopie

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu weryfikacji automatycznej, zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Faza 2: Trwałość gościa (localStorage)

### Przegląd

Wielowątkowe rozmowy gościa w przeglądarce z TTL 1 dzień; historia do modelu bez reasoningu po stronie klienta (serwer i tak wymusi strip w Fazie 3).

### Wymagane zmiany:

#### 1. Composable store

**Plik**: `resources/js/composables/useGuestChatStore.js` (lub `.ts` jeśli projekt używa TS — tu JS jak reszta Jetstream)

**Cel**: Load/save/purge stanu gościa; API do CRUD rozmów i wiadomości.

**Kontrakt**: Klucz `llms.guest.v1`. Shape: `{ version: 1, settings: { apiBaseUrl, defaultParams }, conversations: [...], activeConversationId }`. Per conversation: `id`, `title`, `createdAt`, `updatedAt`, `systemPrompt`, `params` (`temperature`, `max_tokens`, `top_p`), `model`, `messages[]` z `content`, opcjonalnie `reasoning`, `stats`, `createdAt`. TTL: usuń rozmowy gdzie `Date.now() - updatedAt > 86_400_000` przy load i przed write. Helper `toModelMessages(conversation)` → `{ role, content }[]` bez reasoning/stats; system prompt jako osobna wiadomość `system` na początku (lub pole przekazywane osobno do proxy — spójnie z Fazą 3).

#### 2. Podłączenie UI do store

**Pliki**: `Pages/Chat/Index.vue`, komponenty sidebara / listy / wątku

**Cel**: Tworzenie, przełączanie, usuwanie rozmów; edycja URL/params/system prompt; dopisywanie wiadomości użytkownika lokalnie (odpowiedź asystenta pełna w Fazie 4).

**Kontrakt**: Zmiany ustawień i wiadomości aktualizują `updatedAt`. Brak jakichkolwiek requestów Persistence do serwera.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Jeśli dodano testy jednostkowe JS — nie są wymagane w tym repo (brak runnera JS); opcjonalnie pomiń. Preferuj test PHP dla stripu historii w Fazie 3/5.
- Lint frontu nie jest obowiązkowy poza `npm run build` smoke w Fazie 5; tu: brak błędów SSR resolve dla nowych Pages (plik w globie `Pages/**/*.vue`)

#### Weryfikacja ręczna:

- Odświeżenie strony zachowuje rozmowy i aktywną selekcję
- Rozmowa starsza niż 1 dzień (zasymuluj `updatedAt`) znika po reload
- Przełączanie między dwiema rozmowami nie miesza wiadomości

**Uwaga implementacyjna**: Zatrzymaj się na ręczne potwierdzenie przed Fazą 3.

---

## Faza 3: Proxy LLM (models + stream)

### Przegląd

Backend przyjmuje base URL i payload, składa historię bez reasoningu, listuje modele i streamuje chat completions z upstreamu do klienta.

### Wymagane zmiany:

#### 1. Kompozycja historii

**Plik**: `app/Services/Llm/ChatHistoryComposer.php` (lub `app/Services/ChatHistoryComposer.php`)

**Cel**: Jedno miejsce gwarantujące, że reasoning nie trafia do modelu.

**Kontrakt**: Wejście: system prompt + lista wiadomości (mogą zawierać zbędne pola). Wyjście: tablica OpenAI-compatible `{ role, content }` — tylko `system` / `user` / `assistant`; bez pól reasoning; puste content pomijane lub odrzucane walidacją.

#### 2. Form requests + kontrolery

**Pliki**: Form requesty pod `app/Http/Requests/`, kontrolery np. `app/Http/Controllers/Chat/ChatStreamController.php`, `ChatModelsController.php`

**Cel**: Walidacja i orkiestracja proxy.

**Kontrakt**:
- `POST` stream (np. `chat.stream`): `api_base_url` (url), `model` (string), `messages` (array), `system_prompt` (string, nullable), `temperature` / `max_tokens` / `top_p` (numeric, nullable w rozsądnych zakresach). Brak API key. Po walidacji: compose → `Http` POST `{base}/v1/chat/completions` z `stream: true`, bez `Authorization` (ew. empirycznie stały Bearer tylko jeśli LM Studio tego wymaga — bez UI). Odpowiedź: `response()->stream` passthrough, `Content-Type: text/event-stream`.
- `GET` lub `POST` models (np. `chat.models`): `api_base_url` → proxy `GET {base}/v1/models`, JSON response (nie stream).
- Obie trasy: middleware `web`, **bez** `auth`; throttle (np. `throttle:60,1` lub dedykowany limiter).
- Przy user null: zero zapisów Conversation/Prompt (nie ma jeszcze modeli — assert w testach że nie ma insertów, gdy pojawią się w S-02; na razie brak tabel = OK).

#### 3. Trasy

**Plik**: `routes/web.php`

**Cel**: Zarejestrować endpointy proxy.

**Kontrakt**: Named routes `chat.stream`, `chat.models`.

#### 4. Timeout / abort

**Plik**: serwis proxy (np. `app/Services/Llm/ChatCompletionProxy.php`)

**Cel**: Długie generacje i czyste przerwanie.

**Kontrakt**: `timeout` ≥ 300s, `connectTimeout` sensowny (np. 10s); `withOptions(['stream' => true])`; na abort klienta zamknij upstream.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Feature: `Http::fake` chat completions → `post(route('chat.stream'))` → `assertOk()`, `assertStreamed()`, content zawiera oczekiwany SSE
- Feature: models endpoint z `Http::fake` → JSON listy modeli
- Unit/Feature: composer usuwa reasoning z wiadomości asystenta przed „wysyłką” (assert na payload przechwycony przez `Http::fake` / `Http::assertSent`)
- Feature: gość może wywołać stream **bez** logowania (200, nie 302 login)
- `vendor/bin/pint --dirty --format agent` na zmienionych PHP

#### Weryfikacja ręczna:

- Against real LM Studio (lub ngrok): stream napływa w curl/fetch; models zwraca listę
- Przerwanie po stronie klienta nie zostawia wiszącego requestu w nieskończoność (obserwacja)

**Uwaga implementacyjna**: Zatrzymaj się na ręczne potwierdzenie przed Fazą 4.

---

## Faza 4: Podłączenie UI (stream, picker, stats, błędy)

### Przegląd

Vue woła proxy, renderuje tokeny na żywo, reasoning/stats, model picker; zapisuje wynik (w tym partial przy błędzie) w localStorage.

### Wymagane zmiany:

#### 1. Composable stream

**Plik**: `resources/js/composables/useChatStream.js`

**Cel**: `fetch` POST na `chat.stream` z CSRF; parse linii SSE OpenAI; callbacki delta content / reasoning / usage / finish / error.

**Kontrakt**: Nie używać Inertia `router.post`. Parsować `data: {json}` i `data: [DONE]`. Liczyć TTFT przy pierwszym tokenie treści lub reasoningu. Mapować usage/stats gdy upstream je dostarcza (w tym w final chunk); tokens/s z `output_tokens / elapsed` jeśli brak gotowego pola.

#### 2. Model picker

**Pliki**: sidebar + wywołanie `chat.models`

**Cel**: Po ustawieniu / blur URL pobierz modele i wypełnij select; przy błędzie pozwól wpisać `model` ręcznie (degradacja z decyzji #12).

**Kontrakt**: Wybrane `model` zapisane w aktywnej rozmowie / settings.

#### 3. Integracja wątku

**Pliki**: `Chat/Index.vue`, `MessageThread`, `ReasoningBlock`, `ResponseStats`, `ChatComposer`

**Cel**: Pełny happy path + partial on error.

**Kontrakt**: Reasoning w collapsible, nie w bubble treści. Stats pod odpowiedzią asystenta. Mid-stream error: zachowaj partial `content`/`reasoning`, pokaż komunikat błędu, zapisz do localStorage.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Feature testy Fazy 3 nadal przechodzą
- `npm run build` (lub `build:ssr` zgodnie z lokalnym workflow) kończy się sukcesem po zmianach Vue

#### Weryfikacja ręczna:

- Wysyłka wiadomości: tokeny pojawiają się na bieżąco
- Reasoning (jeśli model go zwraca) osobno; kolejna wiadomość nie wysyła reasoningu w historii (Network: payload do `/chat/stream`)
- Stats widoczne po zakończeniu (choćby TTFT + przybliżone t/s)
- Błąd upstream mid-stream: partial zostaje, błąd widoczny
- Picker modeli działa; fallback ręcznego id działa przy martwym URL

**Uwaga implementacyjna**: Zatrzymaj się na ręczne potwierdzenie przed Fazą 5.

---

## Faza 5: Testy domykające i hardening deploy

### Przegląd

Uzupełnienie pokrycia testów, throttle, hardening Caddy/timeout pod produkcję.

### Wymagane zmiany:

#### 1. Testy Feature/Unit uzupełniające

**Pliki**: `tests/Feature/Chat/*`, `tests/Unit/*` wg konwencji `php artisan make:test --phpunit`

**Cel**: Guardraile PRD w CI.

**Kontrakt**: Co najmniej: gość GET `/`; stream bez auth; composer strip reasoning (assertSent); models proxy; walidacja odrzuca zły URL / brak model; (opcjonalnie) throttle 429. Żaden test nie wymaga żywego LM Studio.

#### 2. Caddy / stream buffering

**Plik**: `Dockerfile` (Caddyfile inline)

**Cel**: SSE nie buforowane przez `encode`.

**Kontrakt**: Dla ścieżki streamu (np. `handle /chat/stream*`) wyłączyć kompresję / nie stosować globalnego encode; zachować root + php_server. Dokumentuj w PR jeśli Coolify ma własny idle timeout.

#### 3. Throttle i konfiguracja timeoutu

**Pliki**: `routes/web.php`, ewentualnie `config/llms.php` lub `config/services.php`

**Cel**: Limit nadużyć + konfigurowalny timeout upstream.

**Kontrakt**: Timeout chat z config/env (default 300). Throttle na `chat.stream` i `chat.models`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `php artisan test --compact --filter=Chat` (lub ścieżki testów Chat) przechodzi
- `vendor/bin/pint --dirty --format agent`
- `npm run build` przechodzi

#### Weryfikacja ręczna:

- Smoke na produkcji/staging (jeśli dostępne): długi stream nie „czeka na koniec” zanim pokaże tekst
- Gość nadal nie tworzy żadnych wierszy rozmów w DB (schema bez tych tabel — potwierdź brak nowych migracji Conversation)

---

## Strategia testowania

### Testy jednostkowe:

- `ChatHistoryComposer`: usuwa reasoning, zachowuje kolejność, dokłada system prompt, ignoruje obce pola

### Testy integracyjne (Feature):

- Publiczny home Inertia
- Stream proxy z `Http::fake` SSE body + `assertStreamed`
- Models proxy JSON
- `Http::assertSent` — payload bez reasoning
- Guest bez auth na proxy
- Walidacja 422 na niepoprawnym inputcie

### Kroki testowania ręcznego:

1. Podłącz LM Studio (lokalnie lub ngrok), wpisz base URL, wybierz model, wyślij wiadomość — obserwuj stream
2. Sprawdź reasoning + stats; wyślij follow-up — upewnij się, że historia w requestcie nie zawiera reasoningu
3. Utwórz 2 rozmowy, przeładuj stronę, potwierdź localStorage
4. Symuluj TTL (zmień `updatedAt`) i reload
5. Zaloguj się — Profil z layoutu czatu; wróć na `/`
6. Zabij upstream w trakcie streamu — partial + błąd

## Uwagi dotyczące wydajności

- Nie buforować całego upstreamu w pamięci PHP.
- Zwolnić session lock na czas streamu.
- Frontend: append do jednego reactive message zamiast przebudowy całej historii na każdy token.

## Uwagi dotyczące migracji

- Brak migracji DB w S-01.
- Klucz localStorage wersjonowany (`v1`); przy niezgodnej wersji — reset store.

## Referencje

- Roadmap S-01: `context/foundation/roadmap.md`
- PRD: `context/foundation/prd.md` (US-01, FR-001–007, FR-009, NFR stream, guardraile)
- Tech-stack: `context/foundation/tech-stack.md`
- Laravel streamed responses: docs `responses.md` (stream vs eventStream)
- Istniejący shell: `routes/web.php`, `resources/js/Layouts/AppLayout.vue`, `resources/views/app.blade.php`

## Progress

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dodaj ` — <commit sha>` po zatwierdzeniu kroku. Nie zmieniaj nazw tytułów kroków. Zobacz `references/progress-format.md`.

### Faza 1: Routing i shell czatu

#### Automatyczne

- [x] 1.1 `route:list` — publiczny home bez auth — 46bf57e
- [x] 1.2 Feature auth nadal przechodzi (`AuthenticationTest`) — 46bf57e
- [x] 1.3 Feature — gość `GET /` → Inertia `Chat/Index` — 46bf57e

#### Ręczne

- [x] 1.4 Gość widzi shell czatu bez logowania — 46bf57e
- [x] 1.5 Zalogowany: link Profil działa — 46bf57e
- [x] 1.6 Layout panel + wątek + composer czytelny na desktopie — 46bf57e

### Faza 2: Trwałość gościa (localStorage)

#### Automatyczne

- [x] 2.1 Nowe Pages w globie Inertia (smoke: strona resolvuje się w teście Fazy 1 / build później) — db450c9

#### Ręczne

- [x] 2.2 Reload zachowuje rozmowy i aktywną selekcję — db450c9
- [x] 2.3 TTL 1 dzień usuwa starą rozmowę po reload — db450c9
- [x] 2.4 Przełączanie rozmów nie miesza wiadomości — db450c9

### Faza 3: Proxy LLM (models + stream)

#### Automatyczne

- [x] 3.1 Feature stream + `Http::fake` + `assertStreamed`
- [x] 3.2 Feature models + `Http::fake`
- [x] 3.3 Assert payload bez reasoning (`Http::assertSent`)
- [x] 3.4 Gość wywołuje stream bez auth
- [x] 3.5 Pint na dirty PHP

#### Ręczne

- [ ] 3.6 Live LM Studio: stream + models
- [ ] 3.7 Abort klienta zamyka upstream sensownie

### Faza 4: Podłączenie UI (stream, picker, stats, błędy)

#### Automatyczne

- [ ] 4.1 Testy Fazy 3 nadal przechodzą
- [ ] 4.2 `npm run build` sukces

#### Ręczne

- [ ] 4.3 Tokeny napływają na żywo
- [ ] 4.4 Reasoning osobno; follow-up bez reasoningu w historii
- [ ] 4.5 Stats widoczne po odpowiedzi
- [ ] 4.6 Partial + błąd przy mid-stream failure
- [ ] 4.7 Model picker + fallback ręcznego id

### Faza 5: Testy domykające i hardening deploy

#### Automatyczne

- [ ] 5.1 `php artisan test --compact` (filtr Chat) przechodzi
- [ ] 5.2 Pint dirty
- [ ] 5.3 `npm run build` sukces

#### Ręczne

- [ ] 5.4 Smoke długiego streamu bez buforowania końca
- [ ] 5.5 Brak migracji/tabel Conversation w tej zmianie
