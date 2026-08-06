# Plan implementacji: MCP servers w interfejsie czatu

## Przegląd

Użytkownik konfiguruje zewnętrzne serwery MCP (HTTP) z panelu pod system promptem — Exa jako preset, dowolny URL + token. Backend przy wysyłce promptu listuje tools, dokleja je do OpenAI-compatible `tools[]`, przechwytuje `tool_calls`, wykonuje `tools/call` na MCP i wraca z wynikiem do modelu (max 5 rund). Status tooli widoczny w czacie; pełny JSON wyniku nie zaśmieca bubble. Secrets: konto = encrypted w DB; gość = token tylko w pamięci/requestcie (nie localStorage).

## Analiza stanu obecnego

- Czat to cienki SSE proxy: `ChatStreamController` składa `{model, messages, stream_options, …}` → `ChatCompletionProxy::streamChatCompletions` byte-forwarduje upstream SSE bez parsowania.
- Brak `tools` / `tool_calls` / roli `tool` w walidacji, composerze i kliencie (`useChatStream` czyta tylko content/reasoning).
- Ustawienia użytkownika: `user_chat_settings` (`api_base_url`, `default_params`, `active_conversation_id`). Rozmowa: `system_prompt`, `model`, `params`. Gość: `llms.guest.v1` version **2**.
- `laravel/mcp` jest transitive require-dev (Boost); ma `Client::web` / `tools()` / `callTool()` — nie jest w produkcyjnym `require`.
- Research: `context/changes/add-mcp-servers-to-interface/research.md`. Poza roadmapą PRD (świadomie).

## Pożądany stan końcowy

1. Panel **MCP servers** pod system promptem: dodaj/usuń/edytuj HTTP server (preset Exa), pole token; lista z user settings; na rozmowie checkboxy „użyj w tej rozmowie”.
2. Przy send: backend discovery → mapowanie z prefixem `{serverId}__{toolName}` → pętla tool ≤5 → stream finalnej odpowiedzi + eventy `tool_status`.
3. Soft fail discovery: warning w UI + zwykły czat bez tools.
4. Historia zapisuje tury tool (guest + account); UI pokazuje skrót/status, nie surowy JSON.
5. Testy PHPUnit/JS pokrywają mapper, orchestrator (fake MCP+LLM), settings, composer — bez Playwright.

### Kluczowe odkrycia:

- Byte-forward uniemożliwia tool loop — potrzebny orchestrator parsujący SSE po stronie serwera gdy włączone MCP (`ChatCompletionProxy.php` ~67–86).
- `ChatStreamRequest` role `system|user|assistant` blokuje `tool` — trzeba rozszerzyć.
- Gość dziś może bić publiczny `chat.stream`; tokeny MCP w body requestu (ephemeral), nie w localStorage (decyzja 1B).
- Prefix tooli (9A) unika kolizji między serwerami.

## Czego NIE robimy

- Transport stdio / spawn `npx` na serwerze aplikacji.
- OAuth MCP / Passport flow dla Exa (tylko opcjonalny bearer/API key w panelu).
- Laravel AI SDK / osobny agent framework poza własnym orchestratorem.
- Playwright E2E w tym change.
- Aktualizacja `roadmap.md` / PRD (change świadomie poza roadmapą; opcjonalnie później).
- Pokazywanie pełnych wyników tool w bubble jak w IDE.
- MCP resources/prompts (tylko tools).
- Wykrywanie „czy model wspiera tools” przez probe `/v1/models`.

## Podejście do implementacji

1. **Foundation** — `laravel/mcp` w `require`; schemat settings + conversation enable list; guest version bump.
2. **Toolkit** — serwis list/map/call z prefixem.
3. **Orchestrator** — pętla completions↔MCP + SSE `tool_status` + soft fail; bez MCP zachowaj dotychczasowy szybki byte-forward.
4. **Frontend** — panel + credentials ephemeral (guest) + `onToolStatus`.
5. **Historia** — persist tool turns; composer/historyForModel z rolami tool; UI skrót.
6. **Testy** — unit/feature/JS jak w decyzji 10A (mogą być pisane równolegle w fazach 1–5; faza 6 domyka braki).

## Krytyczne szczegóły implementacji

- **Dwa tryby streamu:** jeśli brak włączonych MCP (lub soft-fail discovery) → istniejący byte-forward. Jeśli tools aktywne → orchestrator musi **buforować/parsować** chunki upstream, zbierać `tool_calls`, nie forwardować ślepo niepełnych tool delt do UI jako „content”. Content/reasoning delty finalnej (lub pośrednich) rund z tekstem — tak; status tooli — osobne eventy JSON `{"event":"tool_status",...}`.
- **Guest credentials:** `ChatStreamRequest` przyjmuje opcjonalne `mcp_credentials` (id→token) tylko dla id włączonych w requestcie / rozmowie; nigdy nie zapisuj tokenów do guest localStorage. Account: ignoruj tokeny z body, bierz z encrypted DB.
- **Secrets w DB:** kolumna `mcp_servers` z castem `encrypted:array` (lub encrypted JSON). Presenter zwraca serwery z **zamaskowanym** tokenem (np. `hasToken: true`, bez raw) do UI; update settings: pusty token w PATCH = „nie zmieniaj”; nowy string = nadpisz.
- **Limity:** `MCP_MAX_TOOL_ROUNDS = 5` (config `config/llms.php`); timeout nadal z `chatTimeoutSeconds`; przy cut-off emituj `tool_status` error + jeśli jest częściowy content — wyślij go, inaczej krótki komunikat w streamie/asystencie.
- **Nazwy tooli:** model widzi `exa__web_search_exa`; przy call strip prefix → prawdziwa nazwa MCP + routing po `serverId`.

## Faza 1: Foundation (dep + model danych)

### Przegląd

Dodać produkcyjny `laravel/mcp` i utrwalić config MCP (user) + enable list (conversation) dla account i guest.

### Wymagane zmiany:

#### 1. Zależność Composer

**Plik**: `composer.json` / lock

**Cel**: MCP client dostępny w produkcji Coolify (nie tylko require-dev Boost).

**Kontrakt**: `composer require laravel/mcp` w `require` (wersja zgodna z lockiem Boost / docs Laravel 13). Smoke: klasa `Laravel\Mcp\Client` autoload w `php artisan about` / prosty test reflection.

#### 2. Migracja `user_chat_settings.mcp_servers`

**Plik**: nowa migracja + `app/Models/UserChatSettings.php`

**Cel**: Przechowywać listę serwerów użytkownika z sekretami at-rest.

**Kontrakt**:
- Kolumna `mcp_servers` json/text nullable default `[]`.
- Cast: `encrypted:array` (lub równoważny encrypted array).
- Element: `{ id: string, name: string, url: string, token: string|null }` (`id` kebab/slug unikalny w liście; `url` https MCP endpoint).
- Fillable + factory aktualizacja.

#### 3. Migracja `conversations.enabled_mcp_server_ids`

**Plik**: nowa migracja + `app/Models/Conversation.php`

**Cel**: Per-rozmowa włączanie podzbioru serwerów usera.

**Kontrakt**: Kolumna JSON array stringów (ids); cast `array`; default `[]`; fillable; Update/Store conversation requests.

#### 4. Settings + conversation HTTP API

**Pliki**: `UpdateChatSettingsRequest`, `ChatSettingsController`, `UpdateConversationRequest` / `StoreConversationRequest`, `AccountChatPresenter`

**Cel**: CRUD-ish przez istniejące PATCH-e; Inertia props z `mcpServers` (bez raw token) i `enabledMcpServerIds`.

**Kontrakt**:
- PATCH settings: walidacja listy (max N serwerów, np. 10; url `url`; id regex; token nullable string max length).
- Semantyka tokenu: omit/null/"" → zachowaj poprzedni; non-empty → replace.
- Presenter: `{ id, name, url, hasToken: bool }` — **bez** `token`.
- Conversation present + patch: `enabledMcpServerIds: string[]` (ids muszą należeć do user mcp_servers przy update — soft: filtruj nieznane).

#### 5. Guest store

**Plik**: `resources/js/composables/useGuestChatStore.js`

**Cel**: Persystować metadane serwerów i enable list bez sekretów; bump version.

**Kontrakt**:
- `GUEST_CHAT_VERSION = 3` (mismatch wipe jak dziś).
- `settings.mcpServers`: `{ id, name, url }[]` — **bez** `token`.
- Conversation: `enabledMcpServerIds: string[]`.
- Osobny in-memory map (nie w `writeStorage`) na tokeny: np. w composable UI / `Index.vue` ref `mcpTokensById` — poza store persist.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Migracje clean na świeżej DB
- Feature: PATCH settings zapisuje encrypted servers; GET/presenter nie wycieka token; pusty token w PATCH nie kasuje istniejącego
- Feature: PATCH conversation `enabled_mcp_server_ids`
- Guest: version 3 shape w unit/js jeśli istnieją testy store; w przeciwnym razie asercja w teście feature nie wymagana — pokryj w fazie 4/6
- `vendor/bin/pint --dirty --format agent`

#### Weryfikacja ręczna:

- Zalogowany: zapisz Exa URL+token w settings, reload — `hasToken` true, pole token puste w UI, ponowny save bez tokenu nie gubi klucza

**Uwaga implementacyjna**: Po fazie zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Faza 2: MCP toolkit (list / map / call)

### Przegląd

Izolowany serwis nad `Laravel\Mcp\Client` — discovery, prefixowanie, wywołania.

### Wymagane zmiany:

#### 1. Serwis MCP

**Plik**: np. `app/Services/Mcp/McpToolGateway.php` (+ ewentualnie `OpenAiToolMapper.php`)

**Cel**: Jedno API dla orchestratora: połącz, listuj, mapuj do OpenAI tools, wołaj po prefiksowanej nazwie.

**Kontrakt** (intencja):
- Input serwera: `{ id, url, token? }`.
- `Client::web($url)` + `withToken` gdy token; timeout z config.
- `listTools(servers): list<OpenAiTool>` gdzie `function.name = "{id}__{mcpName}"`, `parameters = inputSchema`.
- `callTool(prefixedName, arguments): string` (tekst wyniku / serializacja content MCP); błędy → string z `isError` dla modelu.
- Discovery fail per-server: zbierz błędy, nie wal całego requestu (orchestrator zdecyduje soft-fail gdy **zero** tools).

#### 2. Config

**Plik**: `config/llms.php`

**Cel**: Limity w jednym miejscu.

**Kontrakt**: `mcp_max_tool_rounds` (default 5), `mcp_client_timeout` (sekundy), opcjonalnie `mcp_max_servers`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Unit: mapper prefix/strip round-trip; kollizja dwóch serwerów z tą samą nazwą tool → dwie różne OpenAI names
- Unit: gateway z mock/fake Client jeśli da się podmienić; inaczej test mappera osobno + feature w fazie 3 z Http::fake na MCP HTTP jeśli Client na to pozwala
- Pint

#### Weryfikacja ręczna:

- Opcjonalnie: tinker `Client::web('https://mcp.exa.ai/mcp')->tools()` gdy sieć dostępna (nie blokuje fazy)

---

## Faza 3: Chat orchestrator (tool loop + SSE)

### Przegląd

Przy włączonych MCP: backend prowadzi pętlę model↔MCP i emituje status; bez MCP — stary byte-forward.

### Wymagane zmiany:

#### 1. Rozszerzenie requestu stream

**Plik**: `app/Http/Requests/Chat/ChatStreamRequest.php`

**Cel**: Przyjąć credentials gościa + ewentualnie enabled ids; role historii pod tool loop.

**Kontrakt**:
- `enabled_mcp_server_ids`: optional array of strings.
- `mcp_credentials`: optional array of `{ id, token }` — używane **tylko** gdy `auth()->guest()`; dla authed ignorowane.
- `messages.*.role`: dopuszczalne `system|user|assistant|tool` (oraz pola `tool_calls` / `tool_call_id` gdy obecne — walidacja luźna structured array).
- Limity body bez regresji vision.

#### 2. Orchestrator

**Plik**: np. `app/Services/Llm/ChatToolOrchestrator.php` (+ cienkie zmiany `ChatStreamController`, ewentualnie helper SSE writer)

**Cel**: Zastąpić jednostrzałowy proxy gdy są efektywne tools.

**Kontrakt**:
1. Resolve servers: auth → DB `mcp_servers` filtered by conversation/request enabled ids; guest → urls z body/settings w requestcie + tokens z `mcp_credentials` (URL-e też w requestcie: `mcp_servers: [{id,name,url}]` bez zapisu).
2. `listTools`; jeśli pusto/all fail → emit SSE event `mcp_warning` + byte-forward / zwykły stream **bez** `tools`.
3. W przeciwnym razie pętla ≤ `mcp_max_tool_rounds`:
   - POST chat/completions z `tools` + `tool_choice: auto` (lub default), `stream: true`.
   - Parsuj SSE: forward content/reasoning deltas; akumuluj `tool_calls`.
   - Jeśli finish bez tool_calls → koniec.
   - Jeśli tool_calls → dla każdego: emit `tool_status` (`calling` → `done`/`error`); `callTool`; dopisz do messages assistant(+tool_calls) i tool results; kolejna runda.
4. Przy limicie rund: emit status cut-off; zakończ stream (z ewentualnym krótkim komunikatem asystenta).
5. Session save przed długim I/O jak dziś.

#### 3. Proxy / completions helper

**Plik**: `app/Services/Llm/ChatCompletionProxy.php` (lub nowy `ChatCompletionsClient`)

**Cel**: Umożliwić zarówno byte-forward, jak i konsumowalne stream/non-stream dla orchestratora.

**Kontrakt**: Wydziel metodę do POST completions zwracającą parsowalny stream (generator linii SSE lub zebrany assistant message). Nie psuj istniejących testów byte-forward dla ścieżki bez tools.

#### 4. Format SSE dla UI

**Kontrakt**: Linie `data: {json}\n\n` gdzie:
- upstream chunk (choices/delta) — jak dziś gdy forwardujesz tekst,
- lub `{"event":"tool_status","server_id":"exa","tool":"exa__web_search_exa","status":"calling|done|error","detail":"..."}`,
- lub `{"event":"mcp_warning","message":"..."}`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Feature: Http::sequence — 1) completion z tool_calls 2) completion z final content; asercja ≥1 call MCP (Http::fake) i final SSE zawiera content; `tool_status` events obecne
- Feature: discovery fail → mcp_warning + completion bez `tools` w upstream body
- Feature: auth ignoruje `mcp_credentials` z body (token z DB)
- Feature: max rounds cut-off
- Regresja: istniejący `ChatStreamProxyTest` bez MCP nadal green
- Pint

#### Weryfikacja ręczna:

- Z Exa key + modelem wspierającym tools: pytanie wymagające search → widać status → finalna odpowiedź z wiedzą z sieci

---

## Faza 4: Frontend (panel + stream status)

### Przegląd

UI konfiguracji MCP pod system promptem; podpięcie streamu pod eventy statusu; guest tokens w pamięci.

### Wymagane zmiany:

#### 1. Panel w sidebarze

**Plik**: `resources/js/Components/Chat/ChatSidebar.vue` (+ ewentualnie mały `McpServersPanel.vue`)

**Cel**: Sekcja po Parameters (po system prompt), przed guest banner / Chats.

**Kontrakt**:
- Lista serwerów: name, url, token input (password), remove; przycisk „Add Exa” preset (`id` sugerowane `exa`, url `https://mcp.exa.ai/mcp`).
- „Add server” pusty custom.
- Na aktywnej rozmowie: checkboxy enable per server id.
- Props/emits spójne z resztą sidebara (`mcpServers`, `enabledMcpServerIds`, update events).
- Account: debounced PATCH settings / conversation jak api url / system prompt.
- Guest: update store metadanych; tokeny → parent ref, nie localStorage.

#### 2. Index wiring

**Plik**: `resources/js/Pages/Chat/Index.vue`

**Cel**: Przekazać config do sidebara i do `streamChat`.

**Kontrakt**: Przy stream — account: `enabled_mcp_server_ids`; guest: dodatkowo `mcp_servers` (id,name,url) + `mcp_credentials` (id,token) tylko dla enabled z niepustym tokenem.

#### 3. useChatStream + UI status

**Pliki**: `resources/js/composables/useChatStream.js`, `MessageThread.vue` / `Index.vue`

**Cel**: Obsłużyć `tool_status` / `mcp_warning`; pokazać postęp.

**Kontrakt**:
- Parse: jeśli `parsed.event === 'tool_status'|'mcp_warning'` → callback, nie traktuj jako delta content.
- UI: tymczasowy wiersz/status przy trwającym streamie („Calling exa__…”) i/lub wpis w wiadomości asystenta; warning banner/toast przy `mcp_warning`.
- Bez renderowania pełnego JSON wyniku tool.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- JS test parse branch dla `tool_status` (jeśli łatwy do wyciągnięcia helper; inaczej pokrycie w fazie 6)
- Pint nie dotyczy Vue; istniejące testy JS green

#### Weryfikacja ręczna:

- Guest: wpisz token Exa, reload strony → token pusty (oczekiwane); metadata url zostaje
- Account: token przetrwa reload (hasToken)
- Podczas tool call widać status; warning przy złym URL MCP i czat dalej działa

---

## Faza 5: Historia tool turns (composer + persist)

### Przegląd

Zapis tur tool w historii; kolejne prompty wysyłają kontekst tool bez reasoningu; UI skrót.

### Wymagane zmiany:

#### 1. Composer PHP + JS

**Pliki**: `ChatHistoryComposer.php`, `buildUpstreamRequest.js`, `historyForModel` w store/Index

**Cel**: Role `assistant` z `tool_calls` oraz `tool` z `tool_call_id` + content przechodzą do upstream; reasoning nadal strip z content asystenta.

**Kontrakt**: Nie wysyłaj reasoning; tool content jako string; limity rozmiaru — truncate bardzo długich wyników tool przy persist jeśli trzeba (stała nazwana).

#### 2. Persist guest + account

**Pliki**: `useGuestChatStore.js`, `useAccountChatStore.js`, `StorePromptRequest` / presenter, `MessageThread.vue`

**Cel**: Zapisać tool turns; pokazać skrót.

**Kontrakt**:
- Message shape: `role: 'tool' | 'assistant'`, opcjonalnie `toolCalls`, `toolCallId`, `toolStatusSummary` / krótki `detail`.
- Account: `prompts.role` musi akceptować `tool` (migracja enum/check jeśli ograniczone; dziś string?).
- Presenter oddaje pola potrzebne UI.
- `MessageThread`: dla tool — jedna linia statusu; nie dump JSON; assistant z samym tool_calls bez content — ukryj pusty bubble lub pokaż summary.

#### 3. request_payload

**Cel**: Nie pomnażaj ogromnych tool results w `request_payload` ponad limity — sanitizuj podobnie jak vision (skróć content tool).

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Unit composer: tool roles round-trip; reasoning nie wraca
- Feature account: zapis prompt role=tool + reload props
- JS compose mirror test
- Pint

#### Weryfikacja ręczna:

- Po rozmowie z Exa: reload — widać skróty tooli / finalną odpowiedź; kolejny prompt nadal ma sensowny kontekst (model nie „zapomina” że szukał) bez wyświetlania raw JSON

---

## Faza 6: Domknięcie testów

### Przegląd

Uzupełnić lukę testową wg decyzji 10A; regresje guest/account/vision.

### Wymagane zmiany:

#### 1. Suite

**Pliki**: testy Feature/Unit/JS wymienione w fazach 1–5 jeśli coś zostało

**Cel**: Mapper, orchestrator multi-round, settings token semantics, composer tool roles, stream parse event — green w CI.

**Kontrakt**: `php artisan test --compact` na plikach tej zmiany; nie wymagać Playwright; nie usuwać istniejących testów.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Wszystkie nowe/zmienione testy green
- Regresja: `ChatStreamProxyTest`, vision multimodal validation, account CRUD settings
- Pint dirty

#### Weryfikacja ręczna:

- Smoke: guest bez MCP = dotychczasowy czat; account z Exa = happy path raz na środowisku z modelem tools-capable

---

## Strategia testowania

### Testy jednostkowe:

- OpenAI tool mapper prefix/strip
- ChatHistoryComposer z rolami tool / tool_calls
- (opcjonalnie) parsowanie jednej linii SSE tool_status po stronie PHP jeśli wydzielone

### Testy integracyjne / Feature:

- Settings encrypted + presenter mask
- Orchestrator Http::fake sequence (LLM + MCP)
- Soft-fail discovery
- Auth vs guest credentials
- Max rounds
- Prompt persist role=tool

### Testy JS:

- `buildUpstreamRequest` / compose z tool messages
- parse helper stream events jeśli wyciągnięty

### Kroki testowania ręcznego:

1. Preset Exa + token → enable na rozmowie → pytanie „co słychać w newsach o X”
2. Zły URL MCP → warning, zwykła odpowiedź modelu
3. Guest token znika po reload; account hasToken zostaje
4. Model bez tools: soft path / brak crash

## Uwagi dotyczące wydajności

- Każda runda tool = dodatkowy POST do LLM + MCP; limit 5 + istniejący timeout 600s.
- `tools/list` na każdy send — akceptowalne MVP; cache per-request w orchestratorze wystarczy (nie cache cross-request w MVP).
- Duże wyniki Exa: truncate przed dopisaniem do historii/payload limitów.

## Uwagi dotyczące migracji

- Nowe kolumny nullable/default `[]` — bez backfill.
- Guest version 3 wipe — akceptowalne (jak poprzednie bump).
- Rotacja `APP_KEY` unieważnia encrypted `mcp_servers` — użytkownik musi wpisać tokeny ponownie (udokumentować w Notes change jeśli trzeba).

## Referencje

- Badania: `context/changes/add-mcp-servers-to-interface/research.md`
- MCP tools spec: https://modelcontextprotocol.io/specification/2025-06-18/server/tools
- Exa MCP: https://exa.ai/mcp
- Laravel MCP client docs (Boost search-docs / laravel.com mcp.md)
- Wzorzec planu: `context/archive/2026-08-05-vision-image-input/plan.md`
- Sidebar system prompt: `resources/js/Components/Chat/ChatSidebar.vue:199-208`
- Stream controller: `app/Http/Controllers/Chat/ChatStreamController.php:34-59`
- Proxy byte-forward: `app/Services/Llm/ChatCompletionProxy.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Foundation (dep + model danych)

#### Automated

- [x] 1.1 Migracje mcp_servers + enabled_mcp_server_ids stosują się czysto — 6c5a403
- [x] 1.2 Feature: settings encrypted + presenter bez raw token + pusty PATCH nie kasuje tokenu — 6c5a403
- [x] 1.3 Feature: conversation enabled_mcp_server_ids PATCH — 6c5a403
- [x] 1.4 laravel/mcp w require + autoload Client — 6c5a403
- [x] 1.5 Pint dirty — 6c5a403

#### Manual

- [x] 1.6 Ręcznie: zapis Exa token, reload, hasToken, re-save bez tokenu zachowuje klucz — 6c5a403

### Phase 2: MCP toolkit (list / map / call)

#### Automated

- [x] 2.1 Unit: mapper prefix/strip + kolizje nazw — 86c842e
- [x] 2.2 Unit/feature: gateway list/call (z fake tam gdzie możliwe) — 86c842e
- [x] 2.3 Config llms.php limity MCP — 86c842e
- [x] 2.4 Pint dirty — 86c842e

#### Manual

- [x] 2.5 Opcjonalnie: ręczne tools/list na Exa z siecią — 86c842e

### Phase 3: Chat orchestrator (tool loop + SSE)

#### Automated

- [x] 3.1 Feature: multi-round tool loop (Http::fake sequence) + tool_status events — ebef906
- [x] 3.2 Feature: soft-fail discovery → mcp_warning + stream bez tools — ebef906
- [x] 3.3 Feature: auth ignoruje mcp_credentials z body — ebef906
- [x] 3.4 Feature: max rounds cut-off — ebef906
- [x] 3.5 Regresja ChatStreamProxyTest bez MCP — ebef906
- [x] 3.6 Pint dirty — ebef906

#### Manual

- [x] 3.7 Ręcznie: Exa + model z tools → status + finalna odpowiedź — ebef906

### Phase 4: Frontend (panel + stream status)

#### Automated

- [x] 4.1 JS/parse: obsługa event tool_status / mcp_warning (helper lub test) — ec77224
- [x] 4.2 Istniejące testy JS bez regresji — ec77224

#### Manual

- [x] 4.3 Panel pod system promptem: preset Exa, custom server, enable na rozmowie — ec77224
- [x] 4.4 Guest: token nie w localStorage po reload; account: hasToken po reload — ec77224
- [x] 4.5 UI pokazuje status tool i warning przy złym MCP — ec77224

### Phase 5: Historia tool turns (composer + persist)

#### Automated

- [x] 5.1 Unit composer PHP: role tool / tool_calls; reasoning strip — 9cb69f1
- [x] 5.2 JS compose mirror — 9cb69f1
- [x] 5.3 Feature: persist prompt role=tool + reload presenter — 9cb69f1
- [x] 5.4 Pint dirty — 9cb69f1

#### Manual

- [x] 5.5 Reload po tool chat: skróty widoczne, bez raw JSON; kolejny prompt ma kontekst — 9cb69f1

### Phase 6: Domknięcie testów

#### Automated

- [x] 6.1 Pełny zestaw testów tej zmiany green — b11a777
- [x] 6.2 Regresja vision + account CRUD + stream proxy — b11a777
- [x] 6.3 Pint dirty — b11a777

#### Manual

- [ ] 6.4 Smoke guest bez MCP + account Exa happy path
