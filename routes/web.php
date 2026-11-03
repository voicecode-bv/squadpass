<?php

use App\Enums\ClubInvitationRole;
use App\Http\Controllers\CheckInCodeImageController;
use App\Http\Controllers\FeatureLandingController;
use App\Http\Controllers\FormSignatureController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LeaveDemoController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\PlatformMollieWebhookController;
use App\Http\Controllers\SegmentLandingController;
use App\Http\Controllers\SeoController;
use App\Livewire\Admin;
use App\Livewire\CheckIns;
use App\Livewire\Clubs;
use App\Livewire\Dashboard;
use App\Livewire\Forms;
use App\Livewire\Invitations;
use App\Livewire\Invoices;
use App\Livewire\Lessons;
use App\Livewire\Members;
use App\Livewire\News;
use App\Livewire\Portal;
use App\Livewire\Rooms;
use App\Livewire\StripCardPlans;
use App\Livewire\SubscriptionPlans;
use App\Livewire\Subscriptions;
use App\Livewire\Tickets;
use App\Livewire\Trainers;
use App\Mail\ClubInvitationMail;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;
use App\Notifications\CreditsExpiring;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('voor/{segment}', SegmentLandingController::class)->name('segments.show');
Route::get('functies', [FeatureLandingController::class, 'index'])->name('features.index');
Route::get('functies/{feature}', [FeatureLandingController::class, 'show'])->name('features.show');

// Stopping impersonation only needs an authenticated session; the impersonated
// member may well be unverified, which must not lock staff out of their session.
Route::post('impersonate/stop', [ImpersonationController::class, 'destroy'])
    ->middleware('auth')
    ->name('impersonate.stop');

Route::post('demo/leave', LeaveDemoController::class)
    ->middleware('auth')
    ->name('demo.leave');

Route::post('webhooks/mollie-platform', PlatformMollieWebhookController::class)->name('webhooks.mollie.platform');
Route::post('webhooks/mollie/{club}', MollieWebhookController::class)->name('webhooks.mollie');

// The member's own check-in code as a QR image, for the mobile app. The signed,
// seconds-lived code is the only credential the image needs.
Route::get('check-in-code/{code}', CheckInCodeImageController::class)
    ->middleware('throttle:60,1')
    ->name('check-ins.code-image');

Route::livewire('invitations/{token}', Invitations\Accept::class)->name('invitations.accept');
Route::livewire('join/{club}', Clubs\Join::class)->name('clubs.join');
Route::livewire('trial/{club}', Clubs\TrialLesson::class)->name('clubs.trial');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('clubs', Clubs\Index::class)->name('clubs.index');

    Route::livewire('admin/addons', Admin\Addons::class)->name('admin.addons');
    Route::livewire('admin/club-plans', Admin\ClubPlans::class)->name('admin.club-plans');

    Route::prefix('clubs/{club}')->scopeBindings()->group(function () {
        Route::livewire('dashboard', Clubs\Dashboard::class)->name('clubs.dashboard');
        Route::livewire('reports', Clubs\Reports::class)->name('clubs.reports');
        Route::livewire('onboarding', Clubs\Onboarding::class)->name('clubs.onboarding');
        Route::livewire('settings', Clubs\SettingsOverview::class)->name('clubs.settings');
        Route::livewire('settings/club', Clubs\Settings::class)->name('clubs.settings.club');
        Route::livewire('staff', Clubs\Staff::class)->name('clubs.staff');
        Route::livewire('addons', Clubs\Addons::class)->name('clubs.addons');
        Route::livewire('plan', Clubs\Plan::class)->name('clubs.plan');
        Route::livewire('moneybird', Clubs\Moneybird::class)->name('clubs.moneybird');
        Route::livewire('mollie', Clubs\Mollie::class)->name('clubs.mollie');
        Route::livewire('members', Members\Index::class)->name('clubs.members');
        Route::livewire('members/import', Members\Import::class)->name('clubs.members.import');
        Route::livewire('members/{membership}', Members\Show::class)->name('clubs.members.show');
        Route::post('members/{membership}/impersonate', [ImpersonationController::class, 'store'])->name('clubs.members.impersonate');
        Route::post('owners/{owner}/impersonate', [ImpersonationController::class, 'storeOwner'])->name('clubs.owners.impersonate');
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
        Route::livewire('invoices', Invoices\Index::class)->name('clubs.invoices');
        Route::livewire('check-ins', CheckIns\Desk::class)->name('clubs.check-ins');
        Route::livewire('check-ins/kiosk', CheckIns\Kiosk::class)->name('clubs.check-ins.kiosk');
        Route::livewire('forms', Forms\Index::class)->name('clubs.forms');
        Route::livewire('forms/{form}/edit', Forms\Builder::class)->name('clubs.forms.edit');
        Route::livewire('forms/{form}/submissions', Forms\Submissions::class)->name('clubs.forms.submissions');
        Route::livewire('forms/{form}/submissions/{submission}', Forms\SubmissionShow::class)->name('clubs.forms.submissions.show');
        Route::livewire('news', News\Index::class)->name('clubs.news');
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
        Route::livewire('forms', Portal\MyForms::class)->name('portal.forms');
        Route::livewire('forms/{form}', Portal\FormFill::class)->name('portal.forms.fill');
        Route::get('forms/{submission}/signature/{field}', FormSignatureController::class)->name('portal.forms.signature');
        Route::livewire('news', Portal\News::class)->name('portal.news');
        Route::livewire('tickets', Portal\Tickets::class)->name('portal.tickets');
        Route::livewire('tickets/{ticket}', Portal\TicketShow::class)->name('portal.tickets.show');
    });
});

/*
 * Local-only preview of the email template for a club, e.g.
 * /dev/mail-preview/perfect-performance/credits. Nothing is sent or stored.
 */
if (app()->isLocal()) {
    Route::get('dev/mail-preview/{club:slug}/{mail?}', function (Club $club, string $mail = 'credits') {
        return match ($mail) {
            'credits' => (new CreditsExpiring($club, 3, now()->addDays(3)))->toMail(auth()->user() ?? new User),
            'invitation' => new ClubInvitationMail((new ClubInvitation([
                'role' => ClubInvitationRole::Staff,
                'token' => 'preview',
                'expires_at' => now()->addWeek(),
            ]))->setRelation('club', $club)),
            'account' => (new ResetPassword('preview'))->toMail(auth()->user() ?? new User(['email' => 'preview@example.com'])),
            default => abort(404),
        };
    })->name('dev.mail-preview');
}

require __DIR__.'/settings.php';
