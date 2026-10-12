<?php

use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Enums\StaffRole;
use App\Livewire\Forms\Builder;
use App\Livewire\Forms\Index;
use App\Models\Club;
use App\Models\Form;
use App\Models\User;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('an owner creates a form and publishes the first version', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->set('name', 'Intakeformulier')
        ->call('createForm');

    $form = Form::query()->where('club_id', $this->club->id)->sole();

    expect($form->status)->toBe(FormStatus::Draft);

    Livewire::actingAs($this->owner)
        ->test(Builder::class, ['club' => $this->club, 'form' => $form])
        ->call('newField', FormFieldType::Text->value)
        ->set('fieldLabel', 'Naam')
        ->set('fieldRequired', true)
        ->call('saveField')
        ->call('publish');

    $form->refresh();

    expect($form->status)->toBe(FormStatus::Published)
        ->and($form->publishedVersion->version)->toBe(1)
        ->and($form->publishedVersion->schemaObject()->fields)->toHaveCount(1)
        ->and($form->publishedVersion->schemaObject()->fields[0]->label)->toBe('Naam');
});

test('a published form cannot be published empty', function () {
    $form = Form::factory()->for($this->club)->create();

    Livewire::actingAs($this->owner)
        ->test(Builder::class, ['club' => $this->club, 'form' => $form])
        ->call('publish');

    expect($form->refresh()->status)->toBe(FormStatus::Draft)
        ->and($form->published_version_id)->toBeNull();
});

test('editing a published form leaves the live version untouched until publishing', function () {
    $form = Form::factory()->for($this->club)->published()->create();
    $liveVersion = $form->publishedVersion;

    $component = Livewire::actingAs($this->owner)
        ->test(Builder::class, ['club' => $this->club, 'form' => $form])
        ->call('newField', FormFieldType::Date->value)
        ->set('fieldLabel', 'Geboortedatum')
        ->call('saveField');

    $form->refresh();

    expect($form->published_version_id)->toBe($liveVersion->id)
        ->and($liveVersion->refresh()->schemaObject()->fields)->toHaveCount(1)
        ->and($form->draftVersion()->version)->toBe(2)
        ->and($form->draftVersion()->schemaObject()->fields)->toHaveCount(2);

    $component->call('publish');

    $form->refresh();

    expect($form->published_version_id)->not->toBe($liveVersion->id)
        ->and($form->publishedVersion->version)->toBe(2)
        ->and($form->versions()->count())->toBe(2);
});

test('a field keeps its key when it is renamed, so answers stay linked', function () {
    $key = FormField::newKey();
    $form = Form::factory()->for($this->club)->published(new FormSchema([
        new FormField(key: $key, type: FormFieldType::Text, label: 'Naam'),
    ]))->create();

    Livewire::actingAs($this->owner)
        ->test(Builder::class, ['club' => $this->club, 'form' => $form])
        ->call('editField', $key)
        ->set('fieldLabel', 'Volledige naam')
        ->call('saveField')
        ->call('publish');

    $fields = $form->refresh()->publishedVersion->schemaObject()->fields;

    expect($fields[0]->key)->toBe($key)
        ->and($fields[0]->label)->toBe('Volledige naam');
});

test('fields can be reordered and removed in the draft', function () {
    $first = FormField::newKey();
    $second = FormField::newKey();

    $form = Form::factory()->for($this->club)->published(new FormSchema([
        new FormField(key: $first, type: FormFieldType::Text, label: 'Eerste'),
        new FormField(key: $second, type: FormFieldType::Text, label: 'Tweede'),
    ]))->create();

    Livewire::actingAs($this->owner)
        ->test(Builder::class, ['club' => $this->club, 'form' => $form])
        ->call('moveField', $second, -1)
        ->call('deleteField', $first);

    $fields = $form->refresh()->draftVersion()->schemaObject()->fields;

    expect($fields)->toHaveCount(1)
        ->and($fields[0]->key)->toBe($second);
});

test('a non-staff member cannot open the club forms overview', function () {
    $member = User::factory()->create();
    $this->club->addMember($member);

    $this->actingAs($member)
        ->get(route('clubs.forms', $this->club))
        ->assertForbidden();
});

test('a form of another club gives a 404 on the club route', function () {
    $foreignForm = Form::factory()->create();

    $this->actingAs($this->owner)
        ->get(route('clubs.forms.edit', [$this->club, $foreignForm]))
        ->assertNotFound();
});
