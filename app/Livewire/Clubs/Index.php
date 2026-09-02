<?php

namespace App\Livewire\Clubs;

use App\Enums\StaffRole;
use App\Models\Club;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My clubs')]
class Index extends Component
{
    public string $name = '';

    public string $owner_email = '';

    public function createClub(): void
    {
        $this->authorize('create', Club::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email'],
        ]);

        $owner = Auth::user();

        // Superadmins can create a club on behalf of its owner.
        if (Auth::user()->is_superadmin && $validated['owner_email']) {
            $owner = User::query()->where('email', $validated['owner_email'])->first();

            if (! $owner) {
                $this->addError('owner_email', __('No account exists with this email address.'));

                return;
            }
        }

        $club = Club::create([
            'name' => $validated['name'],
            'slug' => $this->generateSlug($validated['name']),
        ]);

        $club->addStaff($owner, StaffRole::Owner);

        $this->redirectRoute('clubs.onboarding', ['club' => $club], navigate: true);
    }

    /**
     * @return Collection<int, Club>
     */
    #[Computed]
    public function clubs(): Collection
    {
        if (Auth::user()->is_superadmin) {
            return Club::query()->orderBy('name')->get();
        }

        return Auth::user()->staffClubs()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.clubs.index');
    }

    protected function generateSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        while (Club::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
