<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The promotion no longer has a limited number of spots: every club that
     * takes out a plan while it runs gets the promotional price.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('spots');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('spots')->default(0)->after('discount_percent');
        });
    }
};
