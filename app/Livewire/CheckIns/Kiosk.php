<?php

namespace App\Livewire\CheckIns;

use App\Models\Club;
use App\Services\CheckIns\CheckInCode;
use App\Services\CheckIns\QrCodeRenderer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The screen a club puts at its entrance: a rotating QR code that members scan
 * with the SquadPass app to check themselves in.
 *
 * A successful scan is confirmed over the club's private broadcast channel, so
 * the screen holds no check-in history of its own.
 */
#[Layout('layouts.kiosk')]
#[Title('Check-in QR code')]
class Kiosk extends Component
{
    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    /**
     * The QR code for the current time window. The view polls well within
     * CheckInCode::PERIOD_SECONDS, so the screen never shows a stale code.
     */
    #[Computed]
    public function qrCodeSvg(): string
    {
        return app(QrCodeRenderer::class)->svg(
            app(CheckInCode::class)->forClub($this->club)
        );
    }

    public function render(): View
    {
        return view('livewire.check-ins.kiosk', [
            'markWidth' => round(QrCodeRenderer::LOGO_RATIO * 100, 1).'%',
        ]);
    }
}
