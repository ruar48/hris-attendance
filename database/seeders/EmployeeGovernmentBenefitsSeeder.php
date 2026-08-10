<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * SSS / PhilHealth / Pag-IBIG / TIN numbers from the client's "201 Files"
 * Government Benefits Details sheet, matched by employee_code.
 *
 * Only rows 25167-25187 are seeded here: those were given as plain pasted
 * text and could be transcribed exactly. Rows 25150-25166 were only visible
 * in a screenshot, and long ID numbers are too easy to misread from an
 * image to seed with confidence — paste that range as text (like this
 * block) to have it imported too.
 */
class EmployeeGovernmentBenefitsSeeder extends Seeder
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function employees(): array
    {
        return [
            ['code' => '25167', 'position' => 'Yardman', 'pagibig' => '09-25053938-0', 'remarks' => 'INCOMPLETE REQ.'],
            ['code' => '25168', 'position' => 'Yardman', 'pagibig' => '1211-8678-1875', 'remarks' => 'RESIGNED'],
            ['code' => '25169', 'position' => 'Opa/OFD', 'sss' => '04-1964844-8', 'philhealth' => '21-025418365-7', 'pagibig' => '1211-7358-6099', 'tin' => '334-658-615'],
            ['code' => '25170', 'position' => 'Checker/TL', 'sss' => '04-2331698-6', 'philhealth' => '09-050285252-2', 'pagibig' => '1210-0340-9323', 'tin' => '406-406-709'],
            ['code' => '25171', 'position' => 'Checker', 'sss' => '04-5230173-9', 'philhealth' => '08-252829801-6', 'remarks' => 'INCOMPLETE REQ.'],
            ['code' => '25172', 'position' => 'Forklift Operator', 'sss' => '04-3254189-4', 'philhealth' => '08-025145338-5', 'pagibig' => '1211-5110-1272', 'tin' => '257-874-650'],
            ['code' => '25173', 'position' => 'Forklift Operator', 'sss' => '34-6271544-8', 'philhealth' => '2102-5446-3927', 'pagibig' => '1211-8253-7839', 'tin' => '755-042-402'],
            ['code' => '25174', 'position' => 'Yard/Utility', 'sss' => '04-5320208-6', 'philhealth' => '08-026492010-1', 'remarks' => 'INCOMPLETE NO RECORD FOUND PHILHEALTH'],
            ['code' => '25175', 'position' => 'Yardman', 'sss' => '04-1334983-7', 'philhealth' => '09-050214645-8', 'pagibig' => '149000768342', 'tin' => '253-1916871-000'],
            ['code' => '25176', 'position' => 'Opa/OFD', 'sss' => '04-4524696-6', 'philhealth' => '08-026957961-0', 'pagibig' => '1213-5494-9724', 'tin' => '621-822-348-000'],
            ['code' => '25177', 'position' => 'Checker', 'sss' => '04-4481385-7', 'philhealth' => '08-026996862-9', 'pagibig' => '9230-1259-1272', 'tin' => '667-392-785'],
            ['code' => '25178', 'position' => 'Checker', 'sss' => '09-3052487-6', 'philhealth' => '17-050193893-1', 'pagibig' => '1211-9302-0437', 'tin' => '335-362-344-000'],
            ['code' => '25179', 'position' => 'Forklift Operator', 'sss' => '04-3572070-4', 'philhealth' => '08-026077123-3', 'pagibig' => '1211654486632', 'tin' => '326-561-938-000'],
            ['code' => '25180', 'position' => 'Forklift Operator', 'sss' => '05-1154109-0', 'philhealth' => '10-202123473-4', 'pagibig' => '1211-4054-4450', 'tin' => '282-329-205-000', 'remarks' => 'ENDO'],
            ['code' => '25181', 'position' => 'Yardman', 'sss' => '04-3886798-3', 'philhealth' => '01-052236150-5', 'pagibig' => '1210-5694-9724'],
            ['code' => '25182', 'position' => 'Yardman', 'sss' => '04-2472132-1', 'philhealth' => '0805-11479685', 'pagibig' => '1210-5694-9724', 'tin' => '08-051147968-5'],
            ['code' => '25183', 'position' => 'Secretary/Assistant', 'sss' => '01-2566317-3', 'philhealth' => '05250709666-2', 'pagibig' => '121166145065', 'tin' => '486-936-447-000'],
            ['code' => '25184', 'position' => 'Driver/Reliever', 'sss' => '34-4295060-5', 'philhealth' => '080256490181', 'pagibig' => '121108832347'],
            ['code' => '25185', 'last' => 'Joson', 'first' => 'Isagani', 'remarks' => 'RESIGNED', 'status' => 'inactive'],
            ['code' => '25186', 'last' => 'Albina', 'first' => 'Julieto', 'remarks' => 'RESIGNED', 'status' => 'inactive'],
            ['code' => '25187', 'last' => 'Mojica', 'first' => 'Clark', 'remarks' => 'RESIGNED', 'status' => 'inactive'],
        ];
    }

    public function run(): void
    {
        foreach (static::employees() as $row) {
            $data = array_filter([
                'position' => $row['position'] ?? null,
                'sss_number' => $row['sss'] ?? null,
                'philhealth_number' => $row['philhealth'] ?? null,
                'pagibig_number' => $row['pagibig'] ?? null,
                'tin_number' => $row['tin'] ?? null,
                'government_benefits_remarks' => $row['remarks'] ?? null,
                'status' => $row['status'] ?? null,
            ], fn ($value) => $value !== null);

            if (isset($row['first'], $row['last'])) {
                $data['first_name'] = $row['first'];
                $data['last_name'] = $row['last'];
            }

            Employee::query()->updateOrCreate(['employee_code' => $row['code']], $data);
        }
    }
}
