<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->unsignedTinyInteger('day_of_week'); // ISO-8601: 1 = Monday ... 7 = Sunday
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['club_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
