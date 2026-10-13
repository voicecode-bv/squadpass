<?php

use App\Enums\ClubPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price and member limit per SquadPass plan, editable by superadmins.
     * Seeded with the current defaults so nothing changes on deploy.
     */
    public function up(): void
    {
        Schema::create('club_plan_settings', function (Blueprint $table) {
            $table->id();
            $table->string('plan', 20)->unique();
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedInteger('member_limit')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('club_plan_settings')->insert(array_map(fn (ClubPlan $plan): array => [
            'plan' => $plan->value,
            'price_cents' => $plan->defaultPriceCents(),
            'member_limit' => $plan->defaultMemberLimit(),
            'created_at' => $now,
            'updated_at' => $now,
        ], ClubPlan::cases()));
    }

    public function down(): void
    {
        Schema::dropIfExists('club_plan_settings');
    }
};
