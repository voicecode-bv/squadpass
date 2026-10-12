---
paths:
  - 'app/{Livewire/Forms,Actions/Forms,Livewire/Portal,Services/Forms}/**'
---

# Forms

## Formulieren: gepubliceerde versies zijn onveranderlijk
Een form_version met published_at is NOOIT meer te muteren. Bewerken gaat via StartFormDraft (kloont het laatste schema naar version+1 met published_at null); PublishFormVersion zet published_at en verschuift forms.published_version_id. Elke form_submission bewaart form_version_id en moet altijd met dat eigen schema gerenderd worden ($submission->version->schemaObject()), nooit met de huidige versie — anders breken oude antwoorden bij een schemawijziging.

Veld-keys en optie-values zijn ULIDs die bij bewerken meegaan en nooit hergebruikt worden; antwoorden zijn {veld-key: waarde}. Nieuw label = zelfde key.

Inzendingen zijn append-only: StoreFormSubmission zet is_current=false op eerdere rijen en insert een nieuwe rij in één transactie. Query "huidige antwoorden" altijd via where('is_current', true) (indexen: form_id+is_current+submitted_at, user_id+form_id+is_current).

## Formulieren: een verstuurde inzending vergrendelt het formulier per versie
Een lid vult een formulier één keer in. De regel staat op één plek: `FormSubmission::locks(Form $form)` — waar is zolang de rij `is_current` is, `reopened_at` null is én `form_version_id` gelijk is aan `forms.published_version_id`.

Het formulier gaat dus vanzelf weer open zodra de club een nieuwe versie publiceert (het lid vult die nieuwe versie in, met de overlappende antwoorden voorgevuld), of zodra staff de inzending heropent via ReopenFormSubmission (zet reopened_at + reopened_by; de rij blijft als historie staan, er wordt niets verwijderd).

StoreFormSubmission bewaakt dit binnen de transactie met `lockForUpdate()` op de huidige rij vóór het wegschrijven van handtekeningen — twee gelijktijdige submits kunnen zo niet allebei slagen. De Livewire-guard in Portal\FormFill (`#[Locked] $locked` + `abort_if` in submit()) is de tweede laag, niet de enige.

Bouw je ergens een "wie moet dit nog invullen"-query, gebruik dan dezelfde voorwaarden (zie Forms\Submissions::pendingMembers), anders ziet staff iemand als klaar terwijl het lid "opnieuw invullen" ziet.
