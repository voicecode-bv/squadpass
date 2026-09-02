<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('trainer_invitations', 'club_invitations');

        Schema::table('club_invitations', function (Blueprint $table) {
            $table->string('role')->default('trainer')->after('email');
        });

        // Add the new index before dropping the old one: MySQL refuses to drop
        // an index that is the last one backing the club_id foreign key.
        Schema::table('club_invitations', function (Blueprint $table) {
            $table->unique(['club_id', 'email', 'role']);
        });

        Schema::table('club_invitations', function (Blueprint $table) {
            $table->dropUnique('trainer_invitations_club_id_email_unique');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->boolean('self_signup')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('self_signup');
        });

        Schema::table('club_invitations', function (Blueprint $table) {
            $table->unique(['club_id', 'email'], 'trainer_invitations_club_id_email_unique');
        });

        Schema::table('club_invitations', function (Blueprint $table) {
            $table->dropUnique(['club_id', 'email', 'role']);
            $table->dropColumn('role');
        });

        Schema::rename('club_invitations', 'trainer_invitations');
    }
};
