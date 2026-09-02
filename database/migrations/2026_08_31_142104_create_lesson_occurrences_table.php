<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('scheduled');
            $table->unsignedSmallInteger('capacity_override')->nullable();
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->timestamps();

            $table->unique(['lesson_id', 'starts_at']);
            $table->index(['club_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_occurrences');
    }
};
