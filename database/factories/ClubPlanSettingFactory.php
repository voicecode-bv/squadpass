<?php

namespace Database\Factories;

use App\Enums\ClubPlan;
use App\Models\ClubPlanSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubPlanSetting>
 */
class ClubPlanSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan' => ClubPlan::Starter,
            'price_cents' => ClubPlan::Starter->defaultPriceCents(),
            'member_limit' => ClubPlan::Starter->defaultMemberLimit(),
        ];
    }
}
