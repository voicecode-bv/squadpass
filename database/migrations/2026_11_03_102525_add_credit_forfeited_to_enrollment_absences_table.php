<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_absences', function (Blueprint $table) {
            // Set when the member reported absent after the cancellation deadline: the credit stays spent,
            // so rejoining that lesson costs no new credit.
            $table->boolean('credit_forfeited')->default(false)->after('lesson_occurrence_id');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_absences', function (Blueprint $table) {
            $table->dropColumn('credit_forfeited');
        });
    }
};
