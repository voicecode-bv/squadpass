---
paths:
  - 'app/Actions/**'
---

# Actions

## Capaciteit, strips en credits alleen via action-klassen met atomaire conditionele updates
Plekken (lesson_occurrences.booked_count), strippen (strip_cards.strips_remaining) en credits (subscriptions.credits_remaining) muteren uitsluitend via de action-klassen in app/Actions en de atomaire helpers claimSpot()/releaseSpot(), redeemStrip(), consumeCredits()/refundCredits(). Die gebruiken conditionele UPDATE ... WHERE-queries (0 affected rows = vol/leeg) in plaats van count+insert, zodat races onmogelijk zijn. Nooit deze kolommen direct increments/decrements geven buiten die helpers; multi-stap flows in DB::transaction zodat rollback claims terugdraait.

## Credits alleen via CreditLedger: saldo, grants en tijdlijn blijven zo in sync
Elke creditmutatie loopt via App\Services\Credits\CreditLedger (grant/consume/refund/forfeit/expire), nooit rechtstreeks via Subscription::consumeCredits()/refundCredits(). De ledger houdt drie dingen gelijk: subscriptions.credits_remaining (atomair gecachet saldo), credit_grants (batches met eigen expires_at; verbruik pakt de grant die het eerst verloopt, teruggave gaat naar de grant waaruit de inschrijving betaalde) en credit_transactions (de tijdlijn per lid, append-only). Credits worden elke maand aangevuld via `credits:grant` (subscriptions.next_credits_at, geteld vanaf starts_at), los van de facturatie: ook een jaarabonnement krijgt maandelijks credits, en ChargeSubscription raakt credits niet aan. Aanvullen telt op bij het restant; `credits:expire` (elk uur) haalt verlopen restanten van het saldo. Annuleringstermijn (clubs.cancellation_deadline_hours) geldt alleen als het lid zelf annuleert: geef in CancelEnrollment/MarkAbsence altijd de handelende gebruiker mee als $actor (staf en systeem = altijd terugbetalen). Een les annuleren namens de club gaat via CancelOccurrence (nooit alleen de status zetten): die geeft alle boekingen vrij en betaalt credits terug, ook binnen de termijn.
