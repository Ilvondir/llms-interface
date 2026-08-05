<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Trwałość rozmów na koncie

- **Plan**: `context/changes/account-conversation-persistence/plan.md`
- **Scope**: Phases 1–4 of 4 (full plan; Progress still has Phase 4 manual 4.4–4.5 open)
- **Date**: 2026-08-05
- **Verdict**: NEEDS ATTENTION → fixes applied 2026-08-05
- **Findings**: 1 critical / 6 warnings / 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Stale debounced JSON can clobber active conversation

- **Severity**: ❌ CRITICAL
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Safety & Quality
- **Location**: `resources/js/composables/useAccountChatStore.js` (flushConversationPatch / flushSettingsPatch / syncAfterJsonMutation)
- **Detail**: In-flight PATCH started on conversation A can finish after Inertia switch to B and overwrite local id / server `active_conversation_id`. Debounce also does not flush-before-navigate, so edits can be dropped or applied to the wrong thread.
- **Fix A ⭐ Recommended**: Add mutation epoch / capture conversation id at schedule; ignore stale responses; flush-or-cancel before select/create/delete.
  - Strength: Fixes real data-corruption class without abandoning fetch UX that solved typing remounts.
  - Tradeoff: Non-trivial store logic; needs a focused regression test.
  - Confidence: HIGH — classic stale-response bug with clear call sites.
  - Blind spot: Have not reproduced multi-tab race in browser.
- **Fix B**: Drop debounce; persist only on blur/explicit commit for all fields.
  - Strength: Simpler lifecycle; fewer in-flight races.
  - Tradeoff: Worse UX for params sliders; still need abort on navigate for in-flight fetch.
  - Confidence: MEDIUM — may reintroduce some of the “reload while typing” pain if blur is mis-wired.
  - Blind spot: Param controls currently fire on every input.
- **Decision**: FIXED via Fix A — mutationGeneration + scheduledForId guards; prepareNavigation flush before select/create/delete; syncAfterJsonMutation ignores mismatched conversation ids.

### F2 — CRUD transport drifted from Inertia-only (plan 5A)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: `useAccountChatStore.js`; `ConversationController` / `PromptController` / `ChatSettingsController` `wantsJson()` branches
- **Detail**: Plan decision 5A and Phase 3 contract specified Inertia mutations. Implementation intentionally switched field/prompt writes to `fetch` + JSON dual responses after Inertia remounts broke typing. Navigation (select/create/delete) still uses Inertia.
- **Fix A ⭐ Recommended**: Document as plan addendum (hybrid: Inertia nav + fetch field/prompt mutations) — preserves working UX.
  - Strength: Matches what shipped and why (user-reported remount loop).
  - Tradeoff: Plan becomes slightly moving target.
  - Confidence: HIGH — change was reactive and validated manually.
  - Blind spot: Stakeholders who signed 5A literally may disagree.
- **Fix B**: Revert field mutations to Inertia-only with careful `only`/`preserveState`.
  - Strength: Restores literal plan adherence.
  - Tradeoff: High risk of reintroducing focus/remount bugs.
  - Confidence: LOW — already failed once in this change.
  - Blind spot: Exact Inertia options that would be safe not proven.
- **Decision**: FIXED via Fix A — plan addendum (2026-08-05) documents hybrid transport; "NOT doing" updated.

### F3 — Guest localStorage still initialized while authenticated

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: `resources/js/Pages/Chat/Index.vue` (always `useGuestChatStore()`); `useGuestChatStore.js` init read/write
- **Detail**: Plan said authenticated path must not read/write `llms.guest.v1`. Index always constructs the guest store singleton, which reads and writes storage on first init even when UI uses account store.
- **Fix**: Lazily create guest store only when `!auth.user` (or no-op guest factory when auth).
- **Decision**: FIXED — Index constructs only the store for the current auth mode.

### F4 — Debounced persist errors are swallowed

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: `useAccountChatStore.js` (`flush*.catch(() => {})`)
- **Detail**: Failed CSRF/network/422 saves leave optimistic UI with no toast; stream path surfaces errors.
- **Fix**: Toast on failure; keep dirty flag / retry on next blur or navigation.
- **Decision**: FIXED — toast.error on persist failures; also fixed `bootstrap/app.php` shouldRenderJsonWhen to include expectsJson so fetch gets 422 JSON (was HTML-only for non-api routes).

### F5 — `active_conversation_id` existence oracle

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: `app/Http/Requests/Chat/UpdateChatSettingsRequest.php:25`
- **Detail**: Global `exists:conversations,id` yields 422 for missing ids vs 404 for other-user ids after `whereBelongsTo` — distinguishable oracle. Low practical risk (solo product) but easy to close.
- **Fix**: Scope exists rule to `user_id = auth id`.
- **Decision**: FIXED — Rule::exists scoped to authenticated user; Feature test added.

### F6 — Unbounded prompt text/JSON fields

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: `StorePromptRequest` / `UpdatePromptRequest`
- **Detail**: `content`/`reasoning`/`request_payload`/`stats` lack max size while `system_prompt` is capped at 100k. Authenticated clients can inflate DB and every subsequent props reload.
- **Fix**: Add `max` on strings and constrain JSON payload size aligned with stream limits.
- **Decision**: FIXED — max 100k on content/reasoning, 10k on error, 100k JSON bytes on stats/request_payload; Feature test for oversized content.

### F7 — Phase 4 not closed (manual open + uncommitted isolation test)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: `plan.md` Progress 4.4–4.5; untracked `tests/Feature/Chat/AccountGuestIsolationTest.php`
- **Detail**: Automated Chat suite passes (27) including isolation tests, but Progress still has manual 4.4–4.5 unchecked and Phase 4 commit/epilogue not done. Review ran mid-closeout.
- **Fix**: Confirm smoke 4.4/4.5, commit Phase 4 + epilogue, flip `change.md` to `implemented` then archive when ready.
- **Decision**: ACCEPTED — code/tests for review fixes landed uncommitted; manuals 4.4/4.5 and Phase 4 commit still need your confirmation (not auto-flipped).

### F8 — Prompt cross-user 403 not explicitly tested

- **Severity**: 👀 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: `tests/Feature/Chat/AccountConversationOwnershipTest.php`
- **Detail**: Conversation ownership 403 covered; prompt store/update as another user relies on FormRequest policy without dedicated assertion.
- **Fix**: Add Feature asserts for prompt store/update as intruder → 403.
- **Decision**: FIXED — added `user_cannot_store_or_update_prompts_on_another_users_conversation`.

### F9 — Full `props()` returned on every field PATCH

- **Severity**: 👀 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: `AccountChatPresenter::props` callers in update controllers
- **Detail**: Every JSON field patch reloads full active thread + conversation list. Fine for MVP volumes; chatty for long threads with 400ms debounce.
- **Fix**: Slim JSON for settings/param patches; full thread only after prompt mutations.
- **Decision**: FIXED — `fieldMutationProps()` / `includeMessages: false` on conversation + settings JSON updates; prompt mutations still return full props; Feature assert omits messages.
