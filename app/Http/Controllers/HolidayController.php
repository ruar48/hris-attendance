<?php

namespace App\Http\Controllers;

use App\Models\DtrLog;
use App\Models\Holiday;
use App\Models\OptionList;
use App\Models\PayrollSetting;
use App\Services\AttendanceResolver;
use Carbon\Carbon;
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
                'site' => $holiday->site,
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
            'sites' => OptionList::values('work_location'),
        ]);
    }

    public function store(Request $request, AttendanceResolver $resolver): RedirectResponse
    {
        $isRecurring = $request->boolean('is_recurring');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // Empty: nationwide. Otherwise a local holiday for that one site.
            'site' => ['nullable', Rule::in(OptionList::values('work_location'))],
            'is_recurring' => ['required', 'boolean'],
            'type' => ['required', 'in:regular,special'],
            'pay_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'month' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:1', 'max:12'],
            'day' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:1', 'max:31'],
            'date' => [
                Rule::requiredIf(! $isRecurring), 'nullable', 'date',
                Rule::unique('holidays', 'date')->where(fn ($q) => $request->input('site') ? $q->where('site', $request->input('site')) : $q->whereNull('site')),
            ],
        ]);

        if ($isRecurring) {
            $exists = Holiday::query()
                ->where('is_recurring', true)
                ->where('month', $data['month'])
                ->where('day', $data['day'])
                ->where(fn ($q) => ($data['site'] ?? null) ? $q->where('site', $data['site']) : $q->whereNull('site'))
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'month' => ($data['site'] ?? null)
                        ? "A yearly {$data['site']} holiday already exists for that month and day."
                        : 'A yearly nationwide holiday already exists for that month and day.',
                ]);
            }

            $holiday = Holiday::query()->create([
                'name' => $data['name'],
                'site' => $data['site'] ?? null,
                'is_recurring' => true,
                'month' => $data['month'],
                'day' => $data['day'],
                'date' => null,
                'type' => $data['type'],
                'pay_multiplier' => $data['pay_multiplier'],
            ]);
            $this->refreshEncodedDays($holiday, $resolver);

            return back()->with('success', 'Yearly holiday saved. It will apply every year on that month and day.');
        }

        $holiday = Holiday::query()->create([
            'name' => $data['name'],
            'site' => $data['site'] ?? null,
            'is_recurring' => false,
            'month' => null,
            'day' => null,
            'date' => $data['date'],
            'type' => $data['type'],
            'pay_multiplier' => $data['pay_multiplier'],
        ]);
        $this->refreshEncodedDays($holiday, $resolver);

        return back()->with('success', 'One-time holiday saved for that specific date.');
    }

    public function destroy(Holiday $holiday, AttendanceResolver $resolver): RedirectResponse
    {
        $holiday->delete();
        $this->refreshEncodedDays($holiday, $resolver);

        return back()->with('success', 'Holiday removed.');
    }

    /**
     * Recompute the time sheet days that fall on a holiday that was just
     * added or removed, so their holiday pay changes straight away rather
     * than at the next payroll run.
     */
    protected function refreshEncodedDays(Holiday $holiday, AttendanceResolver $resolver): void
    {
        DtrLog::query()
            ->with('employee')
            ->when(
                $holiday->is_recurring,
                fn ($query) => $query->whereMonth('work_date', $holiday->month)->whereDay('work_date', $holiday->day),
                fn ($query) => $query->whereDate('work_date', $holiday->date?->toDateString()),
            )
            ->get()
            ->each(function (DtrLog $log) use ($resolver) {
                if ($log->employee) {
                    $date = Carbon::parse($log->work_date);
                    $resolver->resolveDay($log->employee, $date, Holiday::findForDate($date));
                }
            });
    }
}
