<?php

namespace App\Livewire\Clubs;

use App\Enums\StaffPermission;
use App\Models\Club;
use App\Services\CheckIns\QrCodeRenderer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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

    public bool $self_signup = true;

    /** Members cancelling later than this many hours before a lesson keep no credit. */
    public int $cancellation_deadline_hours = 3;

    /** Whether granted credits expire at all. */
    public bool $credits_expire = true;

    /** Days after which granted credits expire, when they expire. */
    public int $credit_expiry_days = 30;

    /** Demo club flag, only editable by superadmins. */
    public bool $is_demo = false;

    /**
     * The tab each field lives on; the first tab is the default.
     *
     * @var array<string, list<string>>
     */
    public const array TAB_FIELDS = [
        'general' => ['name', 'photo', 'is_demo'],
        'registration' => ['self_signup'],
        'lessons' => ['cancellation_deadline_hours', 'credits_expire', 'credit_expiry_days'],
        'integrations' => [],
    ];

    /** Active tab, kept in the URL so a tab can be linked to directly. */
    #[Url]
    public string $tab = 'general';

    public function mount(Club $club): void
    {
        $this->authorize('access', [$club, StaffPermission::ClubDetails]);

        $this->club = $club;
        $this->name = $club->name;
        $this->self_signup = $club->self_signup ?? true;
        $this->is_demo = $club->is_demo;
        $this->cancellation_deadline_hours = $club->cancellation_deadline_hours;
        $this->credits_expire = $club->credit_expiry_days !== null;
        $this->credit_expiry_days = $club->credit_expiry_days ?? 30;

        if (! array_key_exists($this->tab, self::TAB_FIELDS)) {
            $this->tab = array_key_first(self::TAB_FIELDS);
        }
    }

    public function save(): void
    {
        $this->authorize('access', [$this->club, StaffPermission::ClubDetails]);

        try {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'photo' => ['nullable', 'image', 'max:4096'],
                'self_signup' => ['boolean'],
                'is_demo' => ['boolean'],
                'cancellation_deadline_hours' => ['required', 'integer', 'min:0', 'max:168'],
                'credits_expire' => ['boolean'],
                'credit_expiry_days' => ['exclude_unless:credits_expire,true', 'required', 'integer', 'min:1', 'max:730'],
            ]);
        } catch (ValidationException $exception) {
            $this->showTabWithError($exception);

            throw $exception;
        }

        $attributes = [
            'name' => $validated['name'],
            'self_signup' => $validated['self_signup'],
            'cancellation_deadline_hours' => $validated['cancellation_deadline_hours'],
            'credit_expiry_days' => $validated['credits_expire'] ? $validated['credit_expiry_days'] : null,
        ];

        if ($this->photo) {
            if ($this->club->photo_path) {
                Storage::disk('public')->delete($this->club->photo_path);
            }

            $attributes['photo_path'] = $this->photo->store('club-photos', 'public');
        }

        if (Gate::allows('manage-platform')) {
            $this->club->is_demo = $validated['is_demo'];
        }

        $this->club->fill($attributes)->save();

        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Club settings saved.'));
    }

    /**
     * The join link as a print-ready QR code, for posters and flyers.
     */
    public function downloadJoinQrCode(QrCodeRenderer $renderer): StreamedResponse
    {
        $this->authorize('access', [$this->club, StaffPermission::ClubDetails]);

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
        $this->authorize('access', [$this->club, StaffPermission::ClubDetails]);

        if ($this->club->photo_path) {
            Storage::disk('public')->delete($this->club->photo_path);
            $this->club->update(['photo_path' => null]);
        }

        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Club photo removed.'));
    }

    /**
     * The form spans several tabs; switch to the tab of the first invalid
     * field so the error is never hidden on a tab that is not shown.
     */
    protected function showTabWithError(ValidationException $exception): void
    {
        $firstField = array_key_first($exception->errors());

        foreach (self::TAB_FIELDS as $tab => $fields) {
            if (in_array($firstField, $fields, true)) {
                $this->tab = $tab;

                return;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.clubs.settings');
    }
}
