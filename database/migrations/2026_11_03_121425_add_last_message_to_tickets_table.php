<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Set by every new message; tickets from before unread tracking stay NULL, so they never show up as unread.
            $table->timestamp('last_message_at')->nullable()->after('priority');
            $table->foreignId('last_message_by')->nullable()->after('last_message_at')->constrained('users')->nullOnDelete();

            $table->index(['club_id', 'last_message_at']);
            $table->index(['user_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_message_by');
            $table->dropIndex(['club_id', 'last_message_at']);
            $table->dropIndex(['user_id', 'last_message_at']);
            $table->dropColumn('last_message_at');
        });
    }
};
