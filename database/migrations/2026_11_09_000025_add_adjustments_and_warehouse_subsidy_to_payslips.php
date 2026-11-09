<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amounts HR enters on a processed payslip:
     *  - Adjustments (reimbursement, allowance) are added to net pay.
     *  - Warehouse subsidy (forklift diesel and each warehouse's
     *    disbursement) is released with the payslip but listed under net
     *    pay, separate from salary.
     */
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('reimbursement', 12, 2)->default(0)->after('total_deductions');
            $table->decimal('allowance', 12, 2)->default(0)->after('reimbursement');
            $table->decimal('total_adjustments', 12, 2)->default(0)->after('allowance');

            $table->decimal('forklift_diesel', 12, 2)->default(0)->after('net_pay');
            $table->decimal('disbursement_batangas', 12, 2)->default(0)->after('forklift_diesel');
            $table->decimal('disbursement_dasma', 12, 2)->default(0)->after('disbursement_batangas');
            $table->decimal('disbursement_san_pablo', 12, 2)->default(0)->after('disbursement_dasma');
            $table->decimal('disbursement_tayabas', 12, 2)->default(0)->after('disbursement_san_pablo');
            $table->decimal('disbursement_taguig', 12, 2)->default(0)->after('disbursement_tayabas');
            $table->decimal('warehouse_subsidy', 12, 2)->default(0)->after('disbursement_taguig');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn([
                'reimbursement',
                'allowance',
                'total_adjustments',
                'forklift_diesel',
                'disbursement_batangas',
                'disbursement_dasma',
                'disbursement_san_pablo',
                'disbursement_tayabas',
                'disbursement_taguig',
                'warehouse_subsidy',
            ]);
        });
    }
};
