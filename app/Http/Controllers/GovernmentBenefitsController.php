<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Services\PayrollCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GovernmentBenefitsController extends Controller
{
    public function index(Request $request): Response
    {
        $years = PayrollPeriod::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();

        $defaultYear = $years[0] ?? (int) now()->year;
        $year = (int) $request->integer('year', $defaultYear);

        if ($years !== [] && ! in_array($year, $years, true)) {
            $year = $defaultYear;
        }

        $periods = PayrollPeriod::query()
            ->with(['latestRun.payslips'])
            ->where('year', $year)
            ->where('half', '!=', PayrollCalculator::THIRTEENTH_MONTH_HALF)
            ->orderByDesc('month')
            ->orderByDesc('half')
            ->get()
            ->map(function (PayrollPeriod $period) {
                $payslips = $period->latestRun?->payslips ?? collect();

                $sss = (float) $payslips->sum('sss');
                $philhealth = (float) $payslips->sum('philhealth');
                $pagibig = (float) $payslips->sum('pagibig');

                return [
                    'id' => $period->id,
                    'name' => $period->name,
                    'month' => (int) $period->month,
                    'half' => (int) ($period->half ?? 1),
                    'type' => $this->periodType($period),
                    'start_date' => $period->start_date?->toDateString(),
                    'end_date' => $period->end_date?->toDateString(),
                    'employees' => $payslips->count(),
                    'sss' => round($sss, 2),
                    'philhealth' => round($philhealth, 2),
                    'pagibig' => round($pagibig, 2),
                    'total' => round($sss + $philhealth + $pagibig, 2),
                    'status' => $period->status,
                ];
            });

        $totalSss = round($periods->sum('sss'), 2);
        $totalPhilhealth = round($periods->sum('philhealth'), 2);
        $totalPagibig = round($periods->sum('pagibig'), 2);

        return Inertia::render('government-benefits/index', [
            'filters' => [
                'year' => $year,
            ],
            'years' => $years,
            'settings' => [
                'sss_rate' => (float) PayrollSetting::getValue('sss_rate'),
                'philhealth_rate' => (float) PayrollSetting::getValue('philhealth_rate'),
                'pagibig_fixed' => (float) PayrollSetting::getValue('pagibig_fixed'),
            ],
            'summary' => [
                'sss' => $totalSss,
                'philhealth' => $totalPhilhealth,
                'pagibig' => $totalPagibig,
                'total' => round($totalSss + $totalPhilhealth + $totalPagibig, 2),
                'cutoffs' => $periods->count(),
            ],
            'periods' => $periods->values(),
        ]);
    }

    protected function periodType(PayrollPeriod $period): string
    {
        $half = (int) ($period->half ?? 1);

        return $half === 2 ? '2nd_kinsena' : '1st_kinsena';
    }
}
