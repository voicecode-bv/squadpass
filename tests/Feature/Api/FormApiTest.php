<?php

use App\Enums\FormFieldType;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->nameKey = FormField::newKey();
    $this->signatureKey = FormField::newKey();

    $this->form = Form::factory()->for($this->club)->published(new FormSchema([
        new FormField(key: $this->nameKey, type: FormFieldType::Text, label: 'Naam', required: true),
        new FormField(key: $this->signatureKey, type: FormFieldType::Signature, label: 'Handtekening', required: true),
    ]))->create(['name' => 'Gezondheidsverklaring']);

    Sanctum::actingAs($this->member);
});

test('forms lists published forms of the member clubs only', function () {
    Form::factory()->published()->create(['name' => 'Andere club']);
    Form::factory()->for($this->club)->create(['name' => 'Concept']);

    $this->getJson('/api/v1/forms')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Gezondheidsverklaring')
        ->assertJsonPath('data.0.locked', false)
        ->assertJsonPath('data.0.submitted_at', null);
});

test('a form limited to assigned members is hidden until the member is assigned', function () {
    $form = Form::factory()->for($this->club)->assignedOnly()->published()->create(['name' => 'Selectietraining']);

    $this->getJson('/api/v1/forms')->assertJsonMissing(['name' => 'Selectietraining']);
    $this->getJson("/api/v1/forms/{$form->id}")->assertNotFound();

    FormAssignment::create(['form_id' => $form->id, 'user_id' => $this->member->id]);

    $this->getJson("/api/v1/forms/{$form->id}")->assertOk();
});

test('an open form is returned with its fields and empty answers', function () {
    $this->getJson("/api/v1/forms/{$this->form->id}")
        ->assertOk()
        ->assertJsonPath('data.locked', false)
        ->assertJsonCount(2, 'data.fields')
        ->assertJsonPath('data.fields.0.key', $this->nameKey)
        ->assertJsonPath('data.fields.0.type', 'text')
        ->assertJsonPath('data.fields.0.required', true)
        ->assertJsonPath("data.answers.{$this->nameKey}", '');
});

test('a member can send in answers and the form locks afterwards', function () {
    $this->postJson("/api/v1/forms/{$this->form->id}/submissions", [
        'answers' => [
            $this->nameKey => 'Sanne de Vries',
            $this->signatureKey => 'data:image/png;base64,'.base64_encode('png-bytes'),
        ],
    ])->assertCreated();

    expect(FormSubmission::query()->where('user_id', $this->member->id)->where('is_current', true)->count())->toBe(1);

    $this->getJson("/api/v1/forms/{$this->form->id}")
        ->assertOk()
        ->assertJsonPath('data.locked', true)
        ->assertJsonPath("data.answers.{$this->nameKey}", 'Sanne de Vries')
        // The stored signature path must never leave the server.
        ->assertJsonPath("data.answers.{$this->signatureKey}", true);
});

test('sending the same form in twice is refused', function () {
    $answers = [
        'answers' => [
            $this->nameKey => 'Sanne de Vries',
            $this->signatureKey => 'data:image/png;base64,'.base64_encode('png-bytes'),
        ],
    ];

    $this->postJson("/api/v1/forms/{$this->form->id}/submissions", $answers)->assertCreated();
    $this->postJson("/api/v1/forms/{$this->form->id}/submissions", $answers)->assertStatus(422);

    expect(FormSubmission::query()->count())->toBe(1);
});

test('answers are validated against the schema of the published version', function () {
    $this->postJson("/api/v1/forms/{$this->form->id}/submissions", ['answers' => [$this->nameKey => '']])
        ->assertStatus(422)
        ->assertJsonValidationErrors("answers.{$this->nameKey}");
});

test('a member who is not a member of the club cannot reach the form', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/forms/{$this->form->id}")->assertNotFound();
    $this->postJson("/api/v1/forms/{$this->form->id}/submissions", ['answers' => [$this->nameKey => 'X']])->assertNotFound();
});
