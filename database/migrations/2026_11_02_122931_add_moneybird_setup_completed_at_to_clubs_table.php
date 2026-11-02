<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->timestamp('moneybird_setup_completed_at')->nullable()->after('moneybird_last_synced_at');
        });

        // Clubs that were already connected before the wizard existed skip it.
        DB::table('clubs')
            ->whereNotNull('moneybird_access_token')
            ->whereNotNull('moneybird_administration_id')
            ->update(['moneybird_setup_completed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('moneybird_setup_completed_at');
        });
    }
};
