<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The opening offer: the first clubs to take out a plan get a lasting
     * discount (founding price). A single row, managed by superadmins.
     * Seeded active so the offer is live on deploy.
     */
    public function up(): void
    {
        Schema::create('launch_offers', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->unsignedTinyInteger('discount_percent');
            $table->unsignedInteger('spots');
            $table->timestamps();
        });

        $now = now();

        DB::table('launch_offers')->insert([
            'is_active' => true,
            'discount_percent' => 25,
            'spots' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('launch_offers');
    }
};
