<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Logo variant for dark mode; the light logo (logo_path) is used when empty.
            $table->string('logo_dark_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('logo_dark_path');
        });
    }
};
