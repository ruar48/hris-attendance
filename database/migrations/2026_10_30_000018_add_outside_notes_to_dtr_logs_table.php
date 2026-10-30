<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Times jotted beside the paper DTR, outside the form — e.g. a day left
     * blank inside with "6:54 / 16:06" noted to the right. They are kept as
     * notes and never paid until copied into the form.
     */
    public function up(): void
    {
        Schema::table('dtr_logs', function (Blueprint $table) {
            $table->time('note_time_in')->nullable()->after('time_out');
            $table->time('note_time_out')->nullable()->after('note_time_in');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_logs', function (Blueprint $table) {
            $table->dropColumn(['note_time_in', 'note_time_out']);
        });
    }
};
