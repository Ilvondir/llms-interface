---
change_id: add-mcp-servers-to-interface
title: Add MCP servers to interface
status: implemented
created: 2026-08-06
updated: 2026-08-06
archived_at: null
---

## Notes

Intent (confirmed):
- Użytkownik w UI dodaje dowolne zewnętrzne serwery MCP (URL + token/auth co trzeba) — panel pod system promptem; Exa jako pierwszy wygodny preset, nie hardcode-only
- Przy wysyłce promptu backend: łączy się z skonfigurowanymi MCP → `tools/list` → mapuje definicje na `tools[]` OpenAI-compatible → dokleja do requestu do modelu
- Jeśli model zwraca `tool_calls`: backend wykonuje `tools/call` na właściwym MCP, dokleja wynik jako wiadomość tool, ponawia completion — aż model da zwykłą odpowiedź (bez kolejnych wywołań)
- Secrets użytkownika zostają po stronie backendu; gość vs konto do rozstrzygnięcia w planie

Decisions (planning):
- 1B guest+auth, guest token ephemeral only
- 2B user settings servers + per-conversation enable ids
- 3A HTTP only
- 4A encrypted tokens in DB
- 5B tool status in chat
- 6B persist tool turns, UI summary only
- 7A max 50 rounds (raised from 5; env `LLMS_MCP_MAX_TOOL_ROUNDS`)
- 8A soft fail + warning
- 9A prefix serverId__toolName
- 10A PHPUnit/JS, no Playwright
