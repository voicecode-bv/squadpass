<?php

use App\Enums\StaffRole;
use App\Livewire\Lessons\Form;
use App\Livewire\Rooms\Index;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);
});

test('staff can create a room', function () {
    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('name', 'Baan 1')
        ->set('description', 'Squashbaan')
        ->call('saveRoom')
        ->assertHasNoErrors();

    expect(Room::query()->where('club_id', $this->club->id)->where('name', 'Baan 1')->exists())->toBeTrue();
});

test('room names are unique per club but not across clubs', function () {
    Room::factory()->for($this->club)->create(['name' => 'Baan 1']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('name', 'Baan 1')
        ->call('saveRoom')
        ->assertHasErrors(['name']);

    expect(Room::factory()->create(['name' => 'Baan 1']))->not->toBeNull();
});

test('a lesson can be scheduled in a room', function () {
    $room = Room::factory()->for($this->club)->create();

    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club])
        ->set('name', 'Squash beginners')
        ->set('capacity', 8)
        ->set('room_id', $room->id)
        ->call('save')
        ->assertHasNoErrors();

    $lesson = Lesson::query()->firstOrFail();

    expect($lesson->room_id)->toBe($room->id);
});

test('a room from another club cannot be used', function () {
    $foreignRoom = Room::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club])
        ->set('name', 'Squash beginners')
        ->set('capacity', 8)
        ->set('room_id', $foreignRoom->id)
        ->call('save')
        ->assertHasErrors(['room_id']);

    expect(Lesson::query()->count())->toBe(0);
});

test('non-staff cannot manage rooms', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class, ['club' => $this->club])
        ->assertForbidden();
});

test('staff can edit a room', function () {
    $room = Room::factory()->create([
        'club_id' => $this->club->id,
        'name' => 'Baan 1',
        'description' => 'Binnen',
    ]);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('editRoom', $room->id)
        ->assertSet('name', 'Baan 1')
        ->set('name', 'Baan 1 (verwarmd)')
        ->set('description', 'Binnen, verwarmd')
        ->call('saveRoom')
        ->assertHasNoErrors();

    expect($room->refresh()->name)->toBe('Baan 1 (verwarmd)')
        ->and($room->description)->toBe('Binnen, verwarmd')
        ->and(Room::query()->where('club_id', $this->club->id)->count())->toBe(1);
});

test('editing keeps the name unique within the club', function () {
    Room::factory()->create(['club_id' => $this->club->id, 'name' => 'Baan 1']);
    $second = Room::factory()->create(['club_id' => $this->club->id, 'name' => 'Baan 2']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('editRoom', $second->id)
        ->set('name', 'Baan 1')
        ->call('saveRoom')
        ->assertHasErrors(['name' => 'unique']);

    expect($second->refresh()->name)->toBe('Baan 2');
});

test('a room of another club cannot be edited', function () {
    $otherRoom = Room::factory()->create();

    expect(fn () => Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('editRoom', $otherRoom->id)
    )->toThrow(ModelNotFoundException::class);
});
