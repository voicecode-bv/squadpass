<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strip_card_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('strips');
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['club_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strip_card_plans');
    }
};
