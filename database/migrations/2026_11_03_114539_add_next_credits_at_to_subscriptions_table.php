<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Credits are added every month from the start date, whatever the billing interval.
            $table->date('next_credits_at')->nullable()->after('next_billing_at');
            $table->index('next_credits_at');
        });

        // Running subscriptions get their next monthly credit date, counted from their start date.
        $today = Carbon::today();

        DB::table('subscriptions')
            ->whereIn('status', ['active', 'cancelled', 'past_due'])
            ->select(['id', 'starts_at'])
            ->chunkById(500, function ($subscriptions) use ($today): void {
                foreach ($subscriptions as $subscription) {
                    $startsAt = Carbon::parse($subscription->starts_at)->startOfDay();
                    $months = 1;

                    while ($startsAt->copy()->addMonthsNoOverflow($months)->lte($today)) {
                        $months++;
                    }

                    DB::table('subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['next_credits_at' => $startsAt->copy()->addMonthsNoOverflow($months)->toDateString()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['next_credits_at']);
            $table->dropColumn('next_credits_at');
        });
    }
};
