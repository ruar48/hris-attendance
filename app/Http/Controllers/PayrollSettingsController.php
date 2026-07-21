<?php

namespace App\Http\Controllers;

use App\Models\PayrollSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('payroll-settings/index', [
            'settings' => PayrollSetting::allValues(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'undertime_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'undertime_deduction_unit' => ['required', 'in:exact,30_minutes,hour'],

            'ot_minimum_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'ot_rate_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'ot_use_employee_hourly' => ['required', 'boolean'],
            'ot_fixed_hourly_rate' => ['required', 'numeric', 'min:0'],

            'sunday_route_default_amount' => ['required', 'numeric', 'min:0'],
            'sunday_route_use_employee_rate' => ['required', 'boolean'],

            'holiday_pay_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],

            'cash_advance_max_deduction' => ['required', 'numeric', 'min:0'],
            'cash_advance_auto_deduct' => ['required', 'boolean'],

            'auto_13th_month' => ['required', 'boolean'],
            'thirteenth_month_divisor' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        PayrollSetting::syncMany([
            ...$data,
            'ot_use_employee_hourly' => $data['ot_use_employee_hourly'] ? '1' : '0',
            'sunday_route_use_employee_rate' => $data['sunday_route_use_employee_rate'] ? '1' : '0',
            'cash_advance_auto_deduct' => $data['cash_advance_auto_deduct'] ? '1' : '0',
            'auto_13th_month' => $data['auto_13th_month'] ? '1' : '0',
        ]);

        return back()->with('success', 'Payroll settings saved.');
    }
}
