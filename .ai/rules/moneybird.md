---
paths:
  - 'app/Services/Moneybird/**'
---

# Moneybird

## Moneybird via per-club MoneybirdGateway, facturen als concept
Elke club heeft een eigen Moneybird personal API token (encrypted op clubs.moneybird_access_token) plus administratie-ID. Al het Moneybird-verkeer loopt via de MoneybirdGateway-interface (HttpMoneybirdGateway in productie, Tests\Fakes\FakeMoneybirdGateway in tests), net als bij Mollie. Contacten: leden syncen via SyncMemberContact (contact-id op memberships.moneybird_contact_id, lidnummer als customer_id). Facturen: CreateInvoiceForPayment maakt bij een betaalde payment een CONCEPT-verkoopfactuur (bewust niet automatisch verzenden) en is idempotent via payments.moneybird_invoice_id; hij wordt aangeroepen via de CreateMoneybirdInvoice-job die ApplyPaymentStatus met afterCommit dispatcht, zodat er nooit een API-call in de databasetransactie zit.
