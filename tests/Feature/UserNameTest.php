<?php

use App\Enums\StaffRole;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Settings\Profile;
use App\Models\Club;
use App\Models\User;
use Livewire\Livewire;

test('the full name is composed from the parts', function () {
    $user = User::factory()->create(['first_name' => 'Jan', 'name_prefix' => 'van der', 'last_name' => 'Berg']);

    expect($user->name)->toBe('Jan van der Berg');

    $user->update(['name_prefix' => '']);

    expect($user->refresh()->name)->toBe('Jan Berg')
        ->and($user->name_prefix)->toBeNull();
});

test('setting only the full name splits it into parts', function () {
    $user = User::factory()->create(['name' => 'Sanne de Vries']);

    expect([$user->first_name, $user->name_prefix, $user->last_name])->toBe(['Sanne', 'de', 'Vries']);

    $user->update(['name' => 'Jan Willem van den Heuvel']);

    expect([$user->refresh()->first_name, $user->name_prefix, $user->last_name])->toBe(['Jan Willem', 'van den', 'Heuvel']);
});

test('members with an automatically split name are asked to check it until they save their profile', function () {
    $user = User::factory()->unconfirmedName()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertSee(__('Check your name'));

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertSee(__('Check your name'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->needsNameReview())->toBeFalse();

    $this->actingAs($user)->get(route('dashboard'))->assertDontSee(__('Check your name'));
});

test('the member list sorts and searches on last name', function () {
    $club = Club::factory()->create();
    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);
    $club->addMember(User::factory()->create(['first_name' => 'Anna', 'name_prefix' => 'de', 'last_name' => 'Vries']));
    $club->addMember(User::factory()->create(['first_name' => 'Zoe', 'name_prefix' => null, 'last_name' => 'Bakker']));

    Livewire::actingAs($owner)
        ->test(MembersIndex::class, ['club' => $club])
        ->set('sort', 'last_name')
        ->assertSeeInOrder(['Zoe Bakker', 'Anna de Vries'])
        ->set('search', 'Vries')
        ->assertSee('Anna de Vries')
        ->assertDontSee('Zoe Bakker');
});
