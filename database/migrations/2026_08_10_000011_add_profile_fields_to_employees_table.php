<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Personal details
            $table->string('gender')->nullable()->after('last_name');
            $table->string('marital_status')->nullable()->after('gender');
            $table->date('date_of_birth')->nullable()->after('marital_status');
            $table->string('place_of_birth')->nullable()->after('date_of_birth');
            $table->string('nationality')->nullable()->after('place_of_birth');
            $table->string('religion')->nullable()->after('nationality');
            $table->string('contact_number')->nullable()->after('religion');
            $table->text('address')->nullable()->after('contact_number');

            // Emergency contact
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_number')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_address')->nullable()->after('emergency_contact_number');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_address');

            // Employment details
            $table->string('immediate_superior')->nullable()->after('department');
            $table->date('regularization_date')->nullable()->after('immediate_superior');
            $table->date('separation_date')->nullable()->after('regularization_date');
            $table->string('division')->nullable()->after('separation_date');
            $table->string('job_level')->nullable()->after('division');
            $table->string('employment_status')->nullable()->after('job_level');
            $table->string('work_location')->nullable()->after('employment_status');
            $table->string('shift_schedule')->nullable()->after('work_location');
            $table->string('time_in_schedule')->nullable()->after('shift_schedule');
            $table->string('time_out_schedule')->nullable()->after('time_in_schedule');

            // Compensation & payroll
            $table->string('salary_type')->nullable()->after('hourly_rate');
            $table->string('tax_status')->nullable()->after('salary_type');
            $table->decimal('rice_allowance', 10, 2)->nullable()->after('tax_status');
            $table->decimal('transpo_allowance', 10, 2)->nullable()->after('rice_allowance');
            $table->decimal('meal_allowance', 10, 2)->nullable()->after('transpo_allowance');
            $table->decimal('de_minimis_allowance', 10, 2)->nullable()->after('meal_allowance');
            $table->string('bank_name')->nullable()->after('de_minimis_allowance');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_status')->nullable()->after('bank_account_number');

            // Government benefits
            $table->string('sss_number')->nullable()->after('bank_account_status');
            $table->string('philhealth_number')->nullable()->after('sss_number');
            $table->string('pagibig_number')->nullable()->after('philhealth_number');
            $table->string('tin_number')->nullable()->after('pagibig_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'marital_status', 'date_of_birth', 'place_of_birth', 'nationality', 'religion',
                'contact_number', 'address',
                'emergency_contact_name', 'emergency_contact_number', 'emergency_contact_address',
                'emergency_contact_relationship',
                'immediate_superior', 'regularization_date', 'separation_date', 'division', 'job_level',
                'employment_status', 'work_location', 'shift_schedule', 'time_in_schedule', 'time_out_schedule',
                'salary_type', 'tax_status', 'rice_allowance', 'transpo_allowance', 'meal_allowance',
                'de_minimis_allowance', 'bank_name', 'bank_account_number', 'bank_account_status',
                'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number',
            ]);
        });
    }
};
