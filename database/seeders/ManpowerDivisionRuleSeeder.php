<?php

namespace Database\Seeders;

use App\Models\ManpowerDivisionRule;
use Illuminate\Database\Seeder;

class ManpowerDivisionRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            ['division' => 'Inbound', 'minimum_shift' => 1, 'reason' => null],
            ['division' => 'Outbound', 'minimum_shift' => 2, 'reason' => 'Dispatch & Handover operational window starts at 15:00'],
        ];

        foreach ($rules as $rule) {
            ManpowerDivisionRule::query()->updateOrCreate(
                ['division' => $rule['division']],
                $rule,
            );
        }
    }
}
