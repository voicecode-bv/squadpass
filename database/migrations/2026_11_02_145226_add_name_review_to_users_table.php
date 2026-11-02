<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * name_confirmed_at stays empty for names that were split automatically
     * (existing users, imported members) until the person saves their own
     * name. The index serves sorting and prefix searching on last name.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('name_confirmed_at')->nullable()->after('last_name');
            $table->index(['last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_name', 'first_name']);
            $table->dropColumn('name_confirmed_at');
        });
    }
};
