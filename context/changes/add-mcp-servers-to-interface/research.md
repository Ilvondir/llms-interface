---
date: 2026-08-06T20:16:03+02:00
researcher: Auto
git_commit: f482fc84af2acac11848740d2bb12b661a664c27
branch: master
repository: llms-interface
topic: "Podpinanie serwerów MCP (Exa + konfigurowalne) pod system promptem; backend listuje tools i dokleja do modelu"
tags: [research, codebase, mcp, chat-sidebar, chat-stream, exa, laravel-mcp, tool-calling]
status: complete
last_updated: 2026-08-06
last_updated_by: Auto
---

# Research: MCP servers in LLMsInterface (panel + backend tool attach)

**Date**: 2026-08-06T20:16:03+02:00  
**Researcher**: Auto  
**Git Commit**: f482fc84af2acac11848740d2bb12b661a664c27  
**Branch**: master  
**Repository**: llms-interface

## Research Question

Chcę dodać możliwość podpinania serwerów MCP do modelu; panel pod system promptem. Pierwszy target: Exa.ai, ale konfiguracja z panelu. Backend ma pytać o toole z MCP i doklejać je do modelu. Czy jest ustandaryzowany schemat? (Quick / pełny obraz; poza roadmapą.)

## Summary

**Tak — MCP ma ustandaryzowany protokół (JSON-RPC) i schemat tooli; konfiguracja klientów jest de facto ustandaryzowana, ale nie jednym oficjalnym JSON Schema w specyfikacji transportów.**

| Warstwa | Standaryzacja | Co użyć w produkcie |
|---------|---------------|---------------------|
| Protokół | Oficjalny MCP: `tools/list`, `tools/call`, Tool `{name, description, inputSchema, …}` | Backend discovery + invoke |
| Transport | Spec: **stdio** + **Streamable HTTP** (SSE legacy) | Exa = HTTP `https://mcp.exa.ai/mcp` → `Client::web(...)` |
| Config UI klientów | De facto `mcpServers` (Claude/Cursor): `url` *lub* `command`+`args`+`env` | Panel: lista serwerów (id, url/headers/token, enabled) |
| OpenAI tools | Osobny format `tools: [{type:"function", function:{name,description,parameters}}]` | Mapowanie MCP Tool → OpenAI function tool |

W tej aplikacji **nie ma dziś tool-calling ani MCP klienta produktowego**. Czat to cienki SSE proxy do OpenAI-compatible `/v1/chat/completions`. System prompt siedzi w `ChatSidebar` w sekcji Parameters — naturalne miejsce na panel MCP tuż pod nim.

`laravel/mcp` jest w locku tylko jako **transitive require-dev** Boosta (serwer lokalny dla agentów). Pakiet **ma jednak MCP Client** (`Client::web`, `->tools()`, `->callTool()`) — idealny kandydat do produktu, ale trzeba dodać go jako **bezpośrednią zależność produkcyjną**.

## Detailed Findings

### 1. Ustandaryzowany schemat MCP

**Tool (spec):** odpowiedź `tools/list` zwraca tablicę tooli z m.in.:

- `name` (wymagane)
- `title` (opcjonalne)
- `description`
- `inputSchema` (JSON Schema parametrów)
- `outputSchema` / `annotations` (opcjonalne)

Wywołanie: `tools/call` z `{ name, arguments }`.  
Źródło: [MCP Tools spec 2025-06-18](https://modelcontextprotocol.io/specification/2025-06-18/server/tools).

**Transporty:** stdio (lokalny subprocess) oraz Streamable HTTP (jeden endpoint GET/POST). Starszy HTTP+SSE jest deprecated. Dla webowej aplikacji Coolify **stdio na serwerze jest słabe** (spawn procesów, secrets, brak izolacji) — preferować remote HTTP jak Exa.

**Config klientów (de facto, nie „jeden oficjalny schema w transports”):**

```json
{
  "mcpServers": {
    "exa": {
      "url": "https://mcp.exa.ai/mcp"
    }
  }
}
```

Albo lokalnie: `{ "command": "npx", "args": [...], "env": { ... } }`.  
VS Code czasem używa `servers` + `type: "http"`. Panel produktu powinien trzymać **własny, wąski kształt** (np. `id`, `transport: "http"`, `url`, `headers`/`token`, `enabled`) zmapowany na Laravel `Client::web(...)->withToken(...)`.

### 2. Exa.ai jako pierwszy serwer

- Hosted MCP: `https://mcp.exa.ai/mcp`
- Auth: opcjonalnie API key (`x-api-key` / `Authorization: Bearer` / `?exaApiKey=`), OAuth, albo free tier bez klucza
- Query `?tools=web_search_exa,...` ogranicza zestaw tooli
- Docs: [exa.ai/mcp](https://exa.ai/mcp), [exa.ai/docs/reference/exa-mcp](https://exa.ai/docs/reference/exa-mcp)

Dla panelu: predefiniowany preset „Exa” (URL + pole API key) + dowolne inne URL HTTP.

### 3. UI — panel pod system promptem

System prompt: `resources/js/Components/Chat/ChatSidebar.vue` **199–208**, wewnątrz sekcji Parameters (**139–209**).

Kolejność sidebara: API URL → Model → Parameters (temp/top_p/max + **system prompt**) → guest banner → Chats.

**Miejsce MCP:** nowy `border-t` block **po** Parameters (po L209), przed guest/Chats — albo pod textarea system promptu wewnątrz Parameters. Wzorzec: małe labele, `text-xs`, state przez `v-model` emits z `Index.vue`.

Conversation shape dziś (guest + account): `systemPrompt`, `model`, `params` — **brak `mcpServers`**.  
`apiBaseUrl` jest na poziomie settings użytkownika, nie rozmowy. UI „pod system promptem” sugeruje **per-conversation** (jak system prompt) — do potwierdzenia w planie.

Persistence:

- Guest: `localStorage` `llms.guest.v1` (version **2** — bump przy migracji)
- Account: debounced `PATCH` conversations (`system_prompt`, `model`, `params`) + settings dla URL

### 4. Pipeline czatu — brak tools

Przepływ:

```
Index.vue → useChatStream POST /chat/stream
  → ChatStreamRequest
  → ChatHistoryComposer (role+content only)
  → ChatStreamController payload
  → ChatCompletionProxy SSE byte-forward
  → browser: tylko delta.content / reasoning
```

Kluczowe miejsca:

- Payload assemble: `app/Http/Controllers/Chat/ChatStreamController.php:34-59` — **brak `tools`**
- Composer: `app/Services/Llm/ChatHistoryComposer.php` — role `system|user|assistant` only
- Validation: `ChatStreamRequest` — role bez `tool`
- Client: `resources/js/composables/useChatStream.js` — ignoruje `tool_calls`

Proxy jest transparentnym pipe — delty `tool_calls` dojdą do przeglądarki, ale nikt ich nie obsługuje.

**Wymagany loop produktowy (backend-centric, zgodnie z intencją użytkownika):**

1. Z konfiguracji MCP zbudować klienta(ów) → `tools/list`
2. Zmapować MCP tools → OpenAI `tools[]`
3. Dodać `tools` do payload w `ChatStreamController` (lub osobnym orchestratorze)
4. Gdy model zwróci `tool_calls`: `tools/call` na MCP → dodać wiadomości `assistant`+`tool` → ponowić completion
5. Streamować treść końcową (i opcjonalnie status tooli) do UI

Uwaga: obecny stream jest „jednym shotem” proxy. Tool loop prawie na pewno wymaga **orchestracji po stronie Laravel** (nie tylko byte-forward), bo secrets/API keys MCP nie powinny iść przez przeglądarkę, a Exa key musi zostać na serwerze.

### 5. `laravel/mcp` w tym repo

| Fakt | Status |
|------|--------|
| W `composer.json` require? | **Nie** |
| W locku? | Tak — via `laravel/boost` (require-dev), v0.9.x |
| Użycie w `app/`? | **Brak** |
| Boost | `Mcp::local('laravel-boost', …)` — tylko dla agentów IDE |

**Client API (docs Laravel 13 / laravel/mcp):**

```php
use Laravel\Mcp\Client;

$client = Client::web('https://mcp.exa.ai/mcp')->withToken($apiKey);
$tools = $client->tools();
$result = $client->callTool('web_search_exa', [...]);
```

To pokrywa discovery + invoke. Mapowanie do formatu OpenAI function tools i agent loop nad LM Studio/OpenAI-compatible endpointem **nie jest** jeszcze w produkcie — trzeba napisać (lub rozważyć Laravel AI SDK, jeśli kiedyś wejdzie w stack; dziś go nie ma w PRD).

**Deploy:** Coolify produkcja nie instaluje require-dev → bez bezpośredniego `composer require laravel/mcp` klient **nie będzie dostępny** w produkcji.

### 6. Roadmapa / historia

- PRD/roadmap: MCP **nie ma**; non-goal to RAG, nie tools.
- Archive (guest-first, account persistence, vision): brak decyzji o MCP/Exa.
- Jedyna wzmianka produktowa: ten change folder.

## Code References

- `resources/js/Components/Chat/ChatSidebar.vue:199-208` — system prompt UI
- `resources/js/Components/Chat/ChatSidebar.vue:139-209` — Parameters section
- `resources/js/Pages/Chat/Index.vue:83-122`, `321-341` — sidebar wiring
- `app/Http/Controllers/Chat/ChatStreamController.php:34-59` — payload bez tools
- `app/Services/Llm/ChatHistoryComposer.php` — historia bez tool roles
- `app/Services/Llm/ChatCompletionProxy.php` — transparent SSE proxy
- `resources/js/composables/useChatStream.js` — parse content/reasoning only
- `context/foundation/prd.md` — brak MCP; FR system prompt / chat
- `context/foundation/roadmap.md` — brak slice MCP

## Architecture Insights

1. **Cienki proxy ≠ agent loop.** Doklejenie `tools[]` to połowa pracy; druga połowa to wieloturowy loop z `tools/call` po stronie backendu.
2. **Secrets:** API keys MCP (Exa) muszą żyć w requestach serwerowych / DB zaszyfrowane — nie w localStorage gościa bez świadomej akceptacji ryzyka. Gość + MCP remote = decyzja produktowa (włączać tylko dla auth? albo klucze tylko w pamięci sesji?).
3. **LM Studio / lokalny model** musi wspierać OpenAI tool calling; inaczej `tools` w payloadzie nic nie da.
4. **Preset + generic:** UI z presetem Exa + dowolny HTTP URL pokrywa „najpierw Exa, ale konfigurowalne”.
5. **Scope config:** per-conversation (obok system prompt) vs user settings (jak API URL) — UI sugeruje conversation; klucze API mogą lepiej siedzieć w user settings.
6. **laravel/mcp Client** skraca discovery/invoke; nie zastępuje mapowania OpenAI ani stream orchestracji.

## Historical Context (from prior changes)

- `context/archive/2026-08-04-guest-first-chat/` — ustalił guest localStorage + stream proxy; wzorzec ustawień w sidebarze.
- `context/archive/2026-08-05-account-conversation-persistence/` — mirror ustawień rozmowy do DB (`system_prompt`, `params`); ten sam wzorzec rozszerzyć o `mcp_servers` JSON (lub osobną tabelę).
- Brak prior research o tools/MCP.

## Related Research

- Brak wcześniejszych `research.md` w changes/archive dla tego tematu.

## Open Questions

1. **Zakres MVP tej zmiany:** tylko discovery + `tools[]` w jednym requestcie (model sam „nie wykona” bez loop), czy pełny tool loop na backendzie?
2. **Persystencja kluczy:** per-user encrypted vs per-conversation vs tylko w UI (ephemeral)?
3. **Gość:** MCP w ogóle dla guest, czy tylko zalogowany (jak już częściowo image upload)?
4. **Kompatybilność modelu:** jak wykrywać / komunikować, że lokalny endpoint nie obsługuje tools?
5. **Czy dodać `laravel/mcp` do `require` produkcyjnego**, czy ręczny HTTP client pod Streamable HTTP?
6. **Roadmapa:** dodać nowy slice S-0x, czy świadomie zostawić jako change poza roadmapą?
)
