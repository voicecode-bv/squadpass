<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the club reports, which aggregate per club over a date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['club_id', 'status', 'paid_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'cancelled_at']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->index(['club_id', 'joined_at']);
            $table->index(['club_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'joined_at']);
            $table->dropIndex(['club_id', 'ended_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'status']);
            $table->dropIndex(['club_id', 'cancelled_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'status', 'paid_at']);
        });
    }
};
