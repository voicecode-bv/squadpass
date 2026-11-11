<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The name customers see for the promotion, so the same discount can run as
     * an opening offer, Black Friday or any other campaign.
     */
    public function up(): void
    {
        Schema::table('launch_offers', function (Blueprint $table) {
            $table->string('name', 60)->default('')->after('id');
        });

        DB::table('launch_offers')->update(['name' => 'Black Friday']);
    }

    public function down(): void
    {
        Schema::table('launch_offers', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
