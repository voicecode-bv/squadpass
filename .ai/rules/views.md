---
paths:
  - 'resources/views/**'
---

# Views

## Icons zijn Fluent UI System Icons via Flux-overrides
De app gebruikt geen Heroicons. Elk icoon in `resources/views/flux/icon/{naam}.blade.php` is een override die via de `svg()` helper van blade-icons een `fluentui-*` icoon rendert (codeat3/blade-fluentui-system-icons).

Blijf in views gewoon de Flux-namen gebruiken (`<flux:icon.plus />`, `icon="magnifying-glass"`); de override mapt naar de Fluent-naam (plus => add, magnifying-glass => search, trash => delete).

Nieuw icoon nodig? Maak het override-bestand aan naar het model van een bestaande, met de variant-mapping outline => `-24-o`, solid => `-24`, mini => `-20-o`, micro => `-16-o` (val terug op een grotere maat als die niet bestaat). Zonder override valt Flux terug op Heroicons en wijkt het icoon visueel af; `tests/Feature/FluentIconsTest.php` bewaakt dit.

`flux:icon.loading` blijft bewust de Flux-spinner.
