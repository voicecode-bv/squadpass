<?php

use App\Actions\Forms\ReopenFormSubmission;
use App\Actions\Forms\StoreFormSubmission;
use App\Enums\FormFieldType;
use App\Enums\StaffRole;
use App\Exceptions\FormException;
use App\Livewire\Forms\SubmissionShow;
use App\Livewire\Portal\FormFill;
use App\Livewire\Portal\MyForms;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->nameKey = FormField::newKey();
    $this->levelKey = FormField::newKey();

    $this->form = Form::factory()->for($this->club)->published(new FormSchema([
        new FormField(key: $this->nameKey, type: FormFieldType::Text, label: 'Naam', required: true),
        new FormField(key: $this->levelKey, type: FormFieldType::Select, label: 'Niveau', required: true, options: [
            ['value' => 'beginner', 'label' => 'Beginner'],
            ['value' => 'gevorderd', 'label' => 'Gevorderd'],
        ]),
    ]))->create();
});

test('a member fills in a form and the answers are stored against that version', function () {
    Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->set("answers.{$this->nameKey}", 'Sanne de Vries')
        ->set("answers.{$this->levelKey}", 'beginner')
        ->call('submit');

    $submission = FormSubmission::query()->sole();

    expect($submission->user_id)->toBe($this->member->id)
        ->and($submission->club_id)->toBe($this->club->id)
        ->and($submission->form_version_id)->toBe($this->form->published_version_id)
        ->and($submission->is_current)->toBeTrue()
        ->and($submission->answer($this->nameKey))->toBe('Sanne de Vries')
        ->and($submission->answer($this->levelKey))->toBe('beginner');
});

test('every field binds to the answers property so submitted values reach the server', function () {
    $html = Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->html();

    expect($html)
        ->toContain('wire:model="answers.'.$this->nameKey.'"')
        ->toContain('wire:model="answers.'.$this->levelKey.'"');
});

test('a field without a model renders disabled for the builder preview', function () {
    $html = Blade::render('<x-form-field :field="$field" :model="null" />', [
        'field' => new FormField(key: FormField::newKey(), type: FormFieldType::Text, label: 'Naam'),
    ]);

    expect($html)->toContain('disabled')
        ->and($html)->not->toContain('wire:model');
});

test('required fields and option values are validated', function () {
    Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->set("answers.{$this->nameKey}", '')
        ->set("answers.{$this->levelKey}", 'onbekend')
        ->call('submit')
        ->assertHasErrors(["answers.{$this->nameKey}", "answers.{$this->levelKey}"]);

    expect(FormSubmission::query()->count())->toBe(0);
});

test('a form that was sent in cannot be filled in again', function () {
    app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne',
        $this->levelKey => 'beginner',
    ]);

    expect(fn () => app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Iemand anders',
        $this->levelKey => 'gevorderd',
    ]))->toThrow(FormException::class);

    $submission = FormSubmission::query()->sole();

    expect($submission->answer($this->nameKey))->toBe('Sanne');
});

test('a sent in form is shown read-only without a submit form', function () {
    app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne de Vries',
        $this->levelKey => 'beginner',
    ]);

    $component = Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->assertSet('locked', true)
        ->assertSee('Sanne de Vries')
        ->assertSee(__('Beginner'))
        ->assertDontSee('wire:model', escape: false);

    expect($component->html())->not->toContain('wire:submit');
});

test('calling the submit action anyway writes nothing once the form was sent in', function () {
    app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne',
        $this->levelKey => 'beginner',
    ]);

    Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->set("answers.{$this->nameKey}", 'Getampered')
        ->call('submit');

    $submission = FormSubmission::query()->sole();

    expect($submission->answer($this->nameKey))->toBe('Sanne');
});

test('filling the form in again after the club reopened it keeps the earlier response as history', function () {
    $store = app(StoreFormSubmission::class);

    $first = $store->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne',
        $this->levelKey => 'beginner',
    ]);

    app(ReopenFormSubmission::class)->handle($first, $this->owner);

    $second = $store->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne de Vries',
        $this->levelKey => 'gevorderd',
    ]);

    expect(FormSubmission::query()->count())->toBe(2)
        ->and($first->refresh()->is_current)->toBeFalse()
        ->and($second->is_current)->toBeTrue()
        ->and($second->answer($this->nameKey))->toBe('Sanne de Vries');
});

test('staff reopen a response and the member may fill the form in again', function () {
    $submission = app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne',
        $this->levelKey => 'beginner',
    ]);

    Livewire::actingAs($this->owner)
        ->test(SubmissionShow::class, [
            'club' => $this->club,
            'form' => $this->form,
            'submission' => $submission,
        ])
        ->call('reopen');

    expect($submission->refresh()->reopened_at)->not->toBeNull()
        ->and($submission->reopened_by)->toBe($this->owner->id);

    Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form])
        ->assertSet('locked', false)
        ->set("answers.{$this->nameKey}", 'Sanne de Vries')
        ->set("answers.{$this->levelKey}", 'gevorderd')
        ->call('submit')
        ->assertHasNoErrors();

    expect(FormSubmission::query()->where('is_current', true)->sole()->answer($this->nameKey))
        ->toBe('Sanne de Vries');
});

test('a member cannot reach the page that reopens their response', function () {
    $submission = app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne',
        $this->levelKey => 'beginner',
    ]);

    $this->actingAs($this->member)
        ->get(route('clubs.forms.submissions.show', [$this->club, $this->form, $submission]))
        ->assertForbidden();
});

test('an existing response keeps rendering with the version it was filled in with', function () {
    app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne de Vries',
        $this->levelKey => 'beginner',
    ]);

    $submission = FormSubmission::query()->sole();

    // The owner rebuilds the form: the name field is gone and a new one appears.
    $newVersion = FormVersion::create([
        'form_id' => $this->form->id,
        'version' => 2,
        'schema' => (new FormSchema([
            new FormField(key: FormField::newKey(), type: FormFieldType::Text, label: 'Roepnaam', required: true),
        ]))->toArray(),
        'published_at' => now(),
    ]);
    $this->form->update(['published_version_id' => $newVersion->id]);

    Livewire::actingAs($this->owner)
        ->test(SubmissionShow::class, [
            'club' => $this->club,
            'form' => $this->form,
            'submission' => $submission,
        ])
        ->assertOk()
        ->assertSee('Naam')
        ->assertSee('Sanne de Vries')
        ->assertSee('Beginner')
        ->assertSet('isOutdated', true);
});

test('a member filling in again gets the new version with their overlapping answers prefilled', function () {
    app(StoreFormSubmission::class)->handle($this->form, $this->member, [
        $this->nameKey => 'Sanne de Vries',
        $this->levelKey => 'beginner',
    ]);

    $extraKey = FormField::newKey();

    $newVersion = FormVersion::create([
        'form_id' => $this->form->id,
        'version' => 2,
        'schema' => (new FormSchema([
            new FormField(key: $this->nameKey, type: FormFieldType::Text, label: 'Naam', required: true),
            new FormField(key: $extraKey, type: FormFieldType::Phone, label: 'Telefoon'),
        ]))->toArray(),
        'published_at' => now(),
    ]);
    $this->form->update(['published_version_id' => $newVersion->id]);

    Livewire::actingAs($this->member)
        ->test(FormFill::class, ['form' => $this->form->refresh()])
        ->assertSet("answers.{$this->nameKey}", 'Sanne de Vries')
        ->assertSet("answers.{$extraKey}", '')
        ->set("answers.{$extraKey}", '0612345678')
        ->call('submit');

    $current = FormSubmission::query()->where('is_current', true)->sole();

    expect($current->form_version_id)->toBe($newVersion->id)
        ->and($current->answers)->not->toHaveKey($this->levelKey);
});

test('a member of another club cannot open or submit the form', function () {
    $outsider = User::factory()->create();
    Club::factory()->create()->addMember($outsider);

    $this->actingAs($outsider)
        ->get(route('portal.forms.fill', $this->form))
        ->assertNotFound();

    expect(fn () => app(StoreFormSubmission::class)->handle($this->form, $outsider, [
        $this->nameKey => 'Indringer',
        $this->levelKey => 'beginner',
    ]))->toThrow(FormException::class);
});

test('an unpublished form is not offered in the portal', function () {
    $draft = Form::factory()->for($this->club)->create();

    Livewire::actingAs($this->member)
        ->test(MyForms::class)
        ->assertSee($this->form->name)
        ->assertDontSee($draft->name);

    $this->actingAs($this->member)
        ->get(route('portal.forms.fill', $draft))
        ->assertNotFound();
});
