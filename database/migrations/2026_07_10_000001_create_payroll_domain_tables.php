<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->decimal('sunday_route_rate', 12, 2)->default(0);
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->string('biometric_user_id')->nullable()->unique();
            $table->date('hire_date')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('serial_number')->unique();
            $table->string('location')->nullable();
            $table->string('status')->default('online');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('biometric_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('punched_at');
            $table->string('punch_type')->default('in'); // in | out
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
        });

        Schema::create('dtr_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->default('approved'); // pending | approved | rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date')->unique();
            $table->string('type')->default('regular'); // regular | special
            $table->decimal('pay_multiplier', 4, 2)->default(2.00);
            $table->timestamps();
        });

        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->date('released_at');
            $table->string('status')->default('active'); // active | paid
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('source')->default('biometric'); // biometric | dtr
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('ot_minutes')->default(0);
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_sunday')->default(false);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('sunday_pay', 12, 2)->default(0);
            $table->decimal('ot_pay', 12, 2)->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('half')->default(1); // 1 = 1–15, 2 = 16–end (kinsenas)
            $table->date('start_date');
            $table->date('end_date');
            $table->date('cutoff_date')->nullable();
            $table->date('process_start')->nullable();
            $table->date('process_end')->nullable();
            $table->date('payslip_release')->nullable();
            $table->date('payday')->nullable();
            $table->string('status')->default('open'); // open | processing | completed
            $table->timestamps();

            $table->unique(['year', 'month', 'half']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('total_payroll', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_payroll', 14, 2)->default(0);
            $table->string('status')->default('draft'); // draft | in_progress | completed
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('sunday_route', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('thirteenth_month', 12, 2)->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->decimal('cash_advance_deduction', 12, 2)->default(0);
            $table->decimal('sss', 12, 2)->default(0);
            $table->decimal('philhealth', 12, 2)->default(0);
            $table->decimal('pagibig', 12, 2)->default(0);
            $table->decimal('withholding_tax', 12, 2)->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('message');
            $table->string('type')->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('dtr_logs');
        Schema::dropIfExists('biometric_logs');
        Schema::dropIfExists('biometric_devices');
        Schema::dropIfExists('employees');
    }
};
