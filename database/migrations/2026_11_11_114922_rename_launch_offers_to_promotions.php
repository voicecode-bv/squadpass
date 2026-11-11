<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The opening offer became a general promotion that runs under any name
     * (such as Black Friday), so the table and the club columns lose their
     * opening-offer names.
     */
    public function up(): void
    {
        Schema::rename('launch_offers', 'promotions');

        Schema::table('clubs', function (Blueprint $table) {
            $table->renameColumn('founding_discount_percent', 'promotion_discount_percent');
            $table->renameColumn('founding_claimed_at', 'promotion_claimed_at');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->renameIndex('clubs_founding_claimed_at_index', 'clubs_promotion_claimed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->renameIndex('clubs_promotion_claimed_at_index', 'clubs_founding_claimed_at_index');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->renameColumn('promotion_discount_percent', 'founding_discount_percent');
            $table->renameColumn('promotion_claimed_at', 'founding_claimed_at');
        });

        Schema::rename('promotions', 'launch_offers');
    }
};
