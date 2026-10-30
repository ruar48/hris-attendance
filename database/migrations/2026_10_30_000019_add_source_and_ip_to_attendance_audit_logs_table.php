<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The change log now covers schedule edits as well as time sheet edits,
     * and records which computer each change came from.
     */
    public function up(): void
    {
        Schema::table('attendance_audit_logs', function (Blueprint $table) {
            $table->string('source')->default('timesheet')->after('action'); // timesheet | schedule
            $table->string('ip_address', 45)->nullable()->after('changes');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['source', 'ip_address']);
        });
    }
};
