---
project: "LLMsInterface"
context_type: greenfield
created: 2026-08-04
updated: 2026-08-04
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  mvp_weeks: null
  hard_deadline: null
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "context_type"
      decision: "greenfield — użytkownik: tylko szablon Jetstream Inertia Vue, budowa produktu od zera"
    - topic: "primary_persona"
      decision: "tylko ja (właściciel)"
    - topic: "facilitation_mode"
      decision: "bez pytań sokratejskich — prywatny projekt poza kursem, szybkie przechwycenie"
    - topic: "auth strategy"
      decision: "e-mail+hasło (Jetstream); płaski model kont; gość czatuje w pełni, zapis tylko localStorage; zalogowany → DB"
    - topic: "mvp_flow"
      decision: "przepływ MVP złożony z pomysłu początkowego; użytkownik pominął osobne szkicowanie sesji"
    - topic: "frs"
      decision: "FR-001..010 zaakceptowane; bez budżetu czasu (mvp_weeks nieustalone)"
    - topic: "socrates_round"
      decision: "pominięta na życzenie użytkownika"
    - topic: "domain_rule"
      decision: "historia bez reasoningu → OpenAI-compatible API → rozdział odpowiedzi/reasoning/stats/parametrów; DB vs localStorage"
    - topic: "nfr_streaming"
      decision: "widoczny postęp przy długiej generacji; dopuszczalny streaming kolejnych tokenów"
    - topic: "product_frame"
      decision: "web-app; scale small (tylko ja); bez terminu; po godzinach; wyklucz RAG/team/mobile"
    - topic: "quality_override"
      decision: "zaakceptowano lukę mvp_weeks; project=LLMsInterface"
  frs_drafted: 10
  quality_check_status: warned
---

## Pomysł początkowy (dosłownie)

Dobra chcę zbudować nakładkę webową na LLM hostowane za pomocą ngroka z mojego prwyatnego komputera z LMStudio.

Chcę, żeby przypominał on interfejs chatgpt/gemini z tym, że na pnaleu po lewej na samej górez ma być pole do wpisania URL do OpenAI Compatibile API modelu- niżej panel do dostrajania modelu trzeba się rozeznać jakie parametry mogą przyjąć modele przez API LMStudio- niżej rozmowy, zapisywane w bazie modele Conversation i składowe Prompts- cchę żeby baza zapisywała oddielnie odpoiedź oraz proces reasoningu jeśli takowy będzie, teraz uwaga- jest dostępny panel logowania i te rozmowy w bazie się mają zapiyswać tylko dla zalogowanych ludzi- jeśli są niezalogowani to co najwyżej w localStoragu zapis- stack to Laravel + Inertia + Vue- chcę żeby rozmowa pozwalała na wysyłanie zdjęć jeśli model jest wizyjny, nie wiem czy da się to sprawdzić w LMStudio- po każdej odpowiedzi z modeu chcę mieć widoczne jej statystyki, które odsyła LMStudio typu:
"stats": {
    "input_tokens": 28,
    "total_output_tokens": 1596,
    "reasoning_output_tokens": 1549,
    "tokens_per_second": 37.82429623761299,
    "time_to_first_token_seconds": 0.454
  }


I ogólnie zapytnia do LMStudio będzie wysyłał backend, bo on będzie komponował historię konwersacji-będzie wysyłał poprzednie wiadomości ale bez reasoningu.

Cchcę żeby była możliwość modyfikowania system promptu w rozmowie stale- wiadomo parametry modelu i system prompt mogą być zapisywanie  wmodelu Conversation jako te, które obecnie używa model i też niech będzie dla promptu zapisanego w bazie informacja z jakimi został on odpytany

## Wizja i Oświadczenie o Problemie

Ból: brak wygodnej, ChatGPT/Gemini-podobnej nakładki webowej na lokalny LLM w LM Studio (OpenAI-compatible API przez ngrok), z rozmowami, parametrami modelu, system promptem, statsami odpowiedzi i opcjonalnymi obrazami.

Osoba / moment: ja — gdy korzystam z lokalnego modelu i chcę czatu webowego zamiast ograniczać się do UI LM Studio.

Koszt dziś: wykonanie aplikacji.

Wgląd na przyszłość (poza MVP): możliwość rozwoju w kierunku własnego RAG na Qdrant.

## Użytkownik i Persona

Główna persona: ja (właściciel prywatnego hosta LM Studio / tej aplikacji). Jedyny nazwany użytkownik MVP.

## Kontrola Dostępu

- Logowanie: e-mail + hasło (Jetstream).
- Model kont: płaski — bez ról admin/member.
- Zalogowany: rozmowy i prompty w bazie (Conversation / Prompts).
- Gość (niezalogowany): pełny czat (URL API, parametry, system prompt, wizja, stats); trwałość tylko w localStorage, bez zapisu w DB.

## Kryteria Sukcesu

### Podstawowe
- Działa przepływ: UI jak ChatGPT/Gemini → wpisuję URL OpenAI-compatible API → ustawiam parametry modelu i system prompt → prowadzę rozmowę → backend składa historię bez reasoningu i woła LM Studio → widzę odpowiedź (z reasoning osobno, jeśli jest) oraz stats z LM Studio → zalogowany zapis w DB (Conversation + Prompts z parametrami użytego odpytania); gość w localStorage.

### Dodatkowe
- Wysyłanie zdjęć, gdy model jest wizyjny (jeśli da się to sensownie obsłużyć względem LM Studio).

### Bariery ochronne
- Reasoning nie wraca do historii wysyłanej do modelu.
- Rozmowy gościa nie lądują w bazie serwera.
- RAG / Qdrant poza MVP (tylko możliwość rozwoju później).

## Wymagania Funkcjonalne

### Konfiguracja i czat
- FR-001: Użytkownik może wpisać URL OpenAI-compatible API. Priorytet: musi-być
- FR-002: Użytkownik może dostrajać parametry modelu w panelu. Priorytet: musi-być
- FR-003: Użytkownik może edytować system prompt w rozmowie. Priorytet: musi-być
- FR-004: Użytkownik może prowadzić rozmowę w UI jak ChatGPT/Gemini. Priorytet: musi-być

### Proxy i treść odpowiedzi
- FR-005: System wysyła do modelu historię bez reasoningu (backend komponuje). Priorytet: musi-być
- FR-006: System zapisuje odpowiedź i reasoning osobno (gdy jest). Priorytet: musi-być
- FR-007: System pokazuje stats odpowiedzi (input/output/reasoning tokens, t/s, TTFT). Priorytet: musi-być

### Trwałość
- FR-008: Zalogowany ma rozmowy w DB (Conversation + Prompts + parametry odpytania). Priorytet: musi-być
- FR-009: Gość ma trwałość tylko w localStorage. Priorytet: musi-być

### Wizja
- FR-010: Użytkownik może wysłać zdjęcie, jeśli model wizyjny. Priorytet: miło-mieć

## Historie Użytkowników

### US-01: Pierwsza rozmowa z lokalnym modelem

- **Given** otwarta aplikacja i działający endpoint OpenAI-compatible (np. LM Studio przez ngrok)
- **When** użytkownik poda URL API, ustawi parametry/system prompt i wyśle wiadomość
- **Then** widzi odpowiedź (z reasoning osobno, jeśli jest) oraz stats; backend wysłał historię bez reasoningu; przy logowaniu zapis w DB z parametrami odpytania, jako gość — localStorage

#### Acceptance Criteria
- Reasoning nie jest dołączany do kolejnych requestów historii
- Stats z odpowiedzi modelu są widoczne po odpowiedzi
- Gość nie tworzy rekordów Conversation/Prompts w bazie

## Logika Biznesowa

Aplikacja komponuje historię rozmowy bez reasoningu, odpytuje podany OpenAI-compatible endpoint bieżącymi parametrami i system promptem, a potem rozdziela i utrwala treść odpowiedzi, reasoning (jeśli jest), stats oraz parametry użytego odpytania — w DB dla zalogowanego, w localStorage dla gościa.

Wejścia użytkownika: URL API, parametry modelu, system prompt, treść wiadomości (opcjonalnie obraz), kontekst wcześniejszych tur bez reasoningu.  
Wynik: streamowana lub finalna odpowiedź z osobnym reasoningiem (gdy jest), widocznymi statsami i trwałym zapisem według stanu logowania.

## Wymagania Niefunkcjonalne

- Widoczny postęp przy długiej generacji; dopuszczalny efekt streamowania kolejnych tokenów z modelu.
- Gość: brak zapisu rozmów na serwerze (tylko localStorage).
- Reasoning nie jest reincludowany w historii wysyłanej do modelu.

## Cele Niezwiązane z Projektem

- Unikaj: RAG / Qdrant w MVP — rozwój później, nie blokuje pierwszej wersji czatu.
- Unikaj: team / multi-tenant / role zespołowe — produkt jest dla jednego właściciela.
- Unikaj: aplikacja mobilna — MVP to web.

## Otwarte Pytania

1. Budżet czasu MVP (`mvp_weeks`) — użytkownik świadomie pominął.
2. Dokładna lista parametrów LM Studio API do panelu dostrajania — do rozeznania.
3. Czy/jak wykrywać model wizyjny w LM Studio — nieustalone.

## Kontrola jakości

- Potwierdzenie kosztów harmonogramu: brakujące — brak `mvp_weeks` i braku bloku potwierdzenia; użytkownik zaakceptował override. Konsekwencja: PRD bez twardego budżetu tygodni; luka w `## Otwarte Pytania`.

## Dalej: stos technologiczny

Użytkownik wskazał preferencję: Laravel + Inertia + Vue (szablon Jetstream już w repo). Decyzja stosu formalnie po `/10x-prd`.
