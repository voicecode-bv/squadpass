<?php

namespace App\Livewire\Clubs;

use App\Models\Club;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Club settings')]
class Settings extends Component
{
    public Club $club;

    public string $name = '';

    public string $timezone = '';

    public string $mollie_api_key = '';

    public string $moneybird_access_token = '';

    public string $moneybird_administration_id = '';

    public bool $self_signup = true;

    public function mount(Club $club): void
    {
        $this->authorize('update', $club);

        $this->club = $club;
        $this->name = $club->name;
        $this->timezone = $club->timezone;
        $this->mollie_api_key = $club->mollie_api_key ?? '';
        $this->moneybird_access_token = $club->moneybird_access_token ?? '';
        $this->moneybird_administration_id = $club->moneybird_administration_id ?? '';
        $this->self_signup = $club->self_signup ?? true;
    }

    public function save(): void
    {
        $this->authorize('update', $this->club);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'mollie_api_key' => ['nullable', 'string', 'starts_with:test_,live_'],
            'moneybird_access_token' => ['nullable', 'string'],
            'moneybird_administration_id' => ['nullable', 'string', 'max:30'],
            'self_signup' => ['boolean'],
        ]);

        $this->club->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'mollie_api_key' => $validated['mollie_api_key'] ?: null,
            'moneybird_access_token' => $validated['moneybird_access_token'] ?: null,
            'moneybird_administration_id' => $validated['moneybird_administration_id'] ?: null,
            'self_signup' => $validated['self_signup'],
        ]);

        Flux::toast(variant: 'success', text: __('Club settings saved.'));
    }

    public function render(): View
    {
        return view('livewire.clubs.settings');
    }
}
