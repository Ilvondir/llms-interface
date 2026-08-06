# MCP servers w interfejsie czatu — Krótki plan

> Pełny plan: `context/changes/add-mcp-servers-to-interface/plan.md`
> Badania: `context/changes/add-mcp-servers-to-interface/research.md`

## Co i dlaczego

Użytkownik ma z panelu (pod system promptem) podpinać **własne zewnętrzne serwery MCP** (token/URL) — na start Exa, ale dowolny HTTP MCP. Backend przy każdym prompcie listuje tools, dokleja je do modelu i prowadzi **pętlę tool_calls → MCP → z powrotem do modelu**, ze statusem w UI.

## Punkt wyjścia

Czat to cienki SSE proxy bez tools; ustawienia to API URL + params + system prompt. `laravel/mcp` jest tylko transitive Boost (dev), ale pakiet ma Client HTTP gotowy do użycia po dodaniu do `require`.

## Pożądany stan końcowy

Skonfigurowane MCP per user, włączane per rozmowa; send promptu uruchamia discovery + tool loop (max 5); w czacie widać status wywołań; historia trzyma tury tool (skrót w UI); gość bez zapisu tokenów w localStorage; konto z encrypted tokenami w DB.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
| ------ | ----- | ------------------- | ------ |
| Tool loop | Pełny backend loop | Samo `tools[]` bez execute nic nie daje | Plan / user |
| Gość | Token tylko w request/pamięci | Unika plain-text w localStorage | Plan |
| Config | User settings + enable na rozmowie | Jeden token Exa, elastyczność per chat | Plan |
| Transport | Tylko HTTP | Exa + bezpieczeństwo Coolify | Plan |
| Secrets DB | `encrypted:array` | Standard Laravel | Plan |
| UX tooli | Status w czacie, bez raw JSON | Postęp bez clutteru | Plan |
| Historia | Zapisuj tool turns, UI skrót | Kontekst kolejnych tur | Plan |
| Limit pętli | Max 5 + cut-off | Chroni timeout/koszt | Plan |
| Błędy MCP | Soft fail + warning | Nie blokuje czatu | Plan |
| Kolizje nazw | Prefix `{serverId}__` | Deterministyczny routing | Plan |
| Testy | PHPUnit/JS, bez Playwright | Krytyczna logika bez kosztu E2E | Plan |
| Klient MCP | `laravel/mcp` w require | Gotowy Client::web | Badania / Plan |

## Zakres

**W zakresie:**
- Panel MCP pod system promptem (preset Exa + custom)
- Encrypted settings (account), ephemeral tokens (guest)
- Backend discovery + OpenAI tools map + tool loop + SSE status
- Persist tool turns + composer roles
- Testy unit/feature/JS

**Poza zakresem:**
- stdio MCP, OAuth Exa, Laravel AI SDK, Playwright, update roadmap/PRD, MCP resources/prompts

## Architektura / Podejście

```
Settings.mcpServers (+ encrypted token)
Conversation.enabledMcpServerIds
        ↓
POST /chat/stream
        ↓
Orchestrator: tools/list → tools[] → completions
   └─ tool_calls → MCP tools/call → tool_status SSE → repeat ≤5
        ↓
Final content stream + persist tool turns
```

Bez włączonych MCP zostaje istniejący byte-forward.

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| ---- | ------------ | --------------- |
| 1. Foundation | Dep + DB/settings/guest shape | Semantyka „pusty token = nie kasuj” |
| 2. Toolkit | list/map/call + prefix | Dopasowanie Client API do Exa |
| 3. Orchestrator | Tool loop + SSE events | Parsowanie SSE vs byte-forward |
| 4. Frontend | Panel + status UI | Guest token lifecycle |
| 5. Historia | Persist + composer tool roles | Rozmiar wyników Exa / limity |
| 6. Testy | Domknięcie suite | Regresje vision/stream |

**Wymagania wstępne:** działający stack Laravel/Vue jak dziś; do ręcznego happy path: model z tool calling + (opcjonalnie) klucz Exa.  
**Szacowany wysiłek:** ~4–6 sesji w 6 fazach (orchestrator = najcięższy).

## Otwarte ryzyka i założenia

- Lokalny LM Studio / endpoint **musi** wspierać OpenAI tool calling — inaczej soft path bez tools.
- `laravel/mcp` Client musi poprawnie mówić Streamable HTTP z Exa; jeśli nie — cienki HTTP adapter w toolkit (ta sama fasada).
- Rotacja `APP_KEY` = utrata odszyfrowania tokenów MCP.

## Kryteria sukcesu (podsumowanie)

- User dodaje Exa (lub inny HTTP MCP) z tokenem i włącza na rozmowie.
- Prompt uruchamia tools; w UI widać status; model dostaje wyniki i finalną odpowiedź.
- Token gościa nie ląduje w localStorage; token konta nie wycieka w Inertia props.
