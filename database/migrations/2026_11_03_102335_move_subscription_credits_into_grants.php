<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing balances become one grant per subscription that expires after the
     * default 30 days, so they follow the same expiry rules as new credits.
     */
    public function up(): void
    {
        $now = now();

        DB::table('credit_grants')->insertUsing(
            ['club_id', 'user_id', 'subscription_id', 'amount', 'remaining', 'expires_at', 'created_at', 'updated_at'],
            DB::table('subscriptions')
                ->selectRaw('club_id, user_id, id, credits_remaining, credits_remaining, ?, ?, ?', [$now->copy()->addDays(30), $now, $now])
                ->where('credits_remaining', '>', 0),
        );

        DB::table('credit_transactions')->insertUsing(
            ['club_id', 'user_id', 'subscription_id', 'credit_grant_id', 'type', 'amount', 'balance_after', 'created_at'],
            DB::table('credit_grants')
                ->selectRaw('club_id, user_id, subscription_id, id, ?, amount, amount, created_at', ['granted'])
                ->whereNotExists(fn (Builder $query) => $query
                    ->select(DB::raw(1))
                    ->from('credit_transactions')
                    ->whereColumn('credit_transactions.credit_grant_id', 'credit_grants.id')),
        );
    }

    public function down(): void
    {
        DB::table('credit_transactions')->delete();
        DB::table('credit_grants')->delete();
    }
};
