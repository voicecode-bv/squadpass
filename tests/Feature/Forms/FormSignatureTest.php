<?php

use App\Actions\Forms\ReopenFormSubmission;
use App\Actions\Forms\StoreFormSubmission;
use App\Enums\FormFieldType;
use App\Enums\StaffRole;
use App\Livewire\Portal\FormFill;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->consentKey = FormField::newKey();
    $this->signatureKey = FormField::newKey();

    $this->form = Form::factory()->for($this->club)->published(new FormSchema([
        new FormField(
            key: $this->consentKey,
            type: FormFieldType::Consent,
            label: 'Ik ga akkoord met de huisregels',
            help: 'Versie 2026-01',
            required: true,
        ),
        new FormField(key: $this->signatureKey, type: FormFieldType::Signature, label: 'Handtekening', required: true),
    ]))->create();

    // A 1x1 transparent PNG.
    $this->signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
});

test('a signature is stored as a file and only its path ends up in the answers', function () {
    Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->set("answers.{$this->consentKey}", true)
        ->set("answers.{$this->signatureKey}", $this->signature)
        ->call('submit');

    $submission = FormSubmission::query()->sole();
    $path = $submission->answer($this->signatureKey);

    expect($path)->toStartWith("form-signatures/{$this->form->id}/{$this->member->id}/")
        ->and($path)->toEndWith('.png');

    Storage::disk('local')->assertExists($path);
});

test('after saving, the property holds the stored path and the saved signature is shown', function () {
    $component = Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->set("answers.{$this->consentKey}", true)
        ->set("answers.{$this->signatureKey}", $this->signature)
        ->call('submit');

    $submission = FormSubmission::query()->sole();

    // The Alpine pad wipes the canvas as soon as the property is no longer a
    // data URL, so the signature is not shown twice after saving.
    $component
        ->assertSet("answers.{$this->signatureKey}", $submission->answer($this->signatureKey))
        ->assertSee(route('portal.forms.signature', [$submission, $this->signatureKey]), escape: false);
});

test('a required consent statement must be accepted', function () {
    Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->set("answers.{$this->consentKey}", false)
        ->set("answers.{$this->signatureKey}", $this->signature)
        ->call('submit')
        ->assertHasErrors("answers.{$this->consentKey}");

    expect(FormSubmission::query()->count())->toBe(0);
});

test('the signature is served to the member and to club staff, but not to anyone else', function () {
    $submission = app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->consentKey => true,
        $this->signatureKey => $this->signature,
    ]);

    $route = route('portal.forms.signature', [$submission, $this->signatureKey]);

    $this->actingAs($this->member)->get($route)->assertOk();
    $this->actingAs($this->owner)->get($route)->assertOk();

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get($route)->assertForbidden();
});

test('filling in again after a reopen without drawing keeps the stored signature', function () {
    $first = app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->consentKey => true,
        $this->signatureKey => $this->signature,
    ]);

    app(ReopenFormSubmission::class)->handle($first, $this->owner);

    $second = app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->consentKey => true,
        $this->signatureKey => $first->answer($this->signatureKey),
    ]);

    expect($second->answer($this->signatureKey))->toBe($first->answer($this->signatureKey))
        ->and(Storage::disk('local')->files("form-signatures/{$this->form->id}/{$this->member->id}"))->toHaveCount(1);
});
