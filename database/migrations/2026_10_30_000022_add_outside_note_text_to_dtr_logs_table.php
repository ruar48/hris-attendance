<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Text written beside the paper DTR, outside the form — "STRAIGHT DUTY",
     * "PLS. IR", "San Pablo SO" — kept next to the outside times.
     */
    public function up(): void
    {
        Schema::table('dtr_logs', function (Blueprint $table) {
            $table->string('note_text')->nullable()->after('note_time_out');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_logs', function (Blueprint $table) {
            $table->dropColumn('note_text');
        });
    }
};
