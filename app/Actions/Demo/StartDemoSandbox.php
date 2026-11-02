<?php

namespace App\Actions\Demo;

use App\Actions\Clubs\CreateClub;
use App\Models\Club;
use App\Models\User;
use Database\Seeders\DemoClubSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartDemoSandbox
{
    /** Hours a sandbox lives before the prune deletes it. */
    public const int LIFETIME_HOURS = 24;

    /** Fake members per sandbox: enough to fill the overviews and charts. */
    public const int MEMBER_COUNT = 45;

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

        return DB::transaction(function () use ($expiresAt): array {
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
    }
}
