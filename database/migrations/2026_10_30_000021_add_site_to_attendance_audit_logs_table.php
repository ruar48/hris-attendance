<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the site (Manning work location) each change was made for, so a
     * change still shows the right site after the person moves to another.
     */
    public function up(): void
    {
        Schema::table('attendance_audit_logs', function (Blueprint $table) {
            $table->string('site')->nullable()->after('employee_id');
            $table->index('site');
        });

        // Earlier changes: use the site the person is at now.
        DB::table('attendance_audit_logs')
            ->whereNull('site')
            ->update([
                'site' => DB::raw('(select work_location from employees where employees.id = attendance_audit_logs.employee_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('attendance_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['site']);
            $table->dropColumn('site');
        });
    }
};
