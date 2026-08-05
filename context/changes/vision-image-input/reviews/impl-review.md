<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Vision image input

- **Plan**: context/changes/vision-image-input/plan.md
- **Scope**: Phases 1–4 of 4
- **Date**: 2026-08-05
- **Verdict**: APPROVED (findings fixed post-review)
- **Findings**: 0 critical / 5 warnings fixed / 2 observations deferred

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Public chat.stream still accepts multimodal image parts

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/Chat/ChatStreamRequest.php:11-14
- **Detail**: Plan gated vision UI to auth only, but kept `chat.stream` public (S-01 pattern). Any session that can hit the stream can POST `image_url` data-URLs; guest store/UI block images, but curl/browser can bypass UI. Matches planned public proxy, conflicts with product intent “auth-only images” at the API boundary.
- **Fix A ⭐ Recommended**: Reject `image_url` parts in `ChatStreamRequest` / `MessageContentRule` when `$this->user()` is null (422); keep text stream public.
  - Strength: Enforces auth-only vision at the only server boundary that forwards to the model.
  - Tradeoff: Slightly special-cases multimodal on a previously uniform public proxy.
  - Confidence: HIGH — aligns with planning decision 2C.
  - Blind spot: Whether any future guest vision path is desired later.
- **Fix B**: Leave stream open; rely on UI + rate limits only.
  - Strength: Zero backend change; matches literal “stream stays public”.
  - Tradeoff: Guests can still burn proxy bandwidth with images.
  - Confidence: MEDIUM.
  - Blind spot: Abuse volume in production.
- **Decision**: FIXED — applied recommended fix in follow-up commit

### F2 — No max messages count; per-message content up to 5.5M chars

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Support/Chat/ChatContentLimits.php:14; ChatStreamRequest messages rules
- **Detail**: Plan raised content cap for one compressed image. `messages` has `min:1` but no `max`. A crafted request can send many large multimodal messages in one POST (DoS pressure on PHP JSON + upstream).
- **Fix A ⭐ Recommended**: Add `messages` max (e.g. 100–200) and optional total serialized-size cap in FormRequest `after()`.
  - Strength: Cheap guardrail; preserves single-image UX.
  - Tradeoff: Arbitrary history length limit to document.
  - Confidence: HIGH.
  - Blind spot: Exact max needed for long threads with many past images.
- **Fix B**: Rely on existing chat throttle / infra limits only.
  - Strength: No code change.
  - Tradeoff: Weaker app-level protection.
  - Confidence: LOW — throttle may not cover body size.
  - Blind spot: Current throttle config on `chat.stream`.
- **Decision**: FIXED — applied recommended fix in follow-up commit

### F3 — request_payload image sanitization is client-only

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/utils/contentParts.js; PromptController.php
- **Detail**: Plan required sanitizing stored `request_payload`. UI/account store strips `image_url` before POST; server only enforces 100KB JSON. A non-sanitizing client can still store small image parts in `request_payload` under the byte cap.
- **Fix**: Mirror `sanitizeRequestPayloadForStorage` in PHP on Store/Update prompt before persist.
  - Strength: Defense in depth; matches MessageContent server validation pattern.
  - Tradeoff: Small PHP helper + tests.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED — applied recommended fix in follow-up commit

### F4 — Vision error mapping may rewrite unrelated failures

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/utils/visionStreamError.js:17-35
- **Detail**: Needles like `does not support` / bare `image` can remap generic upstream errors when the failure was unrelated to vision.
- **Fix**: Require image/vision/`image_url` **and** support/multimodal language, or only map when the outbound request included an image part. Add negative JS tests.
  - Strength: Keeps friendly copy for real vision rejects.
  - Tradeoff: Slightly more heuristic complexity.
  - Confidence: HIGH.
  - Blind spot: Diversity of LM Studio error strings.
- **Decision**: FIXED — applied recommended fix in follow-up commit

### F5 — Scroll-to-end on every token (+ unplanned open scroll)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/Components/Chat/MessageThread.vue:45-86
- **Detail**: Mid-impl user ask added scroll-to-end on conversation open (EXTRA vs plan). Watch also includes last message `content`/`reasoning`, so every streamed token calls `scrollIntoView` — can thrash on long replies.
- **Fix A ⭐ Recommended**: Keep open-scroll (document as addendum); throttle token scrolls with rAF / only when near bottom.
  - Strength: Preserves requested UX; reduces jank.
  - Tradeoff: Slightly more scroll logic.
  - Confidence: HIGH.
  - Blind spot: Prefer always-stick vs near-bottom only.
- **Fix B**: Document EXTRA only; leave per-token scroll as-is.
  - Strength: Minimal change.
  - Tradeoff: Possible scroll jank on long streams.
  - Confidence: MEDIUM.
  - Blind spot: Real device performance with images.
- **Decision**: FIXED — applied recommended fix in follow-up commit

### F6 — Progress still open for guest / non-vision manuals

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: plan.md Progress (2.4, 3.4, 4.3, 4.5)
- **Detail**: Focused automated suite green locally (no pdo_sqlite for RefreshDatabase Feature tests). Guest isolation / guest attach / non-vision error manuals remain unchecked.
- **Fix**: Stamp after CI/Sail run + quick guest/non-vision smoke.
- **Decision**: SKIPPED — deferred (manual stamp / known MVP tradeoff)

### F7 — Huge data-URLs round-trip in Inertia history (known MVP tradeoff)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: AccountChatPresenter; prompts.content JSON parts
- **Detail**: Plan chose base64 parts in `content` (3A/7B). Multi-image histories will bloat props and replay bodies — accepted for single-user MVP; out-of-band storage was explicitly out of scope.
- **Fix**: Follow-up change for disk/object storage if threads grow heavy; no action for this plan.
- **Decision**: SKIPPED — deferred (manual stamp / known MVP tradeoff)
