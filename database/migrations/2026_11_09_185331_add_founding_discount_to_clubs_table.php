<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A club that claimed a spot in the opening offer keeps its discount on
     * every plan it takes out, for as long as it stays a customer.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->unsignedTinyInteger('founding_discount_percent')->nullable()->after('trial_ends_at');
            $table->timestamp('founding_claimed_at')->nullable()->index()->after('founding_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex(['founding_claimed_at']);
            $table->dropColumn(['founding_discount_percent', 'founding_claimed_at']);
        });
    }
};
