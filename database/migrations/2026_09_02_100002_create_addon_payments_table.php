<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('mollie_payment_id')->unique();
            $table->string('purpose', 20);
            $table->string('status')->default('open');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_payments');
    }
};
