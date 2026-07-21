<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('last_working_day')->nullable()->after('hire_date');
        });

        // Anyone already archived is paid through the day they were archived,
        // so payroll never has to fall back to deleted_at.
        DB::table('employees')
            ->whereNotNull('deleted_at')
            ->whereNull('last_working_day')
            ->update(['last_working_day' => DB::raw('date(deleted_at)')]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('last_working_day');
        });
    }
};
