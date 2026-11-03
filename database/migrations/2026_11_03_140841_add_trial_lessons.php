<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Visitors can book a trial lesson on the public trial page.
            $table->boolean('trial_lessons_enabled')->default(false)->after('self_signup');
        });

        Schema::table('lessons', function (Blueprint $table) {
            // Shown on the public trial page; the price is charged through Mollie, NULL means free.
            $table->boolean('trial_enabled')->default(false)->after('active');
            $table->unsignedInteger('trial_price_cents')->nullable()->after('trial_enabled');
        });

        Schema::table('memberships', function (Blueprint $table) {
            // Kept after converting to a member, so reports can show how many trials convert.
            $table->date('trial_started_at')->nullable()->after('ended_at');
            $table->index(['club_id', 'trial_started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'trial_started_at']);
            $table->dropColumn('trial_started_at');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['trial_enabled', 'trial_price_cents']);
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('trial_lessons_enabled');
        });
    }
};
