<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->text('moneybird_access_token')->nullable();
            $table->string('moneybird_administration_id')->nullable();
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->string('moneybird_contact_id')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('moneybird_invoice_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('moneybird_invoice_id');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('moneybird_contact_id');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['moneybird_access_token', 'moneybird_administration_id']);
        });
    }
};
