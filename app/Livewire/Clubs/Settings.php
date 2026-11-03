<?php

namespace App\Livewire\Clubs;

use App\Enums\StaffPermission;
use App\Models\Club;
use App\Services\BrandPalette;
use App\Services\CheckIns\QrCodeRenderer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
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

    /** Newly picked light-mode logo (also used in emails), stored on save. */
    public ?TemporaryUploadedFile $logo = null;

    /** Newly picked dark-mode logo, stored on save. */
    public ?TemporaryUploadedFile $darkLogo = null;

    public string $name = '';

    public bool $self_signup = true;

    /** Visitors can book a trial lesson on the public trial page. */
    public bool $trial_lessons_enabled = false;

    /** Members cancelling later than this many hours before a lesson keep no credit. */
    public int $cancellation_deadline_hours = 3;

    /** Whether granted credits expire at all. */
    public bool $credits_expire = true;

    /** Days after which granted credits expire, when they expire. */
    public int $credit_expiry_days = 30;

    /** Whether members get an email before their lessons. */
    public bool $lesson_reminders = false;

    /** Hours before a lesson the reminder email goes out. */
    public int $lesson_reminder_hours = 24;

    /** Whether the club replaces SquadPass blue with its own colour. */
    public bool $custom_brand_color = false;

    /** The club's brand colour as #rrggbb. */
    public string $brand_color = BrandPalette::DEFAULT_COLOR;

    /** Demo club flag, only editable by superadmins. */
    public bool $is_demo = false;

    /**
     * The tab each field lives on; the first tab is the default.
     *
     * @var array<string, list<string>>
     */
    public const array TAB_FIELDS = [
        'general' => ['name', 'photo', 'logo', 'darkLogo', 'custom_brand_color', 'brand_color', 'is_demo'],
        'registration' => ['self_signup', 'trial_lessons_enabled'],
        'lessons' => ['cancellation_deadline_hours', 'credits_expire', 'credit_expiry_days', 'lesson_reminders', 'lesson_reminder_hours'],
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
        $this->trial_lessons_enabled = $club->trial_lessons_enabled;
        $this->is_demo = $club->is_demo;
        $this->cancellation_deadline_hours = $club->cancellation_deadline_hours;
        $this->credits_expire = $club->credit_expiry_days !== null;
        $this->credit_expiry_days = $club->credit_expiry_days ?? 30;
        $this->lesson_reminders = $club->lesson_reminder_hours !== null;
        $this->lesson_reminder_hours = $club->lesson_reminder_hours ?? 24;
        $this->custom_brand_color = $club->brand_color !== null;
        $this->brand_color = $club->brand_color ?? BrandPalette::DEFAULT_COLOR;

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
                // Email clients do not reliably render SVG or WebP, so only bitmap formats they all support.
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif', 'max:2048'],
                'darkLogo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif', 'max:2048'],
                'self_signup' => ['boolean'],
                'trial_lessons_enabled' => ['boolean'],
                'is_demo' => ['boolean'],
                'cancellation_deadline_hours' => ['required', 'integer', 'min:0', 'max:168'],
                'credits_expire' => ['boolean'],
                'credit_expiry_days' => ['exclude_unless:credits_expire,true', 'required', 'integer', 'min:1', 'max:730'],
                'lesson_reminders' => ['boolean'],
                'custom_brand_color' => ['boolean'],
                'brand_color' => ['exclude_unless:custom_brand_color,true', 'required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'lesson_reminder_hours' => ['exclude_unless:lesson_reminders,true', 'required', 'integer', 'min:1', 'max:72'],
            ]);
        } catch (ValidationException $exception) {
            $this->showTabWithError($exception);

            throw $exception;
        }

        $attributes = [
            'name' => $validated['name'],
            'self_signup' => $validated['self_signup'],
            'trial_lessons_enabled' => $validated['trial_lessons_enabled'],
            'cancellation_deadline_hours' => $validated['cancellation_deadline_hours'],
            'credit_expiry_days' => $validated['credits_expire'] ? $validated['credit_expiry_days'] : null,
            'lesson_reminder_hours' => $validated['lesson_reminders'] ? $validated['lesson_reminder_hours'] : null,
            'brand_color' => $validated['custom_brand_color'] ? strtolower($validated['brand_color']) : null,
        ];

        if ($this->photo) {
            if ($this->club->photo_path) {
                Storage::disk('public')->delete($this->club->photo_path);
            }

            $attributes['photo_path'] = $this->photo->store('club-photos', 'public');
        }

        foreach (['logo' => 'logo_path', 'darkLogo' => 'logo_dark_path'] as $property => $column) {
            if ($this->{$property}) {
                if ($this->club->{$column}) {
                    Storage::disk('public')->delete($this->club->{$column});
                }

                $attributes[$column] = $this->{$property}->store('club-logos', 'public');
            }
        }

        if (Gate::allows('manage-platform')) {
            $this->club->is_demo = $validated['is_demo'];
        }

        $this->club->fill($attributes)->save();

        $this->photo = null;
        $this->logo = null;
        $this->darkLogo = null;

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

    public function deleteLogo(): void
    {
        $this->authorize('access', [$this->club, StaffPermission::ClubDetails]);

        if ($this->club->logo_path) {
            Storage::disk('public')->delete($this->club->logo_path);
            $this->club->update(['logo_path' => null]);
        }

        $this->logo = null;

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    public function deleteDarkLogo(): void
    {
        $this->authorize('access', [$this->club, StaffPermission::ClubDetails]);

        if ($this->club->logo_dark_path) {
            Storage::disk('public')->delete($this->club->logo_dark_path);
            $this->club->update(['logo_dark_path' => null]);
        }

        $this->darkLogo = null;

        Flux::toast(variant: 'success', text: __('Logo removed.'));
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

    /**
     * Palette preview of the picked colour; SquadPass blue while the picker holds no valid colour.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function brandPreview(): array
    {
        return BrandPalette::fromHex(BrandPalette::isValidHex($this->brand_color) ? $this->brand_color : BrandPalette::DEFAULT_COLOR);
    }

    public function render(): View
    {
        return view('livewire.clubs.settings');
    }
}
