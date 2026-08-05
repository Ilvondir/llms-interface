# Plan implementacji: Wejście obrazu (model wizyjny)

## Przegląd

Dostarczamy S-03 / FR-010: zalogowany użytkownik może załączyć jedno zdjęcie do wiadomości (plik, paste, drag-and-drop), zobaczyć podgląd w wątku i wysłać multimodalną historię przez istniejący publiczny proxy `chat.stream` do OpenAI-compatible `/chat/completions`. Detekcja „czy model jest wizyjny” nie jest osobnym probe — attach jest zawsze dostępny dla authed; błędy upstream mapujemy na czytelny komunikat. Gość **nie** dostaje UI ani ścieżki obrazów (świadomy override PRD Access Control). Reasoning nadal nie wraca do historii modelu.

## Analiza stanu obecnego

- S-01/S-02 done: guest localStorage + account `conversations`/`prompts`; wspólny UI w `Chat/Index.vue` z branch `auth.user`.
- Cały stos jest **string-only**: `ChatComposer` emituje string → `historyForModel` filtruje `typeof content === 'string'` → `ChatStreamRequest` wymaga `messages.*.content` string → `ChatHistoryComposer` / `composeModelMessages` rzutują na string → upstream dostaje tylko `{role, content: string}`.
- `prompts.content` to `text`; `StorePromptRequest::MAX_TEXT_CHARS = 100_000`; `MAX_JSON_BYTES = 100_000` na `stats` / `request_payload`. Assistant `requestPayload` dziś zawiera pełne `messages` z `buildUpstreamRequest` — base64 obrazu wyleciałby dwa razy i odbił się o limity.
- Brak attach UI, brak `attachments`, brak bibliotek compress w `package.json` (tylko Jetstream profile `FileReader`).
- Roadmap S-03 był zablokowany na detekcji modelu wizyjnego — decyzja planowania: **brak detekcji**, zawsze attach + mapowanie błędów.

## Pożądany stan końcowy

Zalogowany wybiera/wkleja/upuści jeden obraz (po client-side resize/compress), opcjonalnie dopisuje tekst, wysyła turę. Historia do modelu zawiera OpenAI parts (`text` + `image_url` data-URL). Wątek pokazuje podgląd obrazu. Po reloadzie obraz nadal widać (parts w `prompts.content`). Gość: composer bez attach; próba multimodal na stream/prompt odrzucona lub niemożliwa z UI. Model tekstowy → czytelny błąd; wiadomość użytkownika z obrazem zostaje w wątku. Reasoning strip bez zmian dla części tekstowych asystenta.

### Kluczowe odkrycia:

- Hard gate: `ChatStreamRequest` L26 (`messages.*.content` => string) oraz `historyForModel` filter na string — bez obu change nie dojdzie do modelu.
- `ChatHistoryComposer` (`app/Services/Llm/ChatHistoryComposer.php`) i `resources/js/utils/buildUpstreamRequest.js` muszą mirrorować ten sam kontrakt parts.
- Trwałość 4MB-class base64 wymaga podniesienia `MAX_TEXT_CHARS` (i limitu stream body); **`request_payload` musi być sanitizowany** (bez data-URL obrazów), inaczej `MAX_JSON_BYTES` i Inertia/JSON reload zabiją feature.
- Auth gate: `Index.vue` L34 `isAuthenticated` — wystarczy prop do `ChatComposer`; stream pozostaje publiczny, więc walidacja parts na backendzie + brak UI dla gościa.
- Compress: natywny canvas/`createImageBitmap` — **bez** nowej zależności npm.

## Czego NIE robimy

- Detekcja vision przez `/v1/models`, heurystykę nazwy ani osobny toggle „Vision”.
- Obrazy dla gościa (localStorage / IndexedDB) — świadomy override PRD.
- Osobna kolumna/tabela `attachments` lub upload na dysk/S3 — wybór: data-URL w `content` jako JSON parts.
- Więcej niż 1 obraz na wiadomość; URL zewnętrzny zamiast pliku; kamera natywna.
- Playwright E2E; RAG; zmiana auth na `chat.stream` / `chat.models`.
- Przesyłanie obrazów w odpowiedziach asystenta (tylko input użytkownika).

## Podejście do implementacji

1. Wspólny kontrakt `content: string | ContentPart[]` w walidacji stream + composerach PHP/JS + limity rozmiaru.
2. Persistencja konta: `content` trzyma legacy string **lub** JSON-encoded parts; presenter/UI rozumieją oba; `request_payload` bez bajtów obrazu; podniesione limity tekstu.
3. UI auth-only: attach (plik/paste/DnD), compress, preview w composerze i wątku, `historyForModel` + store title z pierwszej części tekstowej.
4. Mapowanie błędów upstream + zestaw testów Feature/Unit/JS; guest isolation bez regresji.

## Krytyczne szczegóły implementacji

- **`request_payload` sanitization:** przed `POST/PATCH` promptów asystenta (i przy budowie obiektu do zapisu) usuń z `messages[].content` części `image_url` (zastąp placeholderem typu `[image omitted]` lub zostaw tylko `text` parts). Stream do modelu nadal wysyła pełne data-URL. Bez tego limit 100KB JSON i rozmiar propsów Inertia są nie do utrzymania.
- **Normalizacja `content` w DB:** kolumna `text` zostaje; multimodal zapisujemy jako JSON string tablicy parts. Odczyt (presenter + front): jeśli `json_decode` daje niepustą listę parts z `type`, traktuj jako parts; w przeciwnym razie legacy string. Nie zmieniaj typu kolumny na `json`, żeby nie psuć istniejących wierszy stringowych bez migracji danych.
- **Pustość wiadomości:** dozwolone: sam obraz bez tekstu; niedozwolone: puste parts i pusty tekst. Composer/historia: „empty” = brak niepustego text **i** brak image_url.
- **Kolejność UI→persist:** user prompt z parts zapisujemy **przed** streamem (jak dziś string); compress musi zakończyć się przed `appendMessage`.

## Faza 1: Kontrakt multimodal (stream + composers)

### Przegląd

Odblokować ścieżkę proxy: walidacja i composers akceptują OpenAI parts; limity chronią workerów; mirror JS↔PHP.

### Wymagane zmiany:

#### 1. Walidacja stream

**Plik**: `app/Http/Requests/Chat/ChatStreamRequest.php`

**Cel**: Przyjąć legacy string lub tablicę parts; odrzucić złe typy / nadmiar obrazów / zbyt duże body.

**Kontrakt**:
- `messages.*.content` — custom rule / `after` validation: `string` **lub** non-empty array of parts.
- Part `type=text`: wymagany `text` string (może być `''` jeśli inna część niesie obraz).
- Part `type=image_url`: wymagany `image_url.url` string zaczynający się od `data:image/(jpeg|png|gif|webp);base64,` (HTTPS URL poza zakresem MVP).
- Max **1** `image_url` w całej tablicy `messages` danego requestu **lub** max 1 per user message (wybór implementatora: per message = 1, spójne z UX).
- Twardy limit długości encoded content / całego JSON body (wystarczający na 1 skompresowany obraz ~do kilku MB; konkretna stała współdzielona z prompt requestami).
- Role bez zmian: `system|user|assistant`.

#### 2. Composer PHP

**Plik**: `app/Services/Llm/ChatHistoryComposer.php`

**Cel**: Przekazać parts do upstream bez `(string)` cast; nadal doklejać system prompt jako string; pomijać puste wiadomości; nie dodawać reasoning/stats.

**Kontrakt**: Return type `content: string|list<array<string,mixed>>`. Dla string — dotychczasowe trim. Dla parts — zachowaj tylko `text` / `image_url`; trim text parts; drop message jeśli po normalizacji brak treści. Assistant history: jeśli kiedyś parts (nie w MVP), text-only strip think tags dotyczy wyłącznie text parts — w MVP assistant pozostaje string.

#### 3. Composer JS mirror

**Plik**: `resources/js/utils/buildUpstreamRequest.js` (+ ewentualny mały helper `contentParts.js`)

**Cel**: Identyczna semantyka co PHP dla `composeModelMessages` / `buildUpstreamRequest` (używane przy `requestPayload` inspection).

**Kontrakt**: Te same reguły empty/parts; eksport helperów `normalizeMessageContent`, `isContentEmpty`, `contentPlainText` (pierwszy text / legacy string — pod tytuły i filtry).

#### 4. Limity współdzielone

**Plik**: stałe w FormRequestach (lub mała klasa/`config`) współdzielone między stream a prompt store

**Cel**: Jedno źródło prawdy dla max znaków multimodal `content` (podnieść powyżej 100k — rząd wielkości pozwalający na 1 obraz po client compress z buforem; np. kilka milionów znaków — wartość nazwana stałą, nie magic number w wielu plikach).

**Kontrakt**: `MAX_TEXT_CHARS` (lub `MAX_CONTENT_CHARS`) używane przez stream + Store/Update prompt; `MAX_JSON_BYTES` dla `request_payload` **bez** zmiany w górę (sanitizacja zamiast podnoszenia).

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Unit: `ChatHistoryComposerTest` — string legacy OK; parts z text+image_url przechodzą; reasoning nadal nie w output; puste parts odrzucone
- Feature: `ChatStreamProxyTest` / validation — multipart-shaped JSON messages accepted; invalid part type 422; >1 image 422
- JS: `tests/js/buildUpstreamRequest.test.js` — parts compose + legacy
- `vendor/bin/pint --dirty --format agent`

#### Weryfikacja ręczna:

- Ręczny curl/HTTP do `chat.stream` z małym 1×1 PNG data-URL (gdy lokalny model dostępny) albo weryfikacja że proxy forwarduje body bez zniekształcenia parts (mock jak w istniejących testach)

**Uwaga implementacyjna**: Po tej fazie zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Faza 2: Trwałość konta (parts w `content` + sanitizacja payload)

### Przegląd

Zalogowany zapisuje i odczytuje tury z obrazem; limity i `request_payload` nie wybuchają.

### Wymagane zmiany:

#### 1. Store / Update prompt validation

**Pliki**: `app/Http/Requests/Chat/StorePromptRequest.php`, `UpdatePromptRequest.php`

**Cel**: Akceptować `content` jako string (legacy lub JSON-encoded parts) **albo** jako array parts (encode przy zapisie); podnieść max content; odrzucić >1 image; gość i tak nie woła tych tras (auth).

**Kontrakt**: Po walidacji controller zapisuje w kolumnie `content` zawsze string (plain lub `json_encode(parts)`). `request_payload` nadal max 100KB — caller musi przysłać już zsanityzowany obiekt (test to egzekwuje).

#### 2. PromptController + presenter

**Pliki**: `app/Http/Controllers/Chat/PromptController.php`, `app/Support/Chat/AccountChatPresenter.php`

**Cel**: Round-trip parts do frontendu jako `content: string | ContentPart[]` (nie stringified JSON w propsach UI).

**Kontrakt**: Przy create/update: jeśli input array → encode do DB string. Przy present: decode parts → tablica w DTO; legacy string bez zmian. Title derivation po stronie frontu używa `contentPlainText`.

#### 3. Sanitizacja `request_payload` po stronie klienta (account store)

**Plik**: `resources/js/composables/useAccountChatStore.js` (oraz miejsce budowy `requestPayload` w `Chat/Index.vue`)

**Cel**: Zapisany `requestPayload` nie zawiera data-URL obrazów.

**Kontrakt**: Helper `sanitizeRequestPayloadForStorage(payload)` usuwa/redaguje `image_url` w `messages`; stosowany przed POST/PATCH assistant (i ewentualnie user, jeśli kiedyś niosłoby payload). Stream fetch nadal dostaje pełne parts.

#### 4. Account store title / append

**Plik**: `resources/js/composables/useAccountChatStore.js`

**Cel**: `appendMessage` i tytuł z pierwszej wiadomości działają gdy `content` jest tablicą parts.

**Kontrakt**: Title = `contentPlainText(content).trim().slice(0, 48)` lub fallback `"Image"` / istniejący default gdy brak tekstu.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Feature: `AccountPromptPersistenceTest` — store user prompt z parts (mały data-URL fixture); present zwraca array parts; reload shape OK
- Feature: zapis `request_payload` z pełnym obrazem >100KB → 422; zsanityzowany payload przechodzi
- Feature: oversized content powyżej nowej stałej → 422
- Guest isolation bez regresji (`AccountGuestIsolationTest`)
- `vendor/bin/pint --dirty --format agent`

#### Weryfikacja ręczna:

- Zalogowany: wyślij wiadomość z małym obrazem (po Fazie 3 UI) lub przez API — po F5 podgląd/parts nadal w wątku

**Uwaga implementacyjna**: Po tej fazie zatrzymaj się na ręczne potwierdzenie przed Fazą 3.

---

## Faza 3: UI auth-only (attach, compress, render, wiring)

### Przegląd

Widoczny FR-010 dla zalogowanego: załącz, podejrzyj, wyślij; gość bez zmian composera poza brakiem attach.

### Wymagane zmiany:

#### 1. ChatComposer

**Plik**: `resources/js/Components/Chat/ChatComposer.vue`

**Cel**: Auth-only attach: przycisk pliku, paste, drag-and-drop; preview; emit payload z tekstem + jednym obrazem.

**Kontrakt**:
- Prop `allowAttachments` (z `Index` = `isAuthenticated`).
- Emit `send` ze shape `{ text: string, imageDataUrl: string | null }` **lub** równoważny OpenAI parts — ustal jeden kontrakt i trzymaj w Index.
- Accept `image/jpeg|png|gif|webp`; odrzuć resztę toasts.
- Po wyborze: client compress (max edge ~2048, JPEG/PNG sensowny quality) → data-URL; jeśli nadal > limitu stałej — toast i nie wysyłaj.
- Clear draft image po udanym send; disabled/streaming jak dziś.

#### 2. Compress helper

**Plik**: nowy `resources/js/utils/imageAttach.js` (lub podobny)

**Cel**: File/Blob → skompresowany data-URL bez nowych zależności npm.

**Kontrakt**: `fileToCompressedDataUrl(file, { maxEdge, mime, quality, maxChars })` → Promise<dataUrl>; rzuca/reject przy nieobsługiwanym typie lub limicie.

#### 3. Index wiring + historyForModel

**Plik**: `resources/js/Pages/Chat/Index.vue`

**Cel**: Budować parts dla user message; historia do modelu przenosi parts; gość path string-only bez regresji.

**Kontrakt**:
- `sendMessage` przyjmuje nowy emit; jeśli `!isAuthenticated` i jest obraz — ignore/toast (defense in depth).
- `historyForModel`: nie filtruj wyłącznie `typeof === 'string'`; użyj `isContentEmpty` / normalizacji; assistant nadal `splitThinkTaggedContent` na **string** content.
- `buildUpstreamRequest` / stream: pełne parts.
- Przed persist assistant: `sanitizeRequestPayloadForStorage`.

#### 4. MessageThread render

**Plik**: `resources/js/Components/Chat/MessageThread.vue`

**Cel**: User bubble pokazuje `<img>` dla `image_url` parts + tekst; legacy string bez zmian.

**Kontrakt**: Nie wrzucaj raw data-URL do `MarkdownContent`. Assistant path nietknięty.

#### 5. Guest store defense

**Plik**: `resources/js/composables/useGuestChatStore.js` (minimalnie)

**Cel**: Jeśli kiedyś `content` array wpadnie, title/plain text nie wywali `.trim()` na tablicy — twardy string path dla gościa wystarczy; opcjonalnie odrzuć arrays w `appendMessage`.

**Kontrakt**: Brak UI attach; brak bump `GUEST_CHAT_VERSION` wymagany, jeśli gość nigdy nie zapisuje parts.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- JS unit dla `imageAttach` / `contentParts` helpers (compress z małym fixturem lub mock canvas jeśli środowisko testowe na to pozwala; w przeciwnym razie pure functions normalizacji + limity)
- Istniejące testy guest/account nie czerwone
- `npm` test script używany w CI dla JS (jak dziś w repo) przechodzi dla zmienionych plików

#### Weryfikacja ręczna:

- Zalogowany: attach plikiem, paste screenshot, DnD — preview → send → stream → obraz w historii po reload
- Gość: brak przycisku attach; paste obrazu nie dodaje attachment (opcjonalnie toast „zaloguj się”)
- Wiadomość tylko-obraz (bez tekstu) działa

**Uwaga implementacyjna**: Po tej fazie zatrzymaj się na ręczne potwierdzenie przed Fazą 4.

---

## Faza 4: Błędy vision + domknięcie testów

### Przegląd

Uczciwy feedback gdy model nie przyjmuje obrazów; regresje i edge cases domknięte w CI.

### Wymagane zmiany:

#### 1. Mapowanie błędów stream

**Pliki**: `resources/js/composables/useChatStream.js` i/lub `Chat/Index.vue` `onError`

**Cel**: Typowe odpowiedzi upstream (4xx/5xx body zawierające „image”, „vision”, „multimodal”, „unsupported”, itd.) → czytelny komunikat PL/EN spójny z resztą UI (istniejące stringi toastów).

**Kontrakt**: User message z obrazem **zostaje**; assistant message dostaje `error` + `toast.error` jak dziś. Brak auto-delete user turn. Heurystyka best-effort — fallback: istniejący generyczny komunikat.

#### 2. Uzupełnienie testów Feature

**Pliki**: `tests/Feature/Chat/*` (nowe lub rozszerzenia)

**Cel**: Pokryć happy-path stream parts (mock upstream), 422 validation, persist+present parts, sanitizacja payload, ownership bez zmian.

**Kontrakt**: Bez Playwright. Użyj małych data-URL fixtures (np. 1×1 PNG base64) zamiast 4MB w CI.

#### 3. Dokument status / roadmap note (opcjonalnie w Notes change.md)

**Plik**: `context/changes/vision-image-input/change.md` Notes

**Cel**: Zanotować override PRD (guest bez vision) i decyzję „brak detekcji modelu”.

**Kontrakt**: Krótka notatka; bez edycji foundation PRD chyba że user poprosi osobno.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `php artisan test --compact` z filtrem Feature/Chat + Unit Llm + JS tests dla zmienionych plików — green
- Pint clean

#### Weryfikacja ręczna:

- (Gdy dostępny model tekstowy) wyślij obraz → komunikat zrozumiały, nie generyczny crash
- (Gdy dostępny model wizyjny) obraz + pytanie → sensowna odpowiedź + stats jak S-01
- Guest isolation smoke: stream bez obrazu nadal OK; brak wierszy DB

**Uwaga implementacyjna**: Po domknięciu fazy zaktualizuj Progress; rozważ `/10x-archive` po akceptacji.

---

## Strategia testowania

### Testy jednostkowe:

- `ChatHistoryComposer` — legacy string, parts, empty, reasoning omit
- JS `composeModelMessages` / `sanitizeRequestPayloadForStorage` / `contentPlainText`
- Walidacja parts (rule) jeśli wydzielona

### Testy integracyjne / Feature:

- Stream proxy forwarduje parts do mock upstream
- Prompt store/present round-trip parts
- `request_payload` z obrazem odrzucony; zsanityzowany OK
- Guest nie tworzy DB rows (regresja)
- Ownership 403 bez zmian

### Kroki testowania ręcznego:

1. Auth: jpeg/png z dysku → preview → send → odpowiedź
2. Auth: paste screenshot → send
3. Auth: DnD → send; drugi obraz zastępuje pierwszy (limit 1)
4. Auth: tylko obraz, bez tekstu
5. Auth: za duży plik po compress → toast, brak send
6. Guest: brak attach; paste nie dodaje obrazu
7. Reload auth conversation z obrazem — preview nadal
8. Model bez vision — czytelny error; user turn zostaje

## Uwagi dotyczące wydajności

- Jeden skompresowany data-URL na turę; unikaj trzymania pełnego obrazu w `request_payload` i w podwójnych kopiach stanu.
- Inertia props rozmowy z wieloma obrazami w historii będą ciężkie — akceptowalne dla single-user MVP; poza zakresem: lazy load / osobne URL storage.
- Stream POST body ~MB — timeouti PHP/nginx muszą tolerować (Docker `post_max_size` już wysokie); nie podnoś limitu bez sensownego max w FormRequest.

## Uwagi dotyczące migracji

- Brak migracji schematu (kolumna `text` wystarcza).
- Wiersze legacy: `content` string — presenter bez zmian zachowania.
- Nowe wiersze multimodal: JSON string parts w `content`.
- Brak backfill.

## Referencje

- PRD FR-010 / Open Q3 (detekcja — rozstrzygnięte w planie jako „brak”)
- Roadmap S-03: `context/foundation/roadmap.md`
- Archiwum: `context/archive/2026-08-04-guest-first-chat/`, `context/archive/2026-08-05-account-conversation-persistence/`
- Kluczowe pliki: `ChatStreamRequest.php`, `ChatHistoryComposer.php`, `buildUpstreamRequest.js`, `StorePromptRequest.php`, `AccountChatPresenter.php`, `ChatComposer.vue`, `Chat/Index.vue`, `MessageThread.vue`, `useAccountChatStore.js`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kontrakt multimodal (stream + composers)

#### Automated

- [x] 1.1 ChatHistoryComposerTest covers legacy string and image_url parts; reasoning still omitted — 1515110
- [x] 1.2 ChatStreamProxy/validation accepts parts and 422s invalid or multi-image payloads — 1515110
- [x] 1.3 buildUpstreamRequest JS tests cover parts compose and legacy strings — 1515110
- [x] 1.4 Pint passes on dirty PHP — 1515110

#### Manual

- [x] 1.5 Proxy forwards multimodal body correctly (mock or local smoke) — 1515110

### Phase 2: Trwałość konta (parts w content + sanitizacja payload)

#### Automated

- [x] 2.1 AccountPromptPersistenceTest stores and presents content parts — 1515110
- [x] 2.2 Oversized request_payload with raw image 422s; sanitized payload stores — 1515110
- [x] 2.3 Oversized content above new constant 422s — 1515110
- [ ] 2.4 AccountGuestIsolationTest still green
- [x] 2.5 Pint passes on dirty PHP — 1515110

#### Manual

- [x] 2.6 Auth conversation with image survives full page reload with preview/parts intact — 1515110

### Phase 3: UI auth-only (attach, compress, render, wiring)

#### Automated

- [x] 3.1 JS helper tests for contentParts / sanitize / imageAttach limits — 1515110
- [x] 3.2 Existing guest/account and JS CI tests stay green — 1515110

#### Manual

- [x] 3.3 Auth: file attach, paste, and drag-and-drop each send one image end-to-end — 1515110
- [ ] 3.4 Guest: no attach control; image paste does not attach
- [x] 3.5 Image-only user message (no text) works — 1515110

### Phase 4: Błędy vision + domknięcie testów

#### Automated

- [x] 4.1 Focused Feature/Unit/JS chat tests green via php artisan test --compact (+ JS suite) — 1515110
- [x] 4.2 Pint clean on dirty PHP — 1515110

#### Manual

- [ ] 4.3 Non-vision model returns understandable error; user image turn remains
- [x] 4.4 Vision model (when available) answers with image context; stats still show — 1515110
- [ ] 4.5 Guest text chat smoke still OK with zero DB conversation rows
