# Wejście obrazu (model wizyjny) — Krótki plan

> Pełny plan: `context/changes/vision-image-input/plan.md`
> Roadmap: S-03 w `context/foundation/roadmap.md`

## Co i dlaczego

Zalogowany może wysłać jedno zdjęcie z wiadomością do lokalnego modelu wizyjnego (FR-010), przez istniejący proxy stream, z podglądem w historii. Detekcji modelu nie robimy — zawsze attach + czytelny błąd upstream.

## Punkt wyjścia

Czat text-only end-to-end (string `content`). S-01/S-02 dają guest localStorage i account `prompts`; limity 100k / 100KB JSON blokują base64. S-03 było zablokowane na „jak wykryć vision”.

## Pożądany stan końcowy

Auth: plik/paste/DnD → compress → parts OpenAI w historii i DB → stream z data-URL → preview po reload. Gość bez obrazów. Reasoning nadal poza historią modelu.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) |
| ------ | ----- | ------------------- |
| Detekcja vision | Brak — zawsze attach + mapowanie błędów | Unika fałszywej blokady przy LM Studio |
| Zakres użytkowników | Tylko zalogowany | Override PRD; omija limit localStorage |
| Storage | JSON parts (text + image_url data-URL) w `content` | Jeden shape do replay; legacy string OK |
| request_payload | Sanitizacja bez bajtów obrazu | Limit 100KB JSON i rozmiar propsów |
| UX attach | Plik + paste + DnD, max 1 obraz | Mainstream chat UX |
| Limity | ~4MB plik + client canvas compress | Bez nowej zależności npm |
| Testy | Feature/Unit/JS, bez Playwright | Spójne z S-01/S-02 |

## Zakres

**W zakresie:** walidacja/composers multimodal, persistencja auth parts, UI attach+compress+render, mapowanie błędów, testy.

**Poza zakresem:** guest images, detekcja modelu, upload dysk/S3, multi-image, HTTPS image URL, Playwright, auth na stream.

## Architektura / Podejście

```
Auth UI: Composer(attach) → parts → account store (content JSON) 
       → historyForModel(parts) → POST chat.stream → composer → upstream
Guest:  Composer(text only) — bez zmian ścieżki obrazów
Storage: prompts.content = string | json(parts); request_payload bez data-URL
```

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| ---- | ------------ | --------------- |
| 1. Kontrakt multimodal | Stream + composers parts | DoS/size bez twardego max |
| 2. Trwałość konta | Parts w DB + sanitizacja payload | Inertia/props size; 100KB JSON |
| 3. UI auth-only | Attach/compress/render | Compress quality vs limit |
| 4. Błędy + testy | Feedback + CI | Heurystyka błędów upstream |

**Wymagania wstępne:** S-01 + S-02 done.
**Szacowany wysiłek:** ~2–3 sesje w 4 fazach.

## Otwarte ryzyka i założenia

- Długie historie z wieloma obrazami w propsach Inertia będą ciężkie (akceptowane w MVP single-user).
- Override PRD (gość bez vision) warto później odzwierciedlić w foundation, jeśli produkt to utrzyma.
- Modele lokalne różnie raportują błędy „nie przyjmuję obrazów” — mapowanie best-effort.

## Kryteria sukcesu (podsumowanie)

- Zalogowany wysyła obraz i dostaje odpowiedź (gdy model wizyjny) lub jasny błąd (gdy nie).
- Obraz widać w wątku po reload; gość nie ma attach.
- Reasoning nie wraca do historii; guest isolation bez regresji.
