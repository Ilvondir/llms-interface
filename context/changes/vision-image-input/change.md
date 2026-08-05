---
change_id: vision-image-input
title: Vision image input
status: impl_reviewed
created: 2026-08-05
updated: 2026-08-05
archived_at: null
---

## Notes

Planning decisions (2026-08-05):
- No vision-model detection — always show attach for authenticated users; map upstream errors.
- Guest out of scope for images (PRD Access Control override).
- Persist OpenAI-style JSON parts (text + image_url data-URL) in `prompts.content`; sanitize `request_payload` to omit image bytes.
- Client canvas compress (1 image, ~4MB source); no new npm image libs; no Playwright.

Implementation notes:
- Phase 1–3 delivered multimodal stream contract, account persistence, and auth-only attach UI.
- `structuredClone` on Vue proxies broke assistant persist — fixed via JSON round-trip sanitize.
- Phase 4 maps vision-ish upstream failures to a friendly toast; user image turn is kept.
- EXTRA: MessageThread scrolls to end on conversation open (user request).
- Shipped: `1515110` / `0d4750b` on master.
