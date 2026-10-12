<?php

use App\Actions\Forms\ReopenFormSubmission;
use App\Actions\Forms\StoreFormSubmission;
use App\Enums\FormFieldType;
use App\Enums\StaffRole;
use App\Livewire\Forms\Submissions;
use App\Livewire\Portal\MyForms;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\User;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
});

test('a form for all members shows up for every member', function () {
    $form = Form::factory()->for($this->club)->published()->create(['name' => 'Intakeformulier']);

    Livewire::actingAs($this->member)
        ->test(MyForms::class)
        ->assertSee($form->name);
});

test('a form limited to assigned members stays hidden until it is assigned', function () {
    $form = Form::factory()->for($this->club)->assignedOnly()->published()->create(['name' => 'Blessureformulier']);

    Livewire::actingAs($this->member)
        ->test(MyForms::class)
        ->assertDontSee($form->name);

    $this->actingAs($this->member)
        ->get(route('portal.forms.fill', $form))
        ->assertNotFound();

    Livewire::actingAs($this->owner)
        ->test(Submissions::class, ['club' => $this->club, 'form' => $form])
        ->call('assign', $this->member->id);

    expect(FormAssignment::query()->where('form_id', $form->id)->where('user_id', $this->member->id)->exists())->toBeTrue();

    Livewire::actingAs($this->member)
        ->test(MyForms::class)
        ->assertSee($form->name);

    $this->actingAs($this->member)
        ->get(route('portal.forms.fill', $form))
        ->assertOk();
});

test('an assignment can be removed again', function () {
    $form = Form::factory()->for($this->club)->assignedOnly()->published()->create();
    FormAssignment::create(['form_id' => $form->id, 'user_id' => $this->member->id]);

    Livewire::actingAs($this->owner)
        ->test(Submissions::class, ['club' => $this->club, 'form' => $form])
        ->call('unassign', $this->member->id);

    expect(FormAssignment::query()->count())->toBe(0);
});

test('a member of another club cannot be assigned', function () {
    $form = Form::factory()->for($this->club)->assignedOnly()->published()->create();
    $outsider = User::factory()->create();

    $component = Livewire::actingAs($this->owner)
        ->test(Submissions::class, ['club' => $this->club, 'form' => $form]);

    expect(fn () => $component->call('assign', $outsider->id))
        ->toThrow(ModelNotFoundException::class);

    expect(FormAssignment::query()->count())->toBe(0);
});

test('outstanding members are listed separately from the responses', function () {
    $form = Form::factory()->for($this->club)->published()->create();

    Livewire::actingAs($this->owner)
        ->test(Submissions::class, ['club' => $this->club, 'form' => $form])
        ->set('tab', 'pending')
        ->assertSee($this->member->name);
});

test('a reopened member shows up as pending again', function () {
    $nameKey = FormField::newKey();
    $form = Form::factory()->for($this->club)->published(new FormSchema([
        new FormField(key: $nameKey, type: FormFieldType::Text, label: 'Naam', required: true),
    ]))->create();

    $submission = app(StoreFormSubmission::class)->handle($form, $this->member, [$nameKey => 'Sanne']);

    Livewire::actingAs($this->owner)
        ->test(Submissions::class, ['club' => $this->club, 'form' => $form])
        ->set('tab', 'pending')
        ->assertDontSee($this->member->name);

    app(ReopenFormSubmission::class)->handle($submission, $this->owner);

    Livewire::actingAs($this->owner)
        ->test(Submissions::class, ['club' => $this->club, 'form' => $form])
        ->set('tab', 'pending')
        ->assertSee($this->member->name);
});

test('a non-staff member cannot open the responses of a form', function () {
    $form = Form::factory()->for($this->club)->published()->create();

    $this->actingAs($this->member)
        ->get(route('clubs.forms.submissions', [$this->club, $form]))
        ->assertForbidden();
});
