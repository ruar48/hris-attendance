<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            // Per-advance instalment. Null means "use the payroll-settings default".
            $table->decimal('deduction_per_payroll', 12, 2)->nullable()->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('deduction_per_payroll');
        });
    }
};
