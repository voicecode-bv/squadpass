<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['club_id', 'name']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('location')->constrained()->nullOnDelete();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('location')->nullable();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });

        Schema::dropIfExists('rooms');
    }
};
