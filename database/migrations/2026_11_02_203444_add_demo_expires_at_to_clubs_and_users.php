<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demo sandboxes (a club plus its fake users, one per landing page
     * visitor) carry an expiry, so the hourly prune can delete them in bulk.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->timestamp('demo_expires_at')->nullable()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('demo_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['demo_expires_at']);
            $table->dropColumn('demo_expires_at');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex(['demo_expires_at']);
            $table->dropColumn('demo_expires_at');
        });
    }
};
