---
paths:
  - 'app/{Services/Impersonation,Http/Middleware}/**'
---

# Middleware

## Impersonatie: owners → leden, superadmins → owners, nooit genest, geen accountwijzigingen
Impersonatie loopt via App\Services\Impersonation\Impersonation (sessiekeys `impersonator_id` + `impersonation_return_to`) en ImpersonationController.

- Alleen club-owners mogen impersonaten (MembershipPolicy::impersonate). De controller weigert daarnaast altijd: al actieve impersonatie, jezelf, superadmins en staff van de club — die invarianten moeten in de controller staan omdat de Gate::before-hook voor superadmins elke policy-check overslaat.
- Superadmins kunnen club-owners impersonaten via `clubs.owners.impersonate` (ImpersonationController::storeOwner, Gate `manage-platform`, knop op clubs.index). De `{owner}`-binding is gescoped op Club::owners(), dus een niet-owner of owner van een andere club geeft 404. Ook hier: nooit genest, niet jezelf, geen superadmin als doel.
- Auth::login() migreert de sessie (session->migrate(true)); zet sessiekeys dus altijd ná het inloggen, nooit ervoor.
- EnsureNotImpersonating blokkeert credential-routes (profile.edit, security.edit, two-factor.*, passkey.*, password.confirm) zodat impersonatie niet omgezet kan worden in permanente toegang. Hij hangt in de `web`-groep (bootstrap/app.php) én op de settings-routes, en staat in Livewire::addPersistentMiddleware zodat hij ook op component-updates opnieuw draait. Nieuwe gevoelige route? Voeg de routenaam toe aan BLOCKED_ROUTES.
- `impersonate/stop` hangt bewust alleen achter `auth`, niet achter `verified`, anders sluit een niet-geverifieerd lid de staffsessie op.
