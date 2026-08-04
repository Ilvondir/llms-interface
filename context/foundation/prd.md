---
project: "LLMsInterface"
version: 1
status: draft
created: 2026-08-04
context_type: greenfield
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  mvp_weeks: null
  hard_deadline: null
  after_hours_only: true
---

## Vision & Problem Statement

Brak wygodnej, mainstreamowo-czatowej nakładki webowej na lokalnie hostowany model językowy: z rozmowami, parametrami modelu, system promptem, statystykami odpowiedzi i opcjonalnymi obrazami. Osoba i moment: właściciel prywatnego hosta modelu — gdy chce czatu webowego zamiast ograniczać się do natywnego UI hosta. Koszt dziś: wykonanie aplikacji.

Insight: wartość leży w kompozycji historii bez reasoningu, trwałym rozdziale odpowiedzi/reasoningu/statsów oraz dualnej trwałości (konto vs gość) — z możliwością późniejszego rozwoju w kierunku własnego wyszukiwania wspomaganego bazą wiedzy, poza MVP.

## User & Persona

Główna persona: ja (właściciel prywatnego hosta modelu / tej aplikacji). Jedyny nazwany użytkownik MVP. Sięga po produkt, gdy chce prowadzić rozmowę z lokalnym modelem przez UI webowe z panelami konfiguracji i historią.

## Success Criteria

### Primary
- Działa przepływ: UI jak mainstreamowy asystent czatu → użytkownik podaje URL API modelu → ustawia parametry modelu i system prompt → prowadzi rozmowę → aplikacja składa historię bez reasoningu i odpytuje endpoint → użytkownik widzi odpowiedź (z reasoning osobno, jeśli jest) oraz stats → zalogowany ma zapis w magazynie konta (Conversation + Prompts z parametrami użytego odpytania); gość — tylko lokalnie w przeglądarce.

### Secondary
- Wysyłanie zdjęć, gdy model jest wizyjny (jeśli da się to sensownie obsłużyć względem używanego endpointu).

### Guardrails
- Reasoning nie wraca do historii wysyłanej do modelu.
- Rozmowy gościa nie lądują w magazynie serwerowym.
- Wyszukiwanie wspomagane własną bazą wiedzy poza MVP (tylko możliwość rozwoju później).

## User Stories

### US-01: Pierwsza rozmowa z lokalnym modelem

- **Given** otwarta aplikacja i działający zewnętrzny endpoint API modelu czatu (osiągalny z aplikacji, np. przez tunel do hosta lokalnego)
- **When** użytkownik poda URL API, ustawi parametry/system prompt i wyśle wiadomość
- **Then** widzi odpowiedź (z reasoning osobno, jeśli jest) oraz stats; historia wysłana do modelu nie zawiera reasoningu; przy logowaniu zapis w magazynie konta z parametrami odpytania, jako gość — tylko lokalnie w przeglądarce

#### Acceptance Criteria
- Reasoning nie jest dołączany do kolejnych requestów historii
- Stats z odpowiedzi modelu są widoczne po odpowiedzi
- Gość nie tworzy rekordów Conversation/Prompts w magazynie serwerowym

## Functional Requirements

### Konfiguracja i czat
- FR-001: Użytkownik może wpisać URL API modelu czatu (przyjmującego historię wiadomości). Priority: must-have
- FR-002: Użytkownik może dostrajać parametry modelu w panelu. Priority: must-have
- FR-003: Użytkownik może edytować system prompt w rozmowie. Priority: must-have
- FR-004: Użytkownik może prowadzić rozmowę w UI wzorowanym na mainstreamowych asystentach czatu. Priority: must-have

### Zapytanie i treść odpowiedzi
- FR-005: System wysyła do modelu historię bez reasoningu (aplikacja komponuje historię przed odpytaniem). Priority: must-have
- FR-006: System zapisuje odpowiedź i reasoning osobno (gdy jest). Priority: must-have
- FR-007: System pokazuje stats odpowiedzi (input/output/reasoning tokens, tokens/s, time-to-first-token). Priority: must-have

### Trwałość
- FR-008: Zalogowany ma rozmowy w magazynie konta (Conversation + Prompts + parametry odpytania). Priority: must-have
- FR-009: Gość ma trwałość tylko lokalnie w przeglądarce. Priority: must-have

### Wizja
- FR-010: Użytkownik może wysłać zdjęcie, jeśli model wizyjny. Priority: nice-to-have

## Non-Functional Requirements

- Widoczny ciągły postęp przy długiej generacji odpowiedzi — użytkownik widzi napływającą treść, zanim odpowiedź się domknie.
- Gość: brak zapisu rozmów w magazynie serwerowym (tylko lokalnie w przeglądarce).
- Reasoning nie jest reincludowany w historii wysyłanej do modelu.

## Business Logic

Aplikacja komponuje historię rozmowy bez reasoningu, odpytuje podany endpoint API modelu bieżącymi parametrami i system promptem, a potem rozdziela i utrwala treść odpowiedzi, reasoning (jeśli jest), stats oraz parametry użytego odpytania — w magazynie konta dla zalogowanego, lokalnie w przeglądarce dla gościa.

Wejścia użytkownika: URL API, parametry modelu, system prompt, treść wiadomości (opcjonalnie obraz), kontekst wcześniejszych tur bez reasoningu.  
Wynik: odpowiedź z widocznym postępem podczas generacji, osobnym reasoningiem (gdy jest), widocznymi statsami i trwałym zapisem według stanu logowania.

## Access Control

- Logowanie: e-mail + hasło.
- Model kont: płaski — bez ról admin/member.
- Zalogowany: rozmowy i prompty w magazynie konta (Conversation / Prompts).
- Gość (niezalogowany): pełny czat (URL API, parametry, system prompt, wizja, stats); trwałość tylko lokalnie w przeglądarce, bez zapisu serwerowego.

## Non-Goals

- Unikaj: wyszukiwanie wspomagane własną bazą wiedzy (RAG) w MVP — rozwój później, nie blokuje pierwszej wersji czatu.
- Unikaj: team / multi-tenant / role zespołowe — produkt jest dla jednego właściciela.
- Unikaj: aplikacja mobilna — MVP to web.

## Open Questions

1. **Budżet czasu MVP (`timeline_budget.mvp_weeks`)** — użytkownik świadomie pominął; frontmatter ma `null`. Owner: user. Block: soft (PRD draft bez twardego budżetu tygodni).
2. **Dokładna lista parametrów API modelu do panelu dostrajania** — do rozeznania względem używanego hosta modelu. Owner: user.
3. **Czy/jak wykrywać model wizyjny** względem używanego endpointu — nieustalone. Owner: user.
4. **Potwierdzenie kosztów harmonogramu** — brak `mvp_weeks` i braku bloku potwierdzenia; użytkownik zaakceptował override w shape-notes. Konsekwencja: brak twardego budżetu tygodni w PRD.
