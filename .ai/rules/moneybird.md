---
paths:
  - 'app/Services/Moneybird/**'
---

# Moneybird

## Moneybird via per-club MoneybirdGateway, facturen als concept
Elke club heeft een eigen Moneybird personal API token (encrypted op clubs.moneybird_access_token) plus administratie-ID. Al het Moneybird-verkeer loopt via de MoneybirdGateway-interface (HttpMoneybirdGateway in productie, Tests\Fakes\FakeMoneybirdGateway in tests), net als bij Mollie. Contacten: leden syncen via SyncMemberContact (contact-id op memberships.moneybird_contact_id, lidnummer als customer_id). Facturen: CreateInvoiceForPayment maakt bij een betaalde payment een verkoopfactuur met de club-instellingen (clubs.moneybird_ledger_account_id/tax_rate_id/workflow_id/document_style_id, prijzen incl. btw, reference 'SquadPass #id') en is idempotent via payments.moneybird_invoice_id (id wordt opgeslagen VOOR het finaliseren). Wat daarna gebeurt bepaalt clubs.moneybird_invoice_delivery (enum MoneybirdInvoiceDelivery): Draft = concept laten staan; Manual/Email = send_invoice (PATCH, delivery_method Manual/Email) + Mollie-betaling registreren via sales_invoices/:id/payments. Refunds: CreateCreditInvoiceForPayment (duplicate_creditinvoice, idempotent via payments.moneybird_credit_invoice_id; bij finaliseren ook register_payment_creditinvoice). Alles loopt via queued jobs (CreateMoneybirdInvoice, CreateMoneybirdCreditInvoice, SyncMoneybirdContact) die ApplyPaymentStatus/Club::addMember met afterCommit dispatchen; job-failures landen in clubs.moneybird_last_error. Koppelen gebeurt op de Livewire-pagina Clubs\Moneybird (token valideren via listAdministrations, administratie kiezen, referentiedata 15 min gecachet per club). Backfill: `moneybird:invoice-payments {club?}` en `moneybird:sync-contacts {club}`.
