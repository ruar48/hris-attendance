<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Position and Salary Type from the client's "201 Files" Compensation &
 * Payroll Details sheet, matched by employee_code. Basic Salary was blank
 * in the source sheet, so it's left untouched here.
 */
class EmployeeCompensationSeeder extends Seeder
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function employees(): array
    {
        return [
            '25150' => ['position' => 'Shift supervisor', 'salary_type' => 'monthly', 'tax_status' => 'S', 'bank_account_status' => 'active'],
            '25151' => ['position' => 'Shift supervisor', 'salary_type' => 'monthly', 'tax_status' => 'S1', 'bank_account_status' => 'active'],
            '25152' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25153' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25154' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25155' => ['position' => 'Checker', 'salary_type' => 'semi_monthly'],
            '25156' => ['position' => 'Checker', 'salary_type' => 'semi_monthly'],
            '25157' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25158' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25159' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25160' => ['position' => 'Opa/OFD', 'salary_type' => 'semi_monthly'],
            '25161' => ['position' => 'Opa/OFD', 'salary_type' => 'semi_monthly', 'bank_account_status' => 'active'],
            '25162' => ['position' => 'Checker', 'salary_type' => 'semi_monthly'],
            '25163' => ['position' => 'Checker', 'salary_type' => 'semi_monthly'],
            '25164' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25165' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25166' => ['position' => 'Yard/Utility', 'salary_type' => 'semi_monthly'],
            '25167' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25168' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25169' => ['position' => 'Opa/OFD', 'salary_type' => 'semi_monthly'],
            '25170' => ['position' => 'Checker/TL', 'salary_type' => 'semi_monthly', 'bank_account_status' => 'active'],
            '25171' => ['position' => 'Checker', 'salary_type' => 'semi_monthly'],
            '25172' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25173' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25174' => ['position' => 'Yard/Utility', 'salary_type' => 'semi_monthly'],
            '25175' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25176' => ['position' => 'Opa/OFD', 'salary_type' => 'semi_monthly', 'bank_account_status' => 'active'],
            '25177' => ['position' => 'Checker', 'salary_type' => 'semi_monthly'],
            '25178' => ['position' => 'Checker', 'salary_type' => 'semi_monthly'],
            '25179' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25180' => ['position' => 'Forklift Operator', 'salary_type' => 'semi_monthly'],
            '25181' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25182' => ['position' => 'Yardman', 'salary_type' => 'semi_monthly'],
            '25183' => ['position' => 'Secretary/Assistant', 'salary_type' => 'semi_monthly'],
            '25184' => ['position' => 'Driver/Reliever', 'salary_type' => 'weekly'],
        ];
    }

    public function run(): void
    {
        foreach (static::employees() as $code => $data) {
            Employee::query()
                ->where('employee_code', $code)
                ->update($data);
        }
    }
}
