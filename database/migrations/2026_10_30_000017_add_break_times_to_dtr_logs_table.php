<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The paper DTR has four time columns: AM arrival/departure and PM
     * arrival/departure. time_in and time_out already hold the AM arrival and
     * PM departure; these hold the two in between.
     */
    public function up(): void
    {
        Schema::table('dtr_logs', function (Blueprint $table) {
            $table->time('break_out')->nullable()->after('time_in');
            $table->time('break_in')->nullable()->after('break_out');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_logs', function (Blueprint $table) {
            $table->dropColumn(['break_out', 'break_in']);
        });
    }
};
