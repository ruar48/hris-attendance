<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\PayrollSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(): Response
    {
        $holidays = Holiday::query()
            ->orderByDesc('is_recurring')
            ->orderBy('month')
            ->orderBy('day')
            ->orderByDesc('date')
            ->get()
            ->map(fn (Holiday $holiday) => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'is_recurring' => (bool) $holiday->is_recurring,
                'month' => $holiday->month,
                'day' => $holiday->day,
                'date' => $holiday->date?->toDateString(),
                'display' => $holiday->displayLabel(),
                'type' => $holiday->type,
                'pay_multiplier' => (float) $holiday->pay_multiplier,
            ]);

        return Inertia::render('holidays/index', [
            'holidays' => $holidays,
            'default_multiplier' => PayrollSetting::float('holiday_pay_multiplier'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isRecurring = $request->boolean('is_recurring');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_recurring' => ['required', 'boolean'],
            'type' => ['required', 'in:regular,special'],
            'pay_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'month' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:1', 'max:12'],
            'day' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:1', 'max:31'],
            'date' => [Rule::requiredIf(! $isRecurring), 'nullable', 'date', 'unique:holidays,date'],
        ]);

        if ($isRecurring) {
            $exists = Holiday::query()
                ->where('is_recurring', true)
                ->where('month', $data['month'])
                ->where('day', $data['day'])
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'month' => 'A yearly holiday already exists for that month and day.',
                ]);
            }

            Holiday::query()->create([
                'name' => $data['name'],
                'is_recurring' => true,
                'month' => $data['month'],
                'day' => $data['day'],
                'date' => null,
                'type' => $data['type'],
                'pay_multiplier' => $data['pay_multiplier'],
            ]);

            return back()->with('success', 'Yearly holiday saved. It will apply every year on that month and day.');
        }

        Holiday::query()->create([
            'name' => $data['name'],
            'is_recurring' => false,
            'month' => null,
            'day' => null,
            'date' => $data['date'],
            'type' => $data['type'],
            'pay_multiplier' => $data['pay_multiplier'],
        ]);

        return back()->with('success', 'One-time holiday saved for that specific date.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
