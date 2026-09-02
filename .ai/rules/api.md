---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## Leden-API v1: Sanctum-tokens, resources, en bewust géén betalingen
De API onder /api/v1 (routes/api.php) is voor de NativePHP-ledenapp: Sanctum personal access tokens (login met email/password/device_name), Eloquent API Resources in app/Http/Resources/Api/V1, en alle queries strikt op $request->user() gescoped (andermans records geven 404). Betaalflows (strippenkaart kopen, abonneren, opzeggen) lopen bewust NIET via de API maar alleen via het webportaal; strip-cards en subscriptions zijn in de API read-only. Domeinlogica hergebruiken via de bestaande action-klassen (EnrollSingle, EnrollFixed, CancelEnrollment, MarkAbsence); EnrollmentException wordt vertaald naar een 422-JSON-response.
