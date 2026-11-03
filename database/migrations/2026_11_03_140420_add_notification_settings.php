<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Members get a reminder email this many hours before a lesson; NULL means no reminders.
            $table->unsignedTinyInteger('lesson_reminder_hours')->nullable()->after('credit_expiry_days');
        });

        Schema::table('users', function (Blueprint $table) {
            // Members can opt out of lesson reminder emails.
            $table->boolean('lesson_reminders')->default(true)->after('avatar_path');
        });

        Schema::table('lesson_occurrences', function (Blueprint $table) {
            // Set once the reminders for this occurrence went out, so nobody gets two.
            $table->timestamp('reminders_sent_at')->nullable()->after('booked_count');
            $table->index(['reminders_sent_at', 'starts_at']);
        });

        Schema::table('credit_grants', function (Blueprint $table) {
            // Set once the member was warned that these credits are about to expire.
            $table->timestamp('expiry_warned_at')->nullable()->after('expires_at');
            $table->index(['expiry_warned_at', 'expires_at']);
        });

        Schema::table('news_posts', function (Blueprint $table) {
            // Email the club's members once the post is published.
            $table->boolean('notify_members')->default(false)->after('pinned');
            $table->timestamp('members_notified_at')->nullable()->after('notify_members');
            $table->index(['notify_members', 'members_notified_at', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->dropIndex(['notify_members', 'members_notified_at', 'published_at']);
            $table->dropColumn(['notify_members', 'members_notified_at']);
        });

        Schema::table('credit_grants', function (Blueprint $table) {
            $table->dropIndex(['expiry_warned_at', 'expires_at']);
            $table->dropColumn('expiry_warned_at');
        });

        Schema::table('lesson_occurrences', function (Blueprint $table) {
            $table->dropIndex(['reminders_sent_at', 'starts_at']);
            $table->dropColumn('reminders_sent_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lesson_reminders');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('lesson_reminder_hours');
        });
    }
};
