<?php

namespace Database\Seeders;

use App\Models\OptionList;
use Illuminate\Database\Seeder;

/**
 * The client's "201 Files" Source Data sheet — the picklists that back
 * every dropdown across the Employees section (Employee Status, Employment
 * Status, Company Positions, Departments, Job Levels/Ranks, and Shift &
 * Work Schedules). Editable afterward from the Source Data tab.
 */
class OptionListSeeder extends Seeder
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function rows(): array
    {
        return [
            'employee_status' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'In Active'],
            ],
            'employment_status' => [
                ['value' => 'contractual', 'label' => 'Contractual'],
                ['value' => 'probationary', 'label' => 'Probationary'],
                ['value' => 'regular', 'label' => 'Regular'],
                ['value' => 'on_call', 'label' => 'On Call'],
                ['value' => 'suspended', 'label' => 'Suspended'],
                ['value' => 'awol', 'label' => 'AWOL'],
                ['value' => 'resigned', 'label' => 'Resigned'],
                ['value' => 'end_of_contract', 'label' => 'End of Contract'],
            ],
            'position' => [
                ['value' => 'Shift supervisor', 'label' => 'Shift supervisor'],
                ['value' => 'Opa/OFD', 'label' => 'Opa/OFD'],
                ['value' => 'Checker', 'label' => 'Checker'],
                ['value' => 'Forklift Operator', 'label' => 'Forklift Operator'],
                ['value' => 'Yardman', 'label' => 'Yardman'],
                ['value' => 'Yard/Utility', 'label' => 'Yard/Utility'],
                ['value' => 'Checker/TL', 'label' => 'Checker/TL'],
                ['value' => 'Secretary/Assistant', 'label' => 'Secretary/Assistant'],
                ['value' => 'Driver/Reliever', 'label' => 'Driver/Reliever'],
            ],
            'department' => [
                ['value' => 'Maintenance', 'label' => 'Maintenance'],
            ],
            'job_level' => [
                ['value' => 'L1', 'label' => 'L1 - Executive'],
                ['value' => 'L2', 'label' => 'L2 - Senior Management'],
                ['value' => 'L3', 'label' => 'L3 - Management'],
                ['value' => 'L4', 'label' => 'L4 - Supervisory'],
                ['value' => 'L5', 'label' => 'L5 - Senior Staff'],
                ['value' => 'L6', 'label' => 'L6 - Staff'],
                ['value' => 'L7', 'label' => 'L7 - Junior Staff'],
                ['value' => 'L8', 'label' => 'L8 - Rank & File'],
                ['value' => 'L9', 'label' => 'L9 - Trainee'],
            ],
            'shift_schedule' => [
                ['value' => 'flexible', 'label' => 'Flexible', 'time_in' => null, 'time_out' => null],
                ['value' => 'day', 'label' => 'Day', 'time_in' => '6:00 AM', 'time_out' => '2:00 PM'],
                ['value' => 'mid', 'label' => 'Mid', 'time_in' => '2:00 PM', 'time_out' => '10:00 PM'],
                ['value' => 'night', 'label' => 'Night', 'time_in' => '10:00 PM', 'time_out' => '6:00 AM'],
            ],
        ];
    }

    public function run(): void
    {
        foreach (static::rows() as $category => $items) {
            foreach ($items as $index => $item) {
                OptionList::query()->updateOrCreate(
                    ['category' => $category, 'value' => $item['value']],
                    [
                        'label' => $item['label'],
                        'sort_order' => $index,
                        'time_in' => $item['time_in'] ?? null,
                        'time_out' => $item['time_out'] ?? null,
                    ]
                );
            }
        }
    }
}
