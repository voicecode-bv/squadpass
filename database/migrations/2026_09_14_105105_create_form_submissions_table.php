<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Submissions are append-only: filling in a form again inserts a new row
     * and flips is_current on the previous one, so the history stays intact.
     */
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('form_versions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->boolean('is_current')->default(true);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['form_id', 'is_current', 'submitted_at']);
            $table->index(['user_id', 'form_id', 'is_current']);
            $table->index(['club_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
