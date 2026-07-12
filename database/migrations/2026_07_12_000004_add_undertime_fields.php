<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('undertime_minutes')->default(0)->after('late_minutes');
            $table->decimal('undertime_deduction', 12, 2)->default(0)->after('late_deduction');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('undertime_deduction', 12, 2)->default(0)->after('late_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['undertime_minutes', 'undertime_deduction']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn('undertime_deduction');
        });
    }
};
