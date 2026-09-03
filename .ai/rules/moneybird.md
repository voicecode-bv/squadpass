---
paths:
  - 'app/Services/Moneybird/**'
---

# Moneybird

## Moneybird via per-club MoneybirdGateway, facturen als concept
Elke club heeft een eigen Moneybird personal API token (encrypted op clubs.moneybird_access_token) plus administratie-ID. Al het Moneybird-verkeer loopt via de MoneybirdGateway-interface (HttpMoneybirdGateway in productie, Tests\Fakes\FakeMoneybirdGateway in tests), net als bij Mollie. Contacten: leden syncen via SyncMemberContact (contact-id op memberships.moneybird_contact_id, lidnummer als customer_id). Facturen: CreateInvoiceForPayment maakt bij een betaalde payment een CONCEPT-verkoopfactuur (bewust niet automatisch verzenden) en is idempotent via payments.moneybird_invoice_id; hij wordt aangeroepen via de CreateMoneybirdInvoice-job die ApplyPaymentStatus met afterCommit dispatcht, zodat er nooit een API-call in de databasetransactie zit.

## Facturen als lokale spiegel in moneybird_invoices, crediteren via duplicate_creditinvoice
Het factuuroverzicht (app/Livewire/Invoices/Index) filtert nooit via de Moneybird API maar via de lokale spiegeltabel `moneybird_invoices`. Elke Moneybird-verkoopfactuur wordt daar door RecordInvoice weggeschreven: bij aanmaak (CreateInvoiceForPayment) en bij SyncInvoices, dat incrementeel pullt met filter `updated_after` op basis van max(moneybird_updated_at) van de club en pagineert (100 per pagina, max 25 pagina's per run). Koppeling aan een lid loopt via memberships.moneybird_contact_id; bij bulk-sync geef je RecordInvoice een voorgeladen contact-id => membership-id map mee zodat het aantal queries vast blijft. Bedragen staan als signed cents in total_price_incl_tax_cents (creditfacturen negatief), filteren op bedrag matcht daarom beide kanten van de range. Crediteren gebeurt uitsluitend via CreateCreditInvoice, die Moneybirds PATCH sales_invoices/{id}/duplicate_creditinvoice gebruikt (concept-creditfactuur, bewust niet verzonden) en dubbel crediteren blokkeert via MoneybirdInvoice::isCreditable().
