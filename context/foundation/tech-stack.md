---
starter_id: laravel
package_manager: composer
project_name: llms-interface
hints:
  language_family: php
  team_size: solo
  deployment_target: self-host
  ci_provider: github-actions
  ci_default_flow: auto-deploy-on-merge
  bootstrapper_confidence: verified
  path_taken: standard
  quality_override: false
  self_check_answers: null
  has_auth: true
  has_payments: false
  has_realtime: false
  has_ai: true
  has_background_jobs: false
---

## Why this stack

LLMsInterface is a solo, after-hours web app that needs account auth, durable conversations, and a server-composed chat proxy to an external model API with continuous reply progress. Laravel is the recommended default for `(web, php)` and is already the base of this repo; Inertia + Vue + SSR is the intended UI layer on top (not a separate registry starter). Deployment and CI already exist (self-host via Coolify, GitHub Actions with auto-deploy on merge), so those were locked from the current setup rather than re-litigated. Auth and AI feature flags are on; payments, classic realtime, and background jobs stay out of MVP scope.
