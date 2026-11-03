<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only credit timeline per member.
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_grant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            // Signed change to the balance; 0 for a forfeited credit that was already spent.
            $table->smallInteger('amount');
            $table->unsignedSmallInteger('balance_after');
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_occurrence_id')->nullable()->constrained()->nullOnDelete();
            // No foreign key: enrollments are deleted on cancel, the history stays.
            $table->unsignedBigInteger('lesson_enrollment_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['club_id', 'user_id', 'id']);
            $table->index(['lesson_enrollment_id', 'credit_grant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
