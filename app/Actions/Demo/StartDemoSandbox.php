<?php

namespace App\Actions\Demo;

use App\Actions\Clubs\CreateClub;
use App\Models\Club;
use App\Models\User;
use Database\Seeders\DemoClubSeeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StartDemoSandbox
{
    /** Hours a sandbox lives before the prune deletes it. */
    public const int LIFETIME_HOURS = 24;

    /** Fake members per sandbox: enough to fill the overviews and charts. */
    public const int MEMBER_COUNT = 45;

    /** Club photo every sandbox starts with, copied per club so pruning or replacing it never touches another sandbox. */
    public const string PHOTO = 'resources/images/demo-club-photo.jpg';

    public function __construct(
        protected CreateClub $createClub,
        protected DemoClubSeeder $demoClubSeeder,
    ) {}

    /**
     * Create a private demo club for one visitor: an owner account on the
     * undeliverable sandbox domain and a club filled with fake members and
     * history. Club and accounts share one expiry, after which the hourly
     * `demo:prune` removes them.
     *
     * @return array{User, Club}
     */
    public function handle(): array
    {
        $expiresAt = now()->addHours(self::LIFETIME_HOURS);

        [$owner, $club] = DB::transaction(function () use ($expiresAt): array {
            $owner = User::query()->forceCreate([
                'first_name' => 'Demo',
                'last_name' => 'Eigenaar',
                'email' => DemoClubSeeder::demoEmail('Demo Eigenaar', DemoClubSeeder::SANDBOX_EMAIL_DOMAIN),
                'password' => Str::random(64),
                'email_verified_at' => now(),
                'name_confirmed_at' => now(),
                'demo_expires_at' => $expiresAt,
            ]);

            $club = $this->createClub->handle(__('Demo club'), $owner);

            $club->forceFill([
                'is_demo' => true,
                'demo_expires_at' => $expiresAt,
                'onboarded_at' => now()->subMonths(14),
            ])->save();

            $this->demoClubSeeder->forSandbox($expiresAt)->populate($club, $owner, self::MEMBER_COUNT);

            return [$owner, $club];
        });

        $club->update([
            'photo_path' => Storage::disk('public')->putFile('club-photos', new File(base_path(self::PHOTO))),
        ]);

        return [$owner, $club];
    }
}
