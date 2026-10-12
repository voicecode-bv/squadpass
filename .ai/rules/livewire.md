---
paths:
  - 'app/Livewire/**'
  - app/Livewire/GlobalSearch.php
---

# Livewire

## Autoriseer in mount() én in elke Livewire action-methode; scope queries op club of auth-user
Livewire-actions zijn aparte HTTP-requests: $this->authorize(...) in mount() alleen is onvoldoende — herhaal de check in elke publieke action-methode. Beheer-components authorizen 'manage' (of 'update' voor settings) op de club en scopen elke query op club_id; portaal-components (app/Livewire/Portal) scopen altijd op Auth::id(). Records uit andere clubs horen een 404 te geven via where('club_id', ...)->findOrFail().

## Globale zoekbalk: prefix-match, per groep gelimiteerd, gescoped op staff-clubs
`App\Livewire\GlobalSearch` staat in de topbar (layouts/app/sidebar.blade.php) en zoekt in clubs, leden, lessen en tickets. Uitbreiden? Houd deze eigenschappen aan:

- Scope elke query op `$this->clubIds` (staff-clubs van de gebruiker; superadmin krijgt alle clubs). De balk wordt alleen gerenderd voor staf/superadmin.
- Zoek met een prefix-LIKE (`term%`), nooit `%term%`: dat houdt de index bruikbaar op tabellen met miljoenen rijen.
- Minimaal 2 tekens (`MIN_LENGTH`) en `limit` per groep (`PER_GROUP`); de input debounced 300 ms.
- Lege groepen worden weggefilterd, zodat de dropdown alleen toont wat er is.
