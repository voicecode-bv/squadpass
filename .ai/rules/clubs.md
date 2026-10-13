---
paths:
  - 'app/{Actions/ClubSubscriptions,Livewire/Members,Livewire/Clubs}/**'
  - app/Models/Club.php
---

# Clubs

## Leden toevoegen alleen via Club::addMember(): vereist proefperiode of SquadPass-abonnement
Elk pad dat een lidmaatschap actief maakt (toevoegen, uitnodiging accepteren, join-link, heractiveren) loopt via Club::addMember(); die lockt de clubrij en roept ensureCanAddMembers() aan (gooit ClubPlanException). Maak nooit direct een actieve Membership aan of zet status op Active buiten addMember. Toegang = Club::currentSubscription() (Active, of Cancelled t/m ends_at) óf trial_ends_at >= vandaag (14 dagen, gezet in Club::booted creating). Limiet = ClubPlan::memberLimit(); prijs = ClubPlan::priceCents(). Beide komen uit club_plan_settings (superadmin beheert ze via admin.club-plans, gecached via ClubPlanSetting::values()), met de default*()-waarden in het enum alleen als fallback; nooit prijzen of limieten hardcoden, en labels afleiden uit de limiet. Nieuwe prijs geldt alleen voor nieuwe abonnementen (price_cents vastgelegd), nieuwe limiet direct, tijdens trial ClubPlan::TRIAL_MEMBER_LIMIT; telt alleen status Active. Facturatie spiegelt add-ons: club_subscriptions + club_subscription_payments op het platform-Mollie-account, dezelfde webhook, command club-subscriptions:charge. Plannen zonder mandaat (Enterprise, door superadmin geactiveerd) worden nooit geïncasseerd.
