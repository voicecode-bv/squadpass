---
paths:
  - 'app/Actions/**'
---

# Actions

## Capaciteit, strips en credits alleen via action-klassen met atomaire conditionele updates
Plekken (lesson_occurrences.booked_count), strippen (strip_cards.strips_remaining) en credits (subscriptions.credits_remaining) muteren uitsluitend via de action-klassen in app/Actions en de atomaire helpers claimSpot()/releaseSpot(), redeemStrip(), consumeCredits()/refundCredits(). Die gebruiken conditionele UPDATE ... WHERE-queries (0 affected rows = vol/leeg) in plaats van count+insert, zodat races onmogelijk zijn. Nooit deze kolommen direct increments/decrements geven buiten die helpers; multi-stap flows in DB::transaction zodat rollback claims terugdraait.
