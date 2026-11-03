<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Hex colour such as #e11d48 that replaces SquadPass blue for this club; NULL keeps blue.
            $table->char('brand_color', 7)->nullable()->after('logo_dark_path');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('brand_color');
        });
    }
};
