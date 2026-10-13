<?php

use App\Enums\ClubPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->date('trial_ends_at')->nullable()->after('onboarded_at');
        });

        // Existing clubs get the same trial from go-live, so nobody is locked out overnight.
        DB::table('clubs')->update(['trial_ends_at' => now()->addDays(ClubPlan::TRIAL_DAYS)->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });
    }
};
