<?php

namespace App\Exceptions;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use RuntimeException;

/**
 * Guards against processing the same cutoff twice, which would issue duplicate
 * payslips and deduct cash advance balances a second time.
 */
class PayrollAlreadyProcessed extends RuntimeException
{
    public function __construct(
        public readonly PayrollPeriod $period,
        public readonly PayrollRun $run
    ) {
        parent::__construct("{$period->name} was already processed on ".
            ($run->processed_at?->toDayDateTimeString() ?? 'an earlier run').'.');
    }
}
