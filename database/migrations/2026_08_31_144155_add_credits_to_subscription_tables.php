<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // NULL means unlimited; otherwise the number of lesson credits per billing period.
            $table->unsignedSmallInteger('credits')->nullable()->after('interval');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // NULL means unlimited (mirrors the plan at activation / period start).
            $table->unsignedSmallInteger('credits_remaining')->nullable()->after('next_billing_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('credits');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('credits_remaining');
        });
    }
};
