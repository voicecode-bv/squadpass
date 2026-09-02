<?php

use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\PlatformMollieWebhookController;
use App\Livewire\Admin;
use App\Livewire\CheckIns;
use App\Livewire\Clubs;
use App\Livewire\Dashboard;
use App\Livewire\Invitations;
use App\Livewire\Lessons;
use App\Livewire\Members;
use App\Livewire\Portal;
use App\Livewire\Rooms;
use App\Livewire\StripCardPlans;
use App\Livewire\SubscriptionPlans;
use App\Livewire\Subscriptions;
use App\Livewire\Tickets;
use App\Livewire\Trainers;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('webhooks/mollie-platform', PlatformMollieWebhookController::class)->name('webhooks.mollie.platform');
Route::post('webhooks/mollie/{club}', MollieWebhookController::class)->name('webhooks.mollie');

Route::livewire('invitations/{token}', Invitations\Accept::class)->name('invitations.accept');
Route::livewire('join/{club}', Clubs\Join::class)->name('clubs.join');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('clubs', Clubs\Index::class)->name('clubs.index');

    Route::livewire('admin/addons', Admin\Addons::class)->name('admin.addons');

    Route::prefix('clubs/{club}')->scopeBindings()->group(function () {
        Route::livewire('dashboard', Clubs\Dashboard::class)->name('clubs.dashboard');
        Route::livewire('onboarding', Clubs\Onboarding::class)->name('clubs.onboarding');
        Route::livewire('settings', Clubs\Settings::class)->name('clubs.settings');
        Route::livewire('addons', Clubs\Addons::class)->name('clubs.addons');
        Route::livewire('moneybird', Clubs\Moneybird::class)->name('clubs.moneybird');
        Route::livewire('members', Members\Index::class)->name('clubs.members');
        Route::livewire('members/{membership}', Members\Show::class)->name('clubs.members.show');
        Route::livewire('trainers', Trainers\Index::class)->name('clubs.trainers');
        Route::livewire('rooms', Rooms\Index::class)->name('clubs.rooms');
        Route::livewire('lessons', Lessons\Index::class)->name('clubs.lessons');
        Route::livewire('lessons/create', Lessons\Form::class)->name('clubs.lessons.create');
        Route::livewire('lessons/{lesson}/edit', Lessons\Form::class)->name('clubs.lessons.edit');
        Route::livewire('schedule', Lessons\Schedule::class)->name('clubs.schedule');
        Route::livewire('occurrences/{occurrence}', Lessons\OccurrenceShow::class)->name('clubs.occurrences.show');
        Route::livewire('strip-cards', StripCardPlans\Index::class)->name('clubs.strip-cards');
        Route::livewire('subscription-plans', SubscriptionPlans\Index::class)->name('clubs.subscription-plans');
        Route::livewire('subscriptions', Subscriptions\Index::class)->name('clubs.subscriptions');
        Route::livewire('check-ins', CheckIns\Desk::class)->name('clubs.check-ins');
        Route::livewire('tickets', Tickets\Index::class)->name('clubs.tickets');
        Route::livewire('tickets/{ticket}', Tickets\Show::class)->name('clubs.tickets.show');
    });

    Route::prefix('my')->group(function () {
        Route::livewire('lessons', Trainers\MyLessons::class)->name('trainer.lessons');
        Route::livewire('schedule', Portal\Schedule::class)->name('portal.schedule');
        Route::livewire('enrollments', Portal\MyEnrollments::class)->name('portal.enrollments');
        Route::livewire('cards', Portal\MyCards::class)->name('portal.cards');
        Route::livewire('cards/buy', Portal\BuyStripCard::class)->name('portal.cards.buy');
        Route::livewire('subscriptions', Portal\Subscriptions::class)->name('portal.subscriptions');
        Route::livewire('history', Portal\History::class)->name('portal.history');
        Route::livewire('tickets', Portal\Tickets::class)->name('portal.tickets');
        Route::livewire('tickets/{ticket}', Portal\TicketShow::class)->name('portal.tickets.show');
    });
});

require __DIR__.'/settings.php';
