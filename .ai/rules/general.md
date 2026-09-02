---
paths:
  - '**/*.php'
---

# General

## Voeg bij elke nieuwe UI-string altijd de Nederlandse vertaling toe
Alle user-facing strings gaan door __() / trans_choice() met Engelse bronteksten. Bij elke nieuwe of gewijzigde string moet ook de Nederlandse vertaling in lang/nl.json worden toegevoegd (trans_choice-varianten met | pipe-syntax). De app draait standaard op locale nl.
