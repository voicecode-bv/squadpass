<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_staff', function (Blueprint $table) {
            // Areas a staff member may use (App\Enums\StaffPermission values); owners may use everything.
            $table->json('permissions')->nullable()->after('role');
        });

        Schema::table('club_invitations', function (Blueprint $table) {
            // Permissions handed to an invited staff member once they accept.
            $table->json('permissions')->nullable()->after('role');
        });

        // Existing admins kept full day-to-day access; preserve that.
        DB::table('club_staff')
            ->where('role', 'admin')
            ->update(['permissions' => json_encode(['members', 'check_ins', 'lessons', 'billing', 'forms', 'tickets', 'setup'])]);
    }

    public function down(): void
    {
        Schema::table('club_staff', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });

        Schema::table('club_invitations', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
