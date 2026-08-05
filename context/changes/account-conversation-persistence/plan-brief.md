# Trwałość rozmów na koncie — Krótki plan

> Pełny plan: `context/changes/account-conversation-persistence/plan.md`
> Badania: (brak osobnego `research.md` — decyzje z sesji `/10x-plan` + foundation + archiwum S-01)
> Roadmap: S-02 w `context/foundation/roadmap.md`

## Co i dlaczego

Zalogowany ma Conversation + Prompts w DB z parametrami odpytania (FR-008), przy pełnej parzystości pól z guest localStorage. Gość nadal tylko lokalnie — bez rekordów serwerowych.

## Punkt wyjścia

Po S-01 czat działa end-to-end, ale zawsze przez `useGuestChatStore`; auth zmienia tylko nav. Brak tabel Conversation/Prompt; schema guard to blokuje. Proxy LLM publiczne.

## Pożądany stan końcowy

Po login/register UI czyta/pisze wyłącznie DB: lista i CRUD rozmów, ustawienia, tury z reasoning/stats/params/requestPayload. Guest path nietknięty. Cudze ID → 403.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
| ------ | ----- | ------------------- | ------ |
| Zakres CRUD | Pełna lista + create/rename/delete + load | UX jak gość, magazyn konta | Plan |
| Model | Conversation + Prompt (wiersz = wiadomość), params na asystencie | FR-008 dosłownie + nazwa tabeli | Plan |
| Moment zapisu | User od razu; assistant po finish/error (partial OK) | Spójne z guest store | Plan |
| Guest→konto | Bez auto-importu; po auth tylko DB | Prosty guardrail, osobne światy | Plan |
| API CRUD | Inertia visits/mutacje; stream nadal fetch | Wybrane przez usera; SSE ≠ Inertia | Plan |
| Authorize | ConversationPolicy + scoped queries | 403 + testowalność | Plan |
| Guest vs CRUD | Trasy tylko `auth`; front branch | Niemożliwe przypadkowe inserty | Plan |
| Testy | Feature dual-path (401/403/params/guest isolation) | Chroni S-01 + FR-008 | Plan |
| Parzystość pól | Cały shape localStorage w DB | Wymaganie usera | Plan |

## Zakres

**W zakresie:** migracje settings/conversations/prompts, policy, Inertia CRUD + home auth props, front branch, testy Feature, cascade delete.

**Poza zakresem:** import guest→DB, auth na stream, vision, teams, soft deletes, Playwright, TTL na koncie.

## Architektura / Podejście

```
Guest:  Index → useGuestChatStore (localStorage) → fetch chat.stream
Auth:   Index ← Inertia props (settings, conversations, active+prompts)
          → router CRUD (conversations/prompts/settings)
          → fetch chat.stream (bez zmian)
        Policy: user_id ownership
```

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| ---- | ------------ | --------------- |
| 1. Schemat i modele | Tabele + Eloquent + policy | Kolejność FK settings↔conversations |
| 2. Inertia CRUD | Auth home/props + mutacje | Wyciek danych / guest insert |
| 3. Front branch | Jeden UI, dwa store | Sync props vs optimistic stream |
| 4. Testy dual-path | CI guardraile | Regresja schema guard |

**Wymagania wstępne:** S-01 done (guest chat + proxy).
**Szacowany wysiłek:** ~3–4 sesje w 4 fazach.

## Otwarte ryzyka i założenia

- Inertia reload podczas/po streamie wymaga `only`/`preserveState`, inaczej UX „mryga”.
- Mapowanie snake_case↔camelCase musi być jednej warstwy, żeby nie rozjechać komponentów.
- `active_conversation_id` FK wymaga ostrożnej kolejności migracji.

## Kryteria sukcesu (podsumowanie)

- Zalogowany ma trwałe rozmowy/prompty z pełnym shape po reload.
- Gość nie tworzy wierszy serwerowych; cudze rozmowy → 403.
- Stream/reasoning/stats działają jak w S-01 na obu ścieżkach.
