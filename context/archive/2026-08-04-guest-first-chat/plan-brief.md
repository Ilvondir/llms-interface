# Pierwsza rozmowa gościa — Krótki plan

> Pełny plan: `context/changes/guest-first-chat/plan.md`
> Badania: (brak osobnego `research.md` — decyzje z sesji `/10x-plan` + foundation)
> Roadmap: S-01 w `context/foundation/roadmap.md`

## Co i dlaczego

Budujemy najmniejszy pełny przepływ produktu: gość podaje URL API modelu, ustawia parametry/system prompt, czatuje i widzi streamowaną odpowiedź z osobnym reasoningiem oraz statsami, przy trwałości tylko w przeglądarce. To gwiazda przewodnia MVP — bez tego nie ma sensu S-02 (konto) ani reszta.

## Punkt wyjścia

Repo to Jetstream (Inertia/Vue) z auth i Dashboard; `/` dziś spycha gościa na login. Zero proxy LLM, localStorage, streamingu ani UI czatu. Deploy FrankenPHP/Caddy z globalnym `encode` (ryzyko dla SSE).

## Pożądany stan końcowy

Na `/` działa czat jak ChatGPT: lewy panel (URL, modele, params, system prompt, lista rozmów), wątek ze streamem, reasoning/stats, localStorage ≤1 dzień. Backend składa historię bez reasoningu i streamuje z LM Studio do frontu. Zalogowany ma w nav link do Profilu. Brak tabel Conversation.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
| ------ | ----- | ------------------- | ------ |
| Wejście gościa | `/` = czat publiczny | Guest-first z PRD | Plan |
| Zakres UI | Multi-conv + picker `/v1/models` | UI jak ChatGPT | Plan |
| Markdown | Poza S-01 | Mniej zależności/XSS | Plan |
| URL upstream | Proxy dowolnego URL | Solo self-host / ngrok (FR-001) | Plan |
| Postęp | SSE passthrough backend→front | NFR + kontrakt OpenAI | Plan |
| Trwałość | localStorage + TTL 1 dzień | FR-009 + limit śladu | Plan |
| Params | temperature, max_tokens, top_p | Cienki wspólny podzbiór | Plan |
| Błąd mid-stream | Zachowaj partial | Nie trać widocznych tokenów | Plan |
| API key | Brak w UI (LM Studio) | Tylko LM Studio na razie | Plan |
| Auth upstream | Bez Authorization | Typowe LM Studio | Plan |
| Layout | ChatLayout + link Profil | AppLayout wymaga usera | Plan |
| Testy | Http::fake + assertStreamed + unit compose | CI bez żywego hosta | Plan |
| Cut-line | Najpierw stream/reasoning/store; picker→ręczne id | Chroni Primary Success | Plan |
| Format streamu | `response()->stream` nie `eventStream` | Zachować wire OpenAI | Plan (badania) |

## Zakres

**W zakresie:** publiczny `/`, ChatLayout, localStorage multi-conv + TTL, proxy models + chat stream, compose bez reasoningu, UI stream/reasoning/stats/picker, testy Feature, Caddy encode fix na stream.

**Poza zakresem:** DB Conversation/Prompt (S-02), vision (S-03), markdown, API key UI, allowlista hostów, Playwright, WebSockets, `@laravel/stream-vue`.

## Architektura / Podejście

```
Browser (Chat/Index + localStorage)
  │  fetch POST + CSRF
  ▼
Laravel web routes (no auth) → Composer (strip reasoning)
  │  Http stream
  ▼
LM Studio OpenAI-compatible (/v1/chat/completions, /v1/models)
  │  SSE passthrough
  ▼
Browser ReadableStream → UI + localStorage
```

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| ---- | ------------ | --------------- |
| 1. Routing i shell | Publiczny `/` + ChatLayout | Semantyka home vs Jetstream |
| 2. localStorage | Multi-conv + TTL | Schema / utrata danych przy bump wersji |
| 3. Proxy LLM | Models + SSE stream | Timeouty, session lock, SSRF świadomy |
| 4. UI wire-up | Stream, picker, stats, partial errors | Parsing SSE / stats z LM Studio |
| 5. Testy + deploy | CI + Caddy encode | Buforowanie SSE w prod |

**Wymagania wstępne:** działający scaffold Laravel/Jetstream; dostęp do LM Studio (ręcznie).
**Szacowany wysiłek:** ~3–5 sesji w 5 fazach (after-hours solo).

## Otwarte ryzyka i założenia

- Kontrakt reasoningu/stats w streamie LM Studio bywa niespójny — TTFT i t/s mogą być liczone po stronie klienta.
- Globalny Caddy `encode` musi zostać wyłączony na ścieżce streamu, inaczej NFR „napływ” nie zadziała w prod.
- Świadome ryzyko SSRF przy dowolnym URL — akceptowane dla jednego właściciela.

## Kryteria sukcesu (podsumowanie)

- Gość bez konta kończy pełną rozmowę ze streamem, reasoningiem (gdy jest) i statsami.
- Reasoning nie wraca do kolejnych requestów; serwer nie zapisuje rozmów gościa.
- Po reload rozmowy ≤1 dzień są dostępne lokalnie; zalogowany wchodzi w Profil z UI czatu.
