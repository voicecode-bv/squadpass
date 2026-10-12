<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clubs can upload a photo, shown on the club cards under "My clubs".
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
