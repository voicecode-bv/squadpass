<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The club's payment overview lists payments newest first, optionally per
     * status. The existing (club_id, user_id) index keeps backing the foreign
     * key, so these can be dropped again safely on MySQL.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['club_id', 'created_at']);
            $table->index(['club_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'status', 'created_at']);
            $table->dropIndex(['club_id', 'created_at']);
        });
    }
};
