---
project: "LLMsInterface"
version: 1
status: draft
created: 2026-08-04
updated: 2026-08-04
prd_version: 1
main_goal: low-complexity
top_blocker: decisions
---

# Mapa drogowa: LLMsInterface

> Wygenerowano z `context/foundation/prd.md` (v1) + automatycznie zbadana baza kodu.
> Edytuj na miejscu; archiwizuj po zastąpieniu.
> Fragmenty poniżej są wymienione w kolejności zależności. Tabela „W skrócie” to indeks.

## Podsumowanie wizji

Brak wygodnej, mainstreamowo-czatowej nakładki webowej na lokalnie hostowany model: rozmowy, parametry, system prompt, stats i opcjonalne obrazy. Wartość MVP leży w kompozycji historii bez reasoningu, rozdziale odpowiedzi/reasoningu/statsów oraz dualnej trwałości (konto vs gość). Produkt jest dla jednego właściciela prywatnego hosta modelu.

## Gwiazda przewodnia

**S-01: Pierwsza rozmowa gościa** — najmniejszy pełny przepływ, który udowadnia Primary Success bez magazynu konta.

> Gwiazda przewodnia — najmniejszy, kompleksowy fragment, którego pomyślne dostarczenie udowodniłoby podstawową hipotezę produktu — umieszczony tak wcześnie, jak pozwalają wymagania wstępne, bo reszta ma sens dopiero gdy to działa.

## W skrócie

| ID | Change ID | Wynik (użytkownik może…) | Wymagania wstępne | Odwołania do PRD | Status |
|---|---|---|---|---|---|
| S-01 | guest-first-chat | jako gość podać URL API, ustawić parametry/system prompt, prowadzić czat i zobaczyć odpowiedź z osobnym reasoningiem, statsami oraz postępem; trwałość tylko lokalnie; historia do modelu bez reasoningu | — | US-01, FR-001, FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-009 | done |
| S-02 | account-conversation-persistence | po zalogowaniu mieć rozmowy i prompty w magazynie konta z parametrami użytego odpytania | S-01 | US-01, FR-008 | proponowany |
| S-03 | vision-image-input | wysłać zdjęcie, gdy model jest wizyjny | S-01 | FR-010 | zablokowany |

## Strumienie

Pomoc nawigacyjna — grupuje elementy, które dzielą łańcuch wymagań wstępnych. Kanoniczna kolejność nadal znajduje się w grafie zależności poniżej; ta tabela to proponowana kolejność czytania w równoległych ścieżkach.

| Strumień | Temat | Łańcuch | Uwaga |
|---|---|---|---|
| A | Czat gościa → konto | `S-01` → `S-02` | Ścieżka must-have pod `low-complexity`: najpierw walidacja czatu, potem trwałość konta. |
| B | Wizja (nice-to-have) | `S-03` | Po `S-01`, równolegle z `S-02`; zablokowany do decyzji o wykrywaniu modelu wizyjnego. |

## Baza

Co już jest na miejscu w bazie kodu na dzień `2026-08-04` (automatycznie zbadane + potwierdzone przez użytkownika).
Fundamenty poniżej zakładają, że te elementy są obecne i NIE tworzą ich ponownie.

- **Frontend:** częściowy — Vite + Vue 3 + Inertia + Tailwind (Jetstream); brak UI czatu / URL API (`resources/js/Pages/*`, `vite.config.js`)
- **Backend / API:** częściowy — Laravel 13 + trasy Jetstream; brak proxy LLM / Conversation / Prompts (`routes/web.php`, `app/Http/Controllers/Controller.php`)
- **Dane:** częściowy — Eloquent + migracje auth; brak Conversation/Prompt (`database/migrations/`, `app/Models/User.php`)
- **Uwierzytelnianie:** obecny — Fortify / Jetstream / Sanctum + Login (`resources/js/Pages/Auth/Login.vue`, `routes/web.php`)
- **Wdrożenie / infrastruktura:** obecny — Dockerfile, production-compose, CI → Coolify (`.github/workflows/ci.yml`, `production-compose.yaml`)
- **Obserwowalność:** nieobecny — tylko logi Laravel; brak Sentry/OTel

## Fundamenty

Brak. Auth i wdrożenie są obecne w bazie; domena czatu wchodzi pionowo w fragmentach (progresywne ujawnianie). Obserwowalność nie jest wymagana przez PRD do uruchomienia MVP.

## Fragmenty

### S-01: Pierwsza rozmowa gościa

- **Wynik:** użytkownik jako gość może podać URL API modelu, ustawić parametry i system prompt, prowadzić rozmowę w UI czatu, widzieć napływającą odpowiedź z osobnym reasoningiem (gdy jest) oraz statsami; aplikacja składa historię bez reasoningu; trwałość tylko lokalnie w przeglądarce.
- **Change ID:** guest-first-chat
- **Odwołania do PRD:** US-01, FR-001, FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-009; NFR: widoczny postęp generacji; guardrail: reasoning poza historią modelu; guardrail: gość bez zapisu serwerowego
- **Wymagania wstępne:** —
- **Równolegle z:** —
- **Blokady:** —
- **Niewiadome:**
  - Dokładna lista parametrów API hosta modelu do panelu — Właściciel: user. Blokada: nie (plan przyjmuje cienki, wspólny podzbiór parametrów zgodnych z API czatu; pełna lista zostaje otwartym pytaniem roadmapy).
- **Ryzyko:** tu jest klin produktu (kompozycja historii / reasoning / stats / gość lokalnie); odłożenie go za fundamentami UI lub schematu konta opóźni jedyną walidację Primary Success. Główne ryzyko wykonawcze: kontrakt odpowiedzi hosta modelu (reasoning + stats + stream).
- **Status:** done

### S-02: Trwałość rozmów na koncie

- **Wynik:** użytkownik zalogowany może mieć rozmowy i składowe prompty w magazynie konta wraz z parametrami użytego odpytania; gość nadal nie tworzy rekordów serwerowych.
- **Change ID:** account-conversation-persistence
- **Odwołania do PRD:** US-01, FR-008; Access Control (zalogowany → magazyn konta)
- **Wymagania wstępne:** S-01
- **Równolegle z:** S-03
- **Blokady:** —
- **Niewiadome:** —
- **Ryzyko:** auth już jest w bazie — ryzyko to poprawne rozdzielenie ścieżki gość vs konto, nie budowa logowania od zera. Sekwencjonowane po S-01, żeby nie mieszać walidacji czatu z modelem danych.
- **Status:** proponowany

### S-03: Wejście obrazu (model wizyjny)

- **Wynik:** użytkownik może wysłać zdjęcie w rozmowie, jeśli model jest wizyjny.
- **Change ID:** vision-image-input
- **Odwołania do PRD:** FR-010 (nice-to-have); Secondary Success
- **Wymagania wstępne:** S-01
- **Równolegle z:** S-02
- **Blokady:** —
- **Niewiadome:**
  - Czy/jak wykrywać model wizyjny względem używanego endpointu — Właściciel: user. Blokada: tak.
- **Ryzyko:** nice-to-have; planowanie przed decyzją o detekcji marnuje cykl. Parkowane operacyjnie jako zablokowane do rozwiązania niewiadomej.
- **Status:** zablokowany

## Przekazanie do backlogu

| ID mapy drogowej | Change ID | Sugerowany tytuł zadania | Gotowe do `/10x-plan` | Uwagi |
|---|---|---|---|---|
| S-01 | guest-first-chat | Pierwsza rozmowa gościa: URL, czat, reasoning/stats, local-only | tak | Gwiazda przewodnia — uruchom `/10x-plan guest-first-chat` |
| S-02 | account-conversation-persistence | Trwałość Conversation/Prompts dla zalogowanego | nie | Wymaga S-01 |
| S-03 | vision-image-input | Wysyłanie zdjęć przy modelu wizyjnym | nie | Zablokowany: detekcja modelu wizyjnego |

## Otwarte pytania dotyczące mapy drogowej

1. **Budżet czasu MVP (`timeline_budget.mvp_weeks`)** — Właściciel: user. Blokada: soft / roadmap-wide (brak twardego budżetu tygodni; nie blokuje planowania S-01).
2. **Dokładna lista parametrów API modelu do panelu dostrajania** — Właściciel: user. Blokada: S-01 (nie blokuje planu przy cienkim podzbiorze); wzbogacenie panelu później.
3. **Czy/jak wykrywać model wizyjny względem używanego endpointu** — Właściciel: user. Blokada: S-03.
4. **Potwierdzenie kosztów harmonogramu** — Właściciel: user. Blokada: soft / roadmap-wide (override z shape-notes zaakceptowany).

## Zaparkowane

- **Wyszukiwanie wspomagane własną bazą wiedzy (RAG)** — Dlaczego zaparkowane: PRD §Non-Goals; rozwój później, nie blokuje czatu.
- **Team / multi-tenant / role zespołowe** — Dlaczego zaparkowane: PRD §Non-Goals; produkt dla jednego właściciela.
- **Aplikacja mobilna** — Dlaczego zaparkowane: PRD §Non-Goals; MVP to web.

## Zrobione

- **S-01: użytkownik jako gość może podać URL API modelu, ustawić parametry i system prompt, prowadzić rozmowę w UI czatu, widzieć napływającą odpowiedź z osobnym reasoningiem (gdy jest) oraz statsami; aplikacja składa historię bez reasoningu; trwałość tylko lokalnie w przeglądarce.** — Archived 2026-08-04 → `context/archive/2026-08-04-guest-first-chat/`. Lesson: —.
