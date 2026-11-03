<?php

namespace App\Livewire\Clubs;

use App\Enums\AddonKey;
use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Models\Club;
use App\Models\Form;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The club's settings hub: one card per settings page, with its current status.
 *
 * @property-read array<string, list<array{key: string, title: string, status: string, description: string, url: string}>> $sections
 */
#[Title('Settings')]
class SettingsOverview extends Component
{
    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    /**
     * The cards this user may open, grouped by section in display order.
     *
     * @return array<string, list<array{key: string, title: string, status: string, description: string, url: string}>>
     */
    #[Computed]
    public function sections(): array
    {
        $club = $this->club;
        $canUpdate = Gate::allows('update', $club);
        $addonsUrl = route('clubs.addons', $club);
        $sections = [];

        if ($canUpdate) {
            $sections[__('Club')][] = [
                'key' => 'club',
                'title' => __('Club details'),
                'status' => $club->self_signup ? __('Online registration open') : __('Online registration closed'),
                'description' => __('Name, photo, online registration, cancellation deadline and credit expiry.'),
                'url' => route('clubs.settings.club', $club),
            ];

            $staffCount = $club->staff()->wherePivot('role', StaffRole::Admin->value)->count();

            $sections[__('Club')][] = [
                'key' => 'staff',
                'title' => __('Staff'),
                'status' => $staffCount > 0 ? trans_choice(':count staff member|:count staff members', $staffCount) : __('Only you'),
                'description' => __('Invite colleagues and choose per person what they may do.'),
                'url' => route('clubs.staff', $club),
            ];
        }

        $can = fn (StaffPermission $permission): bool => Gate::allows('access', [$club, $permission]);

        if ($can(StaffPermission::Forms)) {
            $publishedForms = Form::query()->where('club_id', $club->id)->published()->count();

            $sections[__('Club')][] = [
                'key' => 'forms',
                'title' => __('Forms'),
                'status' => $publishedForms > 0 ? trans_choice(':count form published|:count forms published', $publishedForms) : __('No forms published'),
                'description' => __('Enrollment, health and other forms members fill in and sign.'),
                'url' => route('clubs.forms', $club),
            ];
        }

        if ($can(StaffPermission::Setup)) {
            $activeTrainers = $club->trainers()->where('active', true)->count();

            $sections[__('Club')][] = [
                'key' => 'trainers',
                'title' => __('Trainers'),
                'status' => $activeTrainers > 0 ? trans_choice(':count active trainer|:count active trainers', $activeTrainers) : __('No trainers yet'),
                'description' => __('Invite trainers and see who teaches which lessons.'),
                'url' => route('clubs.trainers', $club),
            ];

            $rooms = $club->rooms()->count();

            $sections[__('Club')][] = [
                'key' => 'rooms',
                'title' => __('Rooms'),
                'status' => $rooms > 0 ? trans_choice(':count room|:count rooms', $rooms) : __('No rooms yet'),
                'description' => __('The halls and rooms where your lessons take place.'),
                'url' => route('clubs.rooms', $club),
            ];
        }

        if ($canUpdate) {
            $subscription = $club->currentSubscription();
            $activeAddons = count(array_filter(AddonKey::cases(), fn (AddonKey $key): bool => $club->hasAddon($key)));

            $sections[__('Plan & add-ons')][] = [
                'key' => 'plan',
                'title' => __('SquadPass plan'),
                'status' => match (true) {
                    $subscription !== null => $subscription->plan->label(),
                    $club->isOnTrial() => __('Trial until :date', ['date' => $club->trial_ends_at->format('d-m-Y')]),
                    default => __('No plan'),
                },
                'description' => __('Your SquadPass plan, member limit and billing.'),
                'url' => route('clubs.plan', $club),
            ];

            $sections[__('Plan & add-ons')][] = [
                'key' => 'addons',
                'title' => __('Add-ons'),
                'status' => $activeAddons > 0 ? trans_choice(':count add-on active|:count add-ons active', $activeAddons) : __('No add-ons yet'),
                'description' => __('Extend SquadPass with payments, accounting and more.'),
                'url' => $addonsUrl,
            ];

            $hasMoneybird = $club->hasAddon(AddonKey::Moneybird);
            $moneybirdConnected = $hasMoneybird && $club->hasMoneybirdConfigured();

            $sections[__('Integrations')][] = [
                'key' => 'moneybird',
                'title' => __('Moneybird'),
                'status' => match (true) {
                    ! $hasMoneybird => __('Add-on not active'),
                    $moneybirdConnected => __('Connected'),
                    default => __('Not connected'),
                },
                'description' => __('Invoices and contacts in your Moneybird administration.'),
                'url' => $hasMoneybird ? route('clubs.moneybird', $club) : $addonsUrl,
            ];
        }

        $hasMollie = $club->hasAddon(AddonKey::Mollie);

        if ($canUpdate || ($hasMollie && $can(StaffPermission::Billing))) {
            $mollieConnected = $hasMollie && $club->mollieApiKey() !== null;

            $sections[__('Integrations')][] = [
                'key' => 'mollie',
                'title' => __('Mollie'),
                'status' => match (true) {
                    ! $hasMollie => __('Add-on not active'),
                    ! $mollieConnected => __('Not connected'),
                    $club->mollieIsLive() => __('Live'),
                    default => __('Test mode'),
                },
                'description' => __('Online payments for strip cards and subscriptions.'),
                'url' => $hasMollie ? route('clubs.mollie', $club) : $addonsUrl,
            ];
        }

        return $sections;
    }

    /**
     * All cards in display order, regardless of section.
     *
     * @return list<array{key: string, title: string, status: string, description: string, url: string}>
     */
    #[Computed]
    public function cards(): array
    {
        return array_merge(...array_values($this->sections));
    }

    public function render(): View
    {
        return view('livewire.clubs.settings-overview');
    }
}
