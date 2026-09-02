<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_occurrence_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lesson_enrollment_id', 'lesson_occurrence_id'], 'absences_enrollment_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_absences');
    }
};
