<?php

use App\Services\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store first name, prefix and last name separately. users.name stays as
     * the composed full name (kept in sync by the User model) so displaying
     * and searching names keeps working. Existing names are split once.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->default('')->after('name');
            $table->string('name_prefix', 50)->nullable()->after('first_name');
            $table->string('last_name', 100)->default('')->after('name_prefix');
        });

        // One UPDATE ... CASE statement per chunk instead of a query per user.
        DB::table('users')->select(['id', 'name'])->orderBy('id')->chunkById(500, function (Collection $users): void {
            $columns = ['first_name' => [], 'name_prefix' => [], 'last_name' => []];
            $bindings = ['first_name' => [], 'name_prefix' => [], 'last_name' => []];

            foreach ($users as $user) {
                $parts = PersonName::split((string) $user->name);

                foreach ($columns as $column => $cases) {
                    $columns[$column][] = 'WHEN ? THEN ?';
                    $bindings[$column][] = $user->id;
                    $bindings[$column][] = $column === 'name_prefix' ? $parts[$column] : mb_substr($parts[$column], 0, 100);
                }
            }

            $ids = $users->pluck('id')->all();

            DB::update(
                'UPDATE users SET '
                    .collect($columns)->map(fn (array $cases, string $column): string => "{$column} = CASE id ".implode(' ', $cases).' END')->implode(', ')
                    .' WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
                [...$bindings['first_name'], ...$bindings['name_prefix'], ...$bindings['last_name'], ...$ids],
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'name_prefix', 'last_name']);
        });
    }
};
