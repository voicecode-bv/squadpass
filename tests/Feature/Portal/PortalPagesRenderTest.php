<?php

use App\Livewire\Portal\BuyStripCard;
use App\Livewire\Portal\History;
use App\Livewire\Portal\MyCards;
use App\Livewire\Portal\Subscriptions;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\StripCard;
use App\Models\StripCardPlan;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
});

test('the strip cards page shows the card totals', function () {
    StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'strips_total' => 10,
        'strips_remaining' => 7,
    ]);

    Livewire::actingAs($this->member)
        ->test(MyCards::class)
        ->assertOk()
        ->assertSee(__('Usable cards'))
        ->assertSee(__('Strips left'));
});

test('the subscriptions page shows the running subscription', function () {
    $subscription = Subscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'credits_remaining' => 4,
    ]);

    Livewire::actingAs($this->member)
        ->test(Subscriptions::class)
        ->assertOk()
        ->assertSee($subscription->plan->name)
        ->assertSee(__('Active subscriptions'));
});

test('the visits page shows the visit totals', function () {
    CheckIn::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
    ]);

    Livewire::actingAs($this->member)
        ->test(History::class)
        ->assertOk()
        ->assertSee(__('Total visits'))
        ->assertSee(__('Last visit'));
});

test('the buy page lists the strip card plans of the member clubs', function () {
    $plan = StripCardPlan::factory()->create(['club_id' => $this->club->id]);

    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->assertOk()
        ->assertSee($plan->name);
});

test('portal pages render an empty state when the member has nothing yet', function (string $component, string $heading) {
    Livewire::actingAs($this->member)
        ->test($component)
        ->assertOk()
        ->assertSee(__($heading));
})->with([
    [MyCards::class, 'No strip cards yet'],
    [Subscriptions::class, 'No subscriptions available'],
    [History::class, 'No visits yet'],
    [BuyStripCard::class, 'No strip cards available'],
]);
