<?php

namespace App\Livewire\Clubs;

use App\Enums\AddonKey;
use App\Models\Club;
use App\Services\CheckIns\QrCodeRenderer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Club settings')]
class Settings extends Component
{
    use WithFileUploads;

    public Club $club;

    /** Newly picked club photo, stored on save. */
    public ?TemporaryUploadedFile $photo = null;

    public string $name = '';

    public string $mollie_api_key = '';

    public bool $self_signup = true;

    public function mount(Club $club): void
    {
        $this->authorize('update', $club);

        $this->club = $club;
        $this->name = $club->name;
        $this->mollie_api_key = $club->mollie_api_key ?? '';
        $this->self_signup = $club->self_signup ?? true;
    }

    public function save(): void
    {
        $this->authorize('update', $this->club);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'mollie_api_key' => ['nullable', 'string', 'starts_with:test_,live_'],
            'self_signup' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'self_signup' => $validated['self_signup'],
        ];

        if ($this->photo) {
            if ($this->club->photo_path) {
                Storage::disk('public')->delete($this->club->photo_path);
            }

            $attributes['photo_path'] = $this->photo->store('club-photos', 'public');
        }

        // Integration credentials can only be edited while the matching add-on is unlocked.
        if ($this->club->hasAddon(AddonKey::Mollie)) {
            $attributes['mollie_api_key'] = $validated['mollie_api_key'] ?: null;
        }

        $this->club->update($attributes);

        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Club settings saved.'));
    }

    /**
     * The join link as a print-ready QR code, for posters and flyers.
     */
    public function downloadJoinQrCode(QrCodeRenderer $renderer): StreamedResponse
    {
        $this->authorize('update', $this->club);

        $png = $renderer->png(route('clubs.join', $this->club), moduleSize: 20, quietZone: 4);

        return response()->streamDownload(
            function () use ($png): void {
                echo $png;
            },
            'qr-'.$this->club->slug.'.png',
            ['Content-Type' => 'image/png'],
        );
    }

    public function deletePhoto(): void
    {
        $this->authorize('update', $this->club);

        if ($this->club->photo_path) {
            Storage::disk('public')->delete($this->club->photo_path);
            $this->club->update(['photo_path' => null]);
        }

        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Club photo removed.'));
    }

    public function render(): View
    {
        return view('livewire.clubs.settings');
    }
}
