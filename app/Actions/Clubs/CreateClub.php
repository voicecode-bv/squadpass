<?php

namespace App\Actions\Clubs;

use App\Enums\StaffRole;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateClub
{
    /**
     * Create a club with a unique slug and make the given user its owner.
     */
    public function handle(string $name, User $owner): Club
    {
        return DB::transaction(function () use ($name, $owner): Club {
            $club = Club::create([
                'name' => $name,
                'slug' => $this->generateSlug($name),
            ]);

            $club->addStaff($owner, StaffRole::Owner);

            return $club;
        });
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
