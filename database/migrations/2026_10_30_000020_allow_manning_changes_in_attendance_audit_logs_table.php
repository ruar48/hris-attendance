<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manning changes (a person's site or regular schedule time) are logged
     * too, and they aren't tied to one day, so work_date may be empty.
     */
    public function up(): void
    {
        Schema::table('attendance_audit_logs', function (Blueprint $table) {
            $table->date('work_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_audit_logs', function (Blueprint $table) {
            $table->date('work_date')->nullable(false)->change();
        });
    }
};
