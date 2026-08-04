<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Pierwsza rozmowa gościa

- **Plan**: context/changes/guest-first-chat/plan.md
- **Scope**: Phases 1–5 of 5
- **Date**: 2026-08-04
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical 4 warnings 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Findings

### F1 — HTTP redirects can bypass intended SSRF boundary

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Services/Llm/ChatCompletionProxy.php:34
- **Detail**: Laravel HTTP client follows redirects by default. A public URL can 302 to internal targets (e.g. link-local metadata) even though the plan intentionally allows any user-supplied API base URL for a solo self-host. Direct arbitrary URL is accepted; redirect following widens the blast radius beyond "user's LM Studio".
- **Fix A ⭐ Recommended**: Call `->withoutRedirecting()` on stream and models upstream requests
  - Strength: Small change; preserves intentional open URL while blocking redirect-based pivot.
  - Tradeoff: Breaks rare hosts that require a redirect hop to the real API root.
  - Confidence: HIGH — Laravel Http supports withoutRedirecting().
  - Blind spot: Have not verified whether any common LM Studio/ngrok setups rely on redirects.
- **Fix B**: Keep redirects; document risk for operators only
  - Strength: Zero code change; matches current solo-owner threat model.
  - Tradeoff: Leaves redirect SSRF path open if the app is ever exposed more broadly.
  - Confidence: MEDIUM — depends on deployment assumptions.
  - Blind spot: Future multi-user hosting not gated.
- **Decision**: PENDING

### F2 — Unbounded messages array / content size

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/Chat/ChatStreamRequest.php:24
- **Detail**: `system_prompt` is capped at 100000 chars, but `messages` has no max count and `messages.*.content` has no max length. Guests can POST very large payloads that the server holds and forwards upstream (DoS / worker pressure), despite throttle.
- **Fix**: Add validation limits e.g. `messages` → `max:200`, `messages.*.content` → `max:100000`.
- **Decision**: PENDING

### F3 — Upstream error bodies returned to clients

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/Llm/ChatCompletionProxy.php:48
- **Detail**: Failed upstream responses embed up to 500 chars of upstream body in exception messages returned as JSON to the browser. Upstream stacks may leak paths or host details.
- **Fix**: Log upstream body server-side; return generic client messages mapped by status code.
- **Decision**: PENDING

### F4 — Mid-stream upstream read failures unhandled

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Services/Llm/ChatCompletionProxy.php:56
- **Detail**: Stream loop checks `connection_aborted()` but does not catch upstream read/disconnect errors after SSE headers are sent. Abrupt upstream drop can yield a truncated or broken stream.
- **Fix**: Wrap the read loop body in try/catch, log, close upstream, and end the stream cleanly (optional final SSE error event if client supports it).
- **Decision**: PENDING

### F5 — localStorage schema version is 2 vs plan's version 1

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/composables/useGuestChatStore.js:5
- **Detail**: Plan contract said shape version 1; implementation uses `GUEST_CHAT_VERSION = 2` (intentional bump during development). Key remains `llms.guest.v1`. Behavior matches TTL/multi-conv goals.
- **Fix**: Document version 2 in plan addendum, or leave as-is.
- **Decision**: PENDING

### F6 — Client history helper duplicates store.toModelMessages

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Pages/Chat/Index.vue
- **Detail**: `Index.vue` uses `historyForModel()` (also strips think tags) instead of `store.toModelMessages()`. Server-side `ChatHistoryComposer` still strips reasoning. Intent preserved; logic is duplicated.
- **Fix**: Route outbound history through one helper that strips think tags + reasoning, or document why Index needs a separate path.
- **Decision**: PENDING

### F7 — Open URL proxy / SSRF is intentional

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Scope Discipline
- **Location**: app/Http/Requests/Chat/ChatStreamRequest.php:21
- **Detail**: Guests can point the server at any http(s) URL. Plan and plan-brief explicitly reject host allowlists for solo self-host. Not a defect relative to scope; remains an operator risk if exposure widens.
- **Fix**: No code change required for S-01; revisit allowlist/blocklist in a later hardening change if multi-tenant or public SaaS.
- **Decision**: PENDING
