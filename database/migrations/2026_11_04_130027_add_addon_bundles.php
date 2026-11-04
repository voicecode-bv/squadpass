<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bundles are add-on rows without a key that unlock the add-ons listed in
     * addon_bundle_items. A first payment can be lowered by the unused value of
     * the add-ons a bundle replaces; that discount is kept in credit_cents.
     */
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->string('key', 50)->nullable()->change();
        });

        Schema::create('addon_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('addons')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();

            $table->unique(['bundle_id', 'addon_id']);
        });

        Schema::table('addon_payments', function (Blueprint $table) {
            $table->unsignedInteger('credit_cents')->default(0)->after('amount_cents');
        });
    }

    /**
     * Bundles cannot exist without a nullable key, so they (and their
     * subscriptions, through the cascading foreign keys) go with the rollback.
     */
    public function down(): void
    {
        Schema::table('addon_payments', function (Blueprint $table) {
            $table->dropColumn('credit_cents');
        });

        Schema::dropIfExists('addon_bundle_items');

        DB::table('addons')->whereNull('key')->delete();

        Schema::table('addons', function (Blueprint $table) {
            $table->string('key', 50)->nullable(false)->change();
        });
    }
};
