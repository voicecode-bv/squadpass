---
paths:
  - 'database/migrations/**'
---

# Migrations

## Migraties moeten MySQL-compatibel zijn, niet alleen SQLite
Dev/prod draait op MySQL, tests op SQLite. SQLite verhult twee MySQL-fouten: (1) een foreign key naar een tabel die pas in een latere migratie wordt aangemaakt (MySQL error 1824) — houd de migratievolgorde gelijk aan de FK-afhankelijkheden; (2) het droppen van de laatste index die een foreign key ondersteunt (MySQL error 1553) — maak bij het vervangen van een unique/index eerst de nieuwe index aan en drop daarna pas de oude, ook in down(). Verifieer nieuwe migraties met `php artisan migrate:fresh` op de MySQL-connectie.
