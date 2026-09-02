---
paths:
  - 'app/Services/Mollie/**'
---

# Mollie

## Mollie via per-club MollieGateway, nooit de facade; webhooks server-side verifiëren
Elke club heeft een eigen Mollie API-key (encrypted op clubs.mollie_api_key). Alle Mollie-verkeer loopt via de MollieGateway-interface (HttpMollieGateway in productie, Tests\Fakes\FakeMollieGateway in tests) — nooit de Mollie facade of een globale key. Webhook (POST /webhooks/mollie/{club}, CSRF-exempt) bevat alleen een payment-id: altijd server-side de payment ophalen met de club-key. ApplyPaymentStatus is idempotent met forward-only statusovergangen; recurring incasso via eigen subscriptions:charge command (bewust niet Mollie's Subscriptions API), waarbij next_billing_at vóór het aanmaken van de payment wordt doorgeschoven.
