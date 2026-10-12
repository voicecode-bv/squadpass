---
paths:
  - 'app/{Services/CheckIns,Actions/CheckIns}/**'
---

# Check Ins

## QR-check-in: roterende HMAC-codes, altijd een bezoek zonder les
QR-check-in werkt in twee richtingen: het lid scant de clubcode op het kioskscherm (Livewire\CheckIns\Kiosk, POST /api/v1/check-ins), of de balie scant de code uit de app met de webcam (Desk::scanMemberCode, jsQR via de Alpine-component spQrScanner).

Codes zijn stateloos: CheckInCode tekent subject ('C' club / 'M' lid) + id + tijdvenster met de app key, venster van 30s plus één venster grace. Nooit een code in de database opslaan en nooit een vast (niet-roterend) alternatief toevoegen: een foto van de code moet binnen een minuut waardeloos zijn. Codes blijven in de QR-alfanumerieke set (hoofdletters, cijfers, punten).

Een gescande check-in is altijd een los bezoek: CheckInWithCode geeft géén lesson_occurrence mee (lesdeelname loopt via reserveringen), en omdat er dan geen unique constraint is, blokkeert de DUPLICATE_WINDOW_MINUTES-guard een tweede scan binnen 15 minuten zodat er geen tweede strip afgaat. Dekking blijft volledig aan CheckInMember.

/check-in-code geeft ook een image_url: CheckInCodeImageController rendert de code als PNG (GD, geen imagick) op een publieke, throttled route — de ondertekende, seconden-geldige code is daar de enige credential, alles wat geen levende lid-code is geeft 404.
