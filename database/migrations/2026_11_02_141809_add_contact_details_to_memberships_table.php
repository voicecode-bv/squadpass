<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contact details the club keeps for a member, e.g. from an imported member
     * list. Stored per membership so one club never overwrites another's data.
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('ended_at');
            $table->date('date_of_birth')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['phone', 'date_of_birth']);
        });
    }
};
