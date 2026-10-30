<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Night differential: the minutes of a day worked between 10 PM and
     * 6 AM, the premium they earn, and its line on the payslip.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('night_minutes')->default(0)->after('ot_minutes');
            $table->decimal('night_diff_pay', 12, 2)->default(0)->after('ot_pay');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('night_diff_pay', 12, 2)->default(0)->after('overtime_pay');
            $table->unsignedInteger('unpaid_leave_days')->default(0)->after('absent_days');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['night_diff_pay', 'unpaid_leave_days']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['night_minutes', 'night_diff_pay']);
        });
    }
};
