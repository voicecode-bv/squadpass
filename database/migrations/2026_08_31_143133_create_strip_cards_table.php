<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strip_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strip_card_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('strips_total');
            $table->unsignedSmallInteger('strips_remaining');
            $table->date('purchased_at');
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strip_cards');
    }
};
