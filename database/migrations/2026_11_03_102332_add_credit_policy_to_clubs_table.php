<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Members get their credit back when cancelling at least this many hours before the lesson starts.
            $table->unsignedSmallInteger('cancellation_deadline_hours')->default(3)->after('self_signup');
            // Credits expire this many days after they were granted; NULL means they never expire.
            $table->unsignedSmallInteger('credit_expiry_days')->nullable()->default(30)->after('cancellation_deadline_hours');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['cancellation_deadline_hours', 'credit_expiry_days']);
        });
    }
};
