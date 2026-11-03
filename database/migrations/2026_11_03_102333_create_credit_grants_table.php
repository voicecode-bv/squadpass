<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A batch of credits with its own expiry date; subscriptions.credits_remaining is the sum of the unexpired remainders.
        Schema::create('credit_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('amount');
            $table->unsignedSmallInteger('remaining');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Spending picks the grant that expires first.
            $table->index(['subscription_id', 'expires_at']);
            // The expiry run only touches grants that still hold credits.
            $table->index(['remaining', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_grants');
    }
};
