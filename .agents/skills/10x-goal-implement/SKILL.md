---
name: 10x-goal-implement
description: >
  Autonomously implement technical plans from context/changes/<change-id>/plan.md
  under your AI coding assistant's /goal — no human interaction at any point. Sibling of
  /10x-implement for unattended runs, in an interactive /goal session or headless
  via your AI coding assistant -p. Flips the plan's Automated Progress rows, verifies each phase
  through an automatic quality-gate stack (plan success criteria, deliberate-break
  check, full suite), commits each phase on green with Conventional Commits, and
  surfaces pending Manual rows as a closing human checklist. Use when the user
  wants autonomous or unattended plan execution, pairs /goal with a plan, asks to
  "run the plan under /goal", or needs headless implementation.
---

# Autonomiczne wdrażanie planu (w ramach /goal)

Masz za zadanie wdrożyć zatwierdzony plan techniczny z `context/changes/<change-id>/plan.md` bez interakcji z człowiekiem. Plany zawierają fazy ze specyficznymi zmianami oraz kanoniczną sekcję `## Progress` na dole, która steruje stanem wykonania (patrz `references/progress-format.md`). Ta umiejętność jest autonomicznym odpowiednikiem `/10x-implement`: dzieli te same kontrakty planu — rozwiązywanie planu, `## Progress` jako jedyne źródło prawdy, śledzenie zmienionych plików, protokół Conventional-Commits, cykl życia `change.md` — ale każda decyzja, którą podjąłby człowiek, jest zastąpiona jawną automatyczną polityką.

## Pozycjonowanie i wywołanie

Uruchom tę umiejętność w sesji `/goal`. Warunek celu to test zatrzymania na poziomie sesji; ta umiejętność jest polityką wykonania, która go spełnia.

- **Interaktywne**: najpierw ustaw cel, a następnie wywołaj umiejętność — `/goal <condition>`, a następnie `/10x-goal-implement <change-id> [phase N]`.
- **Bezobsługowe**: `your AI coding assistant -p "/goal <condition> /10x-goal-implement <change-id>" --allowedTools "Read,Glob,Grep,Write,Edit,Bash,Task,TaskCreate,TaskUpdate,TaskList,TaskGet" --permission-mode acceptEdits`.

Skopiuj i wklej szablon warunku `/goal` (uzupełnij `<change-id>` i limit tur `<N>`, zazwyczaj 20):

```
Use the 10x-goal-implement skill to implement all phases of
context/changes/<change-id>/plan.md. Done when: every row under
#### Automated in the plan's ## Progress section is checked, each
phase has its own Conventional-Commits commit, and the final output
lists any pending #### Manual rows. Constraints: do not modify or
weaken existing tests unless the plan says so; do not touch files
outside the plan's scope. Stop after <N> turns if not complete.
```

Ewaluator celu odczytuje **tylko transkrypcję rozmowy** — nie może uruchamiać poleceń ani czytać plików. Wszystko, co testuje warunek, musi być zatem opisane w tekście Twojej odpowiedzi: werdykty bram, SHA commitów, oczekujące wiersze Manual. Brama, która przeszła cicho, jest nie do odróżnienia od bramy, która nigdy nie została uruchomiona. Opisuj.

## Polityka braku interakcji

Nikt nie obserwuje przebiegu. Nigdy nie wywołuj interaktywnych narzędzi do zadawania pytań — nie ma nikogo, kto by odpowiedział, a w przypadku wywołania bezobsługowego wywołanie kończy się niepowodzeniem. Każda decyzja jest podejmowana zgodnie z politykami zawartymi w tym dokumencie. Gdy coś jest naprawdę niejednoznaczne i żadna z poniższych polityk tego nie rozwiązuje, wybierz konserwatywną interpretację, opisz wybór w tekście odpowiedzi i zapisz go w raporcie z przebiegu. Konserwatywne oznacza: odczyt, który dotyka mniej plików, zmienia mniej zachowań i pozostaje najbliżej dosłownego tekstu planu.

## Konfiguracja

Po wywołaniu tego polecenia:

1. **Rozwiąż plan**:
   - Jeśli wywołano jako `/10x-goal-implement <change-id> [phase N]`, rozwiąż do `context/changes/<change-id>/plan.md`.
   - Jeśli wywołano z `@context/changes/<change-id>/plan.md` lub pełną ścieżką, zaakceptuj.
   - **Odmów, jeśli rozwiązana ścieżka zaczyna się od `context/archive/`** — wydrukuj "This change is archived. Open a new change with `/10x-new` instead." i ZATRZYMAJ.
   - Jeśli nie podano planu lub rozwiązany plik nie istnieje, wydrukuj jeden wiersz — `Cannot start: no plan resolved from "<input>". Provide a change-id or plan path.` — i ZATRZYMAJ. Nie zgaduj change-id.

2. **Załaduj kontekst**:
   - Przeczytaj plan w całości. Sekcja `## Progress` na dole jest autorytatywna dla stanu wykonania — znaczniki wyboru (`- [x]`) znajdują się TYLKO tam. Bloki faz zawierają zwykłe punktorzy `- ` (bez pól wyboru).
   - Przeczytaj `context/foundation/lessons.md`, jeśli istnieje, i zinternalizuj każdy wpis przed rozpoczęciem jakiejkolwiek fazy — są to zaakceptowane powtarzające się zasady zespołu i muszą kształtować każdy wybór implementacyjny w tym przebiegu.
   - Przeczytaj wszystkie pliki wymienione w planie (odwołania do badań, ram, plików źródłowych w tym samym folderze zmian).
   - **Czytaj pliki w całości** — nigdy nie używaj parametrów limit/offset; potrzebujesz pełnego kontekstu.

3. **Wstępna weryfikacja bram**: zbierz polecenia z kryteriów sukcesu Automated każdej fazy i sprawdź, czy każde z nich jest możliwe do uruchomienia w tym środowisku (binarny lub skrypt pakietu istnieje — np. sprawdź skrypty `package.json`, `command -v`, cele `Makefile`). Kryterium, którego polecenie nie może zostać uruchomione, jest niezgodnością strukturalną dla fazy, która go potrzebuje: opisz brakujące polecenie teraz (`PREFLIGHT: <command> not runnable — Phase <N> will stop unless fixed`), a gdy wykonanie osiągnie tę fazę, wydrukuj blok STOP i zatrzymaj. Nie pomijaj cicho nieweryfikowalnego kryterium.

4. **Zaktualizuj `change.md`**: ustaw `status: implementing` (tylko jeśli obecnie w `{planned, plan_reviewed}`) i `updated: <today>`.

5. **Utwórz zadania faz**: policz całkowitą liczbę faz (z nagłówków `## Phase N:`) i utwórz jeden wpis TaskCreate dla każdej fazy (`subject: "Phase N: [Phase Name]"`, `activeForm: "Implementing Phase N"`). Ustaw bieżącą fazę `in_progress` za pomocą TaskUpdate przed rozpoczęciem pracy; oznacz ją jako `completed`, gdy jej bramy przejdą i jej commit zostanie zatwierdzony.

6. **Znajdź następny oczekujący krok**: przeskanuj sekcję `## Progress` w poszukiwaniu pierwszego wiersza `- [ ]` **w podsekcji `#### Automated`** w kolejności dokumentu — tam zaczynasz. Wiersze w `#### Manual` są poza Twoją jurysdykcją (patrz "Wiersze ręczne" poniżej); pomiń je podczas lokalizowania punktu wznowienia. Jeśli podano argument `phase N`, przejdź do pierwszego Automated `- [ ]` w `### Phase N:`.

## Taksonomia niezgodności

Plany są starannie projektowane, ale rzeczywistość może być skomplikowana. Gdy baza kodu nie odpowiada temu, co opisuje plan, sklasyfikuj niezgodność i działaj — nigdy nie edytuj bloków faz, aby dopasować plan.

**Drobna** — przeniesiony plik, zmieniona nazwa symbolu, dryf importu, trywialna różnica w API lub konfiguracji. Intencja planu jest nienaruszona; zmieniła się tylko jedna współrzędna. Dostosuj implementację do rzeczywistości, opisz adaptację w jednym lub dwóch wierszach (`ADAPT: plan says src/auth.ts, file is now src/auth/index.ts`) i uwzględnij ją w raporcie z przebiegu.

**Strukturalna** — brakująca zależność, architektura różniąca się od tej, którą zakłada plan, odwołanie do pliku lub API, które nie istnieje, faza, która zależy od danych wyjściowych, których poprzednia faza nigdy nie wyprodukowała. Plan nie może być przestrzegany w obecnej formie, a adaptacja oznaczałaby jego przeprojektowanie. Wydrukuj blok STOP i zatrzymaj.

W razie wątpliwości między tymi dwoma, traktuj to jako strukturalne. Błędne przypuszczenie, które zatrzymuje, kosztuje jedno wznowienie; błędne przypuszczenie, które adaptuje, może spowodować wysłanie przeprojektowania, którego nikt nie zatwierdził.

## Stos bram na fazę

Wdróż fazę w całości, a następnie uruchom tę stałą sekwencję — jedyną kanoniczną kolejność dla wszystkiego między "napisany kod" a "zatwierdzony commit". Bramy uruchamiają się od najtańszych; staging znajduje się tam, gdzie potrzebuje go kontrola przerwania; rytuał commitu jest ogonem. Po każdej bramie wydrukuj jednolinijkowy werdykt w tekście odpowiedzi — `GATE <name>: PASS` lub `GATE <name>: FAIL (<summary>, attempt <k>/2)` — aby ewaluator celu go zobaczył.

1. **(a) Kryteria planu** — uruchom polecenia kryteriów sukcesu `#### Automated` fazy z planu, w kolejności. Każde polecenie to jedna brama z własnym wierszem werdyktu.

2. **Przygotuj zestaw zmienionych plików** — `git add` każdy plik według ścieżki (definicja zestawu i obsługa brudnych ścieżek: patrz "Śledzenie plików zmienionych podczas fazy"). Przygotowanie _tutaj_, przed kontrolą przerwania, sprawia, że przywracanie kontroli przerwania jest dokładne.

3. **(b) Kontrola celowego przerwania** — tylko dla faz, które dodają lub zmieniają testy. Gdy pliki fazy są przygotowane, sprawdź, czy nowy lub zmieniony test faktycznie coś chroni:
   1. Odwróć lub osłab chronione zachowanie w kodzie produkcyjnym — edycja tylko w worktree, nigdy nie przygotowana.
   2. Uruchom odpowiedni test (ograniczone uruchomienie, np. pojedynczy plik testowy).
   3. Potwierdź, że się nie powiódł. Czerwony tutaj jest warunkiem przejścia: `GATE break-check: PASS (test went red on broken code)`.
   4. Przywróć bezwarunkowo za pomocą `git checkout -- <file>` — to resetuje worktree dokładnie do przygotowanej wersji, więc przerwanie nigdy nie może wyciec do commitu.
   5. Opisz sekwencję (co zostało zepsute, że test stał się czerwony, że plik został przywrócony).

   Jeśli test **pozostaje zielony** na zepsutym kodzie, asercja niczego nie chroni — to jest błąd bramy. Napraw to, wzmacniając asercję, nigdy nie osłabiając kodu produkcyjnego ani nie pomijając kontroli. Edycja przerwania nigdy nie może zostać zatwierdzona; przywracanie w kroku 4 jest bezwarunkowe, w tym na ścieżce błędu.

4. **(c) Sprawdzenia w całym repozytorium** — pełny zestaw testów, lint, typecheck, gdziekolwiek plan lub repozytorium je definiuje (np. skrypt `ci:local`, `make check test`). Jeden wiersz werdyktu dla każdego.

5. **(d) Commit** — niezmiennik commit-only-on-green: nigdy nie rozpoczynaj rytuału commitu, gdy którakolwiek z powyższych bram jest czerwona. Nie ma nadpisania. Jeśli samodzielna naprawa bramy (b)/(c) zmieniła pliki, uruchom ponownie krok 2, aby je przechwycić, a następnie uruchom rytuał Autonomous commit.

## Eskalacja samodzielnej naprawy

Brama, która się nie powiodła, otrzymuje maksymalnie **2** próby samodzielnej naprawy. Numeruj je w wierszach werdyktu (`attempt 1/2`, `attempt 2/2`). Jeśli ta sama brama nie powiedzie się po raz trzeci, problem jest głębszy niż mechaniczny dryf — wydrukuj blok STOP i zatrzymaj, zamiast spalać tury.

Granice tego, co może zrobić poprawka:

- Nigdy nie osłabiaj asercji, nie usuwaj testu ani nie rozluźniaj reguły lint/typecheck, aby brama przeszła, chyba że plan wyraźnie to mówi. Napraw kod, aby spełniał kontrolę, a nie kontrolę, aby spełniała kod.
- Gdy oczekiwana wartość testu jest niejednoznaczna — plan i implementacja są niezgodne i nie ma niezależnego źródła prawidłowej odpowiedzi — nie zgaduj. Oznacz krok jako niepewny w raporcie z przebiegu, pozostaw werdykt bramy uczciwy i pozwól, aby ścieżka STOP lub raport ujawniły to człowiekowi.

## Format bloku STOP

Blok STOP to widoczna dla człowieka powierzchnia błędu i sygnał, który ewaluator celu odczytuje jako "niezrobione". Wydrukuj go dokładnie w tej formie, a następnie zatrzymaj — bez dalszych edycji, bez commitu:

```
STOPPED — <STRUCTURAL MISMATCH | GATE FAILURE> in Phase <N>
Expected: <what the plan says / what the gate requires>
Found:    <actual situation / failing output summary>
Why:      <why this blocks autonomous continuation>
Resume:   fix the above, then /10x-goal-implement <change-id> phase <N>
```

Przed zatrzymaniem pozostaw drzewo robocze w uczciwym stanie: ukończone wiersze Progress pozostają odwrócone, praca w toku pozostaje w worktree niezacommitowana, a wszelkie celowe edycje przerwania są przywracane. Wznowienie nie wymaga dodatkowego stanu — pierwszy oczekujący wiersz Automated jest punktem ponownego wejścia.

## Śledzenie plików zmienionych podczas fazy

Rytuał commitu przygotowuje pliki z **zestawu zmienionych plików** utrzymywanego w pamięci roboczej przez całą fazę. Ten zestaw jest kanonicznym wejściem do `git add` — nigdy nie wracaj do heurystyk `git status` dla decyzji o przygotowaniu.

- Za każdym razem, gdy modyfikujesz plik lub piszesz nowy plik podczas bieżącej fazy, dodaj jego ścieżkę względną do repozytorium do zestawu.
- Zestaw zawsze zawiera `context/changes/<change-id>/plan.md` — dodaj go przy wejściu do fazy, zanim jakiekolwiek pola wyboru zostaną odwrócone.
- **Bootstrap fazy 1**: w pierwszej fazie zmiany, również zasiej zestaw wszystkimi nieśledzonymi lub zmodyfikowanymi plikami w `context/changes/<change-id>/` (zazwyczaj `change.md`, `research.md`, `plan.md`), aby pliki kontekstu zmiany trafiły do pierwszego commitu.
- Zestaw **resetuje się na każdej granicy fazy**, po zakończeniu commitu fazy.
- Zestaw nadpisuje `git status`. Plik, który jest brudny, ale nie znajduje się w zestawie, jest niepowiązany — nigdy nie jest przygotowywany.

**Przygotowanie zestawu (krok 2 stosu bram):** przygotuj zestaw zmienionych plików ∪ `{context/changes/<change-id>/plan.md}` (Faza 1: zestaw zasiany bootstrapem). Uruchom `git status --porcelain`; każda brudna ścieżka poza zestawem przygotowania jest **nigdy nie przygotowywana** — wymień ją jako `DIRTY (not staged): <paths>` w tekście odpowiedzi (aby pojawiła się w transkrypcji i raporcie z przebiegu) i kontynuuj tylko z zaplanowanym zestawem. Przygotuj według nazwy za pomocą `git add` każdy plik; nigdy `git add -A` lub `git add .`.

## Śledzenie odniesień do problemów/zadań dla commitów

Przed skomponowaniem jakiejkolwiek wiadomości commitu fazy lub epilogu, przeskanuj kontekst rozmowy w poszukiwaniu odniesień do systemu śledzenia związanych z tą pracą: klucze Jira (`ABC-123`), identyfikatory Linear (`ENG-123`), problemy/PR-y GitHub (`#123`, `GH-123`, pełne adresy URL) lub jawne linki do zadań. Jeśli są obecne, dodaj wiersz `Refs:` do treści commitu, zachowując dokładne identyfikatory; wiele odniesień umieść oddzielone przecinkami w jednym wierszu. Nigdy nie wymyślaj ani nie wnioskuj odniesień z change-id, nazwy gałęzi lub nazw plików — używaj tylko tego, co jest widoczne w kontekście. Zastosuj ten sam wiersz `Refs:` do każdego commitu fazy i epilogu.

## Autonomiczny rytuał commitu

Uruchamia się tylko jako krok (d) stosu bram, po tym, jak każda brama jest zielona, a zestaw zmienionych plików jest przygotowany (krok 2 stosu bram). Utwórz jeden commit Conventional-Commits i zapisz końcowy krótki SHA z powrotem do każdego wiersza Progress odwróconego podczas fazy. Żaden krok nie zatrzymuje się na zatwierdzenie.

1. **Sprawdź pusty diff**: `git diff --cached --quiet`. Kod wyjścia 0 oznacza brak commitu — wydrukuj `Phase <N> had no diff to commit; rows remain SHA-less; archive warn-only will surface them.`, ustaw `SHA=""` i przejdź do kroku 5.

2. **Skomponuj wiadomość**: temat `<type>(<change-id>): <phase title> (p<N>)`, gdzie `<type>` ∈ `feat / fix / chore / refactor / docs` wybrane z natury fazy. Treść: krótka lista zmienionych plików, plus wiersz `Refs:`, jeśli ma zastosowanie. Wydrukuj pełną wiadomość w tekście odpowiedzi przed commitowaniem — to jest zapis transkrypcji tego, co zostało zatwierdzone i dlaczego.

3. **Commit za pomocą heredoc**:

   ```bash
   git commit -m "$(cat <<'EOF'
   <type>(<change-id>): <phase title> (p<N>)

   <short body listing touched files>
   <Refs: issue/task references, if applicable>
   EOF
   )"
   ```

   Nigdy nie przekazuj flag `--no-verify`, `--amend` ani flag pomijających podpisywanie. Jeśli hook pre-commit się nie powiedzie, commit NIE nastąpił — traktuj błąd hooka jako błąd bramy (ma ten sam budżet 2 prób), napraw podstawowy problem i utwórz NOWY commit.

4. **Przechwyć krótki SHA**: `git rev-parse --short HEAD` (pomiń, jeśli `SHA=""`). Opisz go: `COMMIT p<N>: <sha>`.

5. **Zapisz SHA z powrotem do Progress**: dla każdego wiersza odwróconego podczas tej fazy, zmodyfikuj plik, aby zmienić `- [x] N.M <title>` → `- [x] N.M <title> — <SHA>`. Pomiń wiersze, które już zawierają sufiks SHA (bezpieczeństwo wznowienia — nigdy nie dodawaj podwójnie). Jeśli `SHA=""`, pozostaw wiersze bez SHA; `/10x-archive` wyświetli je jako ostrzeżenia informacyjne.

6. **Zaktualizuj `change.md`**: ustaw `updated: <today>`; zachowaj `status: implementing` do ostatniej fazy (patrz "Po wszystkich fazach").

7. **Zresetuj zestaw zmienionych plików** i przejdź bezpośrednio do następnej fazy — bez pauzy, bez punktu decyzyjnego. Przeczytaj sekcję planu następnej fazy, ustaw jej zadanie `in_progress` i kontynuuj.

## Wiersze ręczne

Wiersze w `#### Manual` są jurysdykcją człowieka, nigdy Twoją. Polityka:

- **Nigdy ich nie odwracaj.** Pozostają `- [ ]` bez względu na to, jak pewny jesteś, że zachowanie działa.
- **Nigdy na nich nie blokuj.** Faza jest zatwierdzana, gdy jej bramy Automated są zielone; jej wiersze Manual nie blokują commitu ani następnej fazy.
- **Zawsze je wyświetlaj.** Podsumowanie bram każdej fazy zawiera dosłowne oczekujące wiersze Manual fazy, a raport z przebiegu kończy się pełną listą wszystkich faz — ta lista jest listą kontrolną dla człowieka po przebiegu.

## Postęp i stan

**Sekcja `## Progress` w `plan.md` jest jedynym źródłem prawdy.** Żadnych plików stanu, żadnych znaczników komentarzy, żadnych plików towarzyszących. Modyfikuj TYLKO sekcję `## Progress` — bloki faz (Overview, Changes Required, Success Criteria) są tylko do odczytu.

- **Po każdym kroku**: zmodyfikuj plik, aby zmienić dokładnie jeden wiersz, `- [ ] N.M <title>` → `- [x] N.M <title>`. Brak sufiksu SHA w trakcie fazy — SHA ląduje na końcu fazy poprzez rytuał. Ukończone wiersze z `[x]` bez SHA w trakcie fazy są prawidłowym stanem pośrednim.
- **Gdzie jestem** jest wyprowadzane, a nie przechowywane: pierwszy oczekujący Automated `- [ ]` jest następnym krokiem; nagłówek fazy powyżej jest bieżącą fazą; ukończenie to `count([x]) / count([ ] + [x])`.
- **Wznowienie po STOP** nie wymaga dodatkowego stanu: ponowne wywołanie `/10x-goal-implement <change-id> [phase N]` znajduje pierwszy oczekujący wiersz Automated i kontynuuje. Zaufaj istniejącym znacznikom `[x]`; weryfikuj poprzednią pracę tylko wtedy, gdy coś wydaje się nie tak.

### Po wszystkich fazach

Gdy każdy wiersz Automated w całej sekcji `## Progress` ma wartość `- [x]`:

1. Zaktualizuj `change.md`: ustaw `status: implemented`, `updated: <today>`. (NIE ustawiaj `archived_at` — to należy do `/10x-archive`.) Oczekujące wiersze Manual nie blokują tego odwrócenia; są one wyświetlane w raporcie z przebiegu.
2. **Uruchom commit epilogu** — commit ostatniej fazy nie może zawierać własnego SHA, więc zapis SHA z powrotem plus odwrócenie statusu `change.md` pozostają brudne po rytuale ostatniej fazy:
   1. Przygotuj dokładnie `context/changes/<change-id>/plan.md` i `context/changes/<change-id>/change.md`.
   2. `git diff --cached --quiet` — jeśli puste, pomiń epilog.
   3. Commit za pomocą heredoc z tematem `chore(<change-id>): close out plan (epilogue)`, treścią odnotowującą końcowy zapis SHA z powrotem + change.md → implemented, plus wiersz `Refs:`, jeśli ma zastosowanie.
   4. NIE zapisuj własnego SHA epilogu z powrotem do planu.
3. Wydrukuj raport z przebiegu.

## Raport z przebiegu

Zakończ każdy przebieg — udany lub zatrzymany — raportem z przebiegu w tekście odpowiedzi. To jest to, co czyta ewaluator celu i wracający człowiek:

```
RUN REPORT — <change-id>

Phases: <completed>/<total>
- Phase 1: <title> — <sha> (gates: <names>: PASS)
- Phase 2: <title> — STOPPED (<reason>)

Adaptations:
- <minor mismatches adapted, one line each — or "none">

Uncertainties:
- <steps marked uncertain and why — or "none">

Pending manual verification (human checklist):
- <phase>.<index> <title>
- ...

Suggested follow-up: /10x-impl-review <change-id>
```

Wymień oczekujące wiersze Manual dosłownie z Progress. Jeśli przebieg został zatrzymany wcześniej, blok STOP poprzedza raport, a raport uczciwie odzwierciedla skrócony stan.

## Zalecane środowisko

Hooki per-edycji sprawiają, że ta pętla jest ciaśniejsza: hook PostToolUse uruchamiający lint, typecheck lub testy zakresowe (`vitest related "$FILE" --run`) przy każdej modyfikacji pliku wychwytuje dryf sekundy po jego wystąpieniu, zamiast na końcu fazy, a nieudany hook automatycznie wstrzykuje błąd z powrotem do kontekstu. Konfiguracja hooków jest własnością `the AI tool's configuration directory/settings.json` użytkownika — ta umiejętność działa bez żadnych hooków; po prostu uruchamia swoje bramy na poziomie fazy tak czy inaczej. Jeśli zauważysz, że takie hooki uruchamiają się podczas przebiegu, traktuj ich błędy jak każdy inny błąd bramy (ten sam budżet 2 prób).
