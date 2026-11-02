<?php

namespace App\Livewire;

use App\Models\Club;
use App\Models\User;
use App\Services\Impersonation\Impersonation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A short, step-by-step tour of how SquadPass works. It opens by itself for
 * new users until they finish or dismiss it, and anyone can reopen it from
 * the profile menu. Staff get the club-management tour, everyone else the
 * member tour. Steps are only built while the tour is open, so the
 * component costs no queries on regular page loads.
 */
class ProductTour extends Component
{
    public bool $open = false;

    public function mount(): void
    {
        // Staff looking around as a member must not use up that member's first-time tour.
        $this->open = Auth::user()->product_tour_completed_at === null && ! Impersonation::isActive();
    }

    #[On('open-product-tour')]
    public function show(): void
    {
        $this->open = true;
    }

    /**
     * Fires whenever the modal closes, by finishing, skipping or dismissing it.
     */
    public function complete(): void
    {
        $this->open = false;

        $user = Auth::user();

        if ($user->product_tour_completed_at === null && ! Impersonation::isActive()) {
            $user->forceFill(['product_tour_completed_at' => now()])->save();
        }
    }

    /**
     * Go to the page a step points at; the tour counts as seen.
     */
    public function visit(int $index): void
    {
        $url = $this->steps[$index]['url'] ?? null;

        abort_if($url === null, 404);

        $this->complete();

        $this->redirect($url, navigate: true);
    }

    /**
     * @return array<int, array{icon: string, title: string, body: string, url: string|null, action: string|null}>
     */
    #[Computed]
    public function steps(): array
    {
        $user = Auth::user();

        $steps = $this->isStaff($user)
            ? $this->staffSteps($user)
            : $this->memberSteps($user);

        return [
            $this->step(
                'sparkles',
                __('Welcome to SquadPass'),
                __('SquadPass brings members, lessons, check-ins and payments together in one place. This short tour shows you how it works.'),
            ),
            ...$steps,
            $this->step(
                'rocket-launch',
                __('You are all set'),
                __('You can view this tour again at any time via your profile menu, under "How SquadPass works".'),
            ),
        ];
    }

    private function isStaff(User $user): bool
    {
        return $user->is_superadmin || $user->staffClubs()->exists();
    }

    /**
     * @return array<int, array{icon: string, title: string, body: string, url: string|null, action: string|null}>
     */
    private function staffSteps(User $user): array
    {
        /** @var Club|null $club */
        $club = $user->staffClubs()->orderBy('name')->first();

        $route = fn (string $name): ?string => $club ? route($name, $club) : null;

        return [
            $this->step(
                'building-storefront',
                __('Set up your club'),
                __('Every club has its own dashboard and settings. The set-up wizard helps you add rooms, trainers, a first lesson and your prices in a few minutes.'),
                $club && $user->can('update', $club) ? route('clubs.onboarding', $club) : $route('clubs.dashboard'),
                __('Open set-up'),
            ),
            $this->step(
                'users',
                __('Members and trainers'),
                __('Invite members and trainers to your club. Everyone gets their own portal and app to view lessons, cards and visits.'),
                $route('clubs.members'),
                __('View members'),
            ),
            $this->step(
                'calendar-days',
                __('Lessons and schedule'),
                __('Create recurring lessons per room and assign a trainer. Members enroll themselves and you see in the schedule who is coming.'),
                $route('clubs.schedule'),
                __('View schedule'),
            ),
            $this->step(
                'credit-card',
                __('Strip cards and subscriptions'),
                __('Sell strip cards and subscriptions. Members pay online and you keep track of all payments and invoices in one overview.'),
                $route('clubs.subscription-plans'),
                __('View plans'),
            ),
            $this->step(
                'finger-print',
                __('Check-in desk'),
                __('Members check in with the QR code in their app, at the desk or on a kiosk screen. Every visit is recorded and the right card or subscription is charged.'),
                $route('clubs.check-ins'),
                __('Open check-in desk'),
            ),
        ];
    }

    /**
     * Members get links to their portal; users without a membership yet only get the explanation.
     *
     * @return array<int, array{icon: string, title: string, body: string, url: string|null, action: string|null}>
     */
    private function memberSteps(User $user): array
    {
        $hasMembership = $user->memberships()->exists();

        $route = fn (string $name): ?string => $hasMembership ? route($name) : null;

        return [
            $this->step(
                'calendar-days',
                __('Find a lesson'),
                __('Browse the lesson schedule of your club and enroll with a single click. Your enrollments are always under "My enrollments".'),
                $route('portal.schedule'),
                __('View lesson schedule'),
            ),
            $this->step(
                'ticket',
                __('Strip cards and subscriptions'),
                __('Buy a strip card or view your subscription. You always see how many strips or credits you have left.'),
                $route('portal.cards'),
                __('View my strip cards'),
            ),
            $this->step(
                'qr-code',
                __('Check in with your QR code'),
                __('Show the QR code in the app at the desk, or scan the code on the kiosk screen. Your visits appear under "My visits".'),
                $route('portal.history'),
                __('View my visits'),
            ),
            $this->step(
                'lifebuoy',
                __('Forms and support'),
                __('Fill in and sign forms from your club, and ask your questions via a support ticket.'),
                $route('portal.tickets'),
                __('View support'),
            ),
        ];
    }

    /**
     * @return array{icon: string, title: string, body: string, url: string|null, action: string|null}
     */
    private function step(string $icon, string $title, string $body, ?string $url = null, ?string $action = null): array
    {
        return [
            'icon' => $icon,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'action' => $url ? $action : null,
        ];
    }

    public function render(): View
    {
        return view('livewire.product-tour');
    }
}
