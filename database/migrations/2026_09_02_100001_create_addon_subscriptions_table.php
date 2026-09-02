<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('price_cents');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->date('next_billing_at')->nullable();
            $table->string('mollie_mandate_id')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'addon_id', 'status']);
            $table->index(['status', 'next_billing_at']);
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->string('platform_mollie_customer_id')->nullable()->after('mollie_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('platform_mollie_customer_id');
        });

        Schema::dropIfExists('addon_subscriptions');
    }
};
