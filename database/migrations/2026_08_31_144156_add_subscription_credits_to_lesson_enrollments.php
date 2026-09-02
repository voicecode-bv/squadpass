<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_enrollments', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('credits_used')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('credits_used');
        });
    }
};
