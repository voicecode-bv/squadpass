---
paths:
  - 'app/Actions/Addons/**'
---

# Addons

## Add-ons: platform-Mollie-account, aparte tabellen, feature-gating via Club::hasAddon()
Add-ons (enum AddonKey: mollie, moneybird) verkoopt het platform aan clubs; prijs/naam/te-koop beheert de superadmin via Gate 'manage-platform' (admin.addons). Betaling loopt via het PLATFORM-Mollie-account (PlatformMollieAccount, env MOLLIE_API_KEY), niet via de club-key: MollieGateway accepteert daarom een MollieAccount (Club of PlatformMollieAccount). Add-on-betalingen staan bewust in addon_payments (niet in payments, dat zijn ledenbetalingen) met eigen webhook POST /webhooks/mollie-platform en ApplyAddonPaymentStatus (idempotent, forward-only). Recurring via addons:charge (next_billing_at eerst doorschuiven), maandelijks opzegbaar: Cancelled blijft unlocked tot ends_at. Feature-checks altijd via Club::hasAddon()/acceptsOnlinePayments()/hasMoneybirdConfigured(); in tests Club::factory()->withMollieKey()/withMoneybird()/withAddon() gebruiken, anders zijn Mollie/Moneybird-features geblokkeerd.
