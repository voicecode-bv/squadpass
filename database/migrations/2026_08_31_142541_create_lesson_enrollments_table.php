<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_occurrence_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Fixed enrollments: one per user per lesson. Single enrollments have a NULL lesson_id.
            $table->unique(['lesson_id', 'user_id']);
            // Single enrollments: one per user per occurrence. Fixed enrollments have a NULL occurrence id.
            $table->unique(['lesson_occurrence_id', 'user_id']);
            $table->index(['club_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_enrollments');
    }
};
