---
change_id: guest-first-chat
title: Pierwsza rozmowa gościa
status: implementing
created: 2026-08-04
updated: 2026-08-04
archived_at: null
---

## Notes

Roadmap S-01 (gwiazda przewodnia). Decyzje z `/10x-plan`: `/` = czat gościa; multi-conv + model picker; proxy dowolnego URL; SSE passthrough; localStorage + TTL 1 dzień; params temperature/max_tokens/top_p; partial on error; bez API key (LM Studio); ChatLayout + link do profilu; Feature Http::fake + unit compose; przy ciasnym czasie degraduj picker do ręcznego model id.

**Faza 4 zakończona** (UI stream, thinking accordion, stats, toasty, Stop, anti-spam new chat). Pozostaje Faza 5: testy domykające + hardening deploy (Caddy/timeout).
