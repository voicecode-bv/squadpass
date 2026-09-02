---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Autoriseer in mount() én in elke Livewire action-methode; scope queries op club of auth-user
Livewire-actions zijn aparte HTTP-requests: $this->authorize(...) in mount() alleen is onvoldoende — herhaal de check in elke publieke action-methode. Beheer-components authorizen 'manage' (of 'update' voor settings) op de club en scopen elke query op club_id; portaal-components (app/Livewire/Portal) scopen altijd op Auth::id(). Records uit andere clubs horen een 404 te geven via where('club_id', ...)->findOrFail().
