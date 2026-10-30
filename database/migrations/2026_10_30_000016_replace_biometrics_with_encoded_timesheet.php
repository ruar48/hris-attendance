<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance is now encoded by hand on the time sheet instead of synced
     * from a biometric device, so the DTR entry becomes the only source and
     * every change to it is written to an audit trail.
     */
    public function up(): void
    {
        Schema::table('dtr_logs', function (Blueprint $table) {
            // worked | day_off | absent | leave
            $table->string('day_status')->default('worked')->after('time_out');
            $table->foreignId('encoded_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('action'); // created | updated | deleted
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'work_date']);
        });

        Schema::dropIfExists('biometric_logs');
        Schema::dropIfExists('biometric_devices');
    }

    public function down(): void
    {
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
            $table->string('punch_type')->default('in');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
        });

        Schema::dropIfExists('attendance_audit_logs');

        Schema::table('dtr_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encoded_by');
            $table->dropColumn('day_status');
        });
    }
};
