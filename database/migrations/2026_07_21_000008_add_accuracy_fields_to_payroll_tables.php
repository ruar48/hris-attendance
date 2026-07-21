<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // A day whose punch pair is unusable (missing in or out). It earns
            // nothing and is not counted as worked until HR corrects it by DTR.
            $table->boolean('is_incomplete')->default(false)->after('source');
            $table->unsignedInteger('worked_minutes')->default(0)->after('is_incomplete');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->unsignedSmallInteger('absent_days')->default(0)->after('basic_pay');
            $table->decimal('absence_deduction', 12, 2)->default(0)->after('undertime_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['is_incomplete', 'worked_minutes']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['absent_days', 'absence_deduction']);
        });
    }
};
