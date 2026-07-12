<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs already have half from the create migration.
        if (! Schema::hasColumn('payroll_periods', 'half')) {
            Schema::table('payroll_periods', function (Blueprint $table) {
                $table->unsignedTinyInteger('half')->default(1)->after('month');
            });

            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                // Rebuild in place (no rename-to-_old) so dependent FKs stay valid.
                Schema::table('payroll_periods', function (Blueprint $table) {
                    $table->dropUnique(['year', 'month']);
                });
                Schema::table('payroll_periods', function (Blueprint $table) {
                    $table->unique(['year', 'month', 'half']);
                });
            } else {
                Schema::table('payroll_periods', function (Blueprint $table) {
                    $table->dropUnique(['year', 'month']);
                    $table->unique(['year', 'month', 'half']);
                });
            }
        }

        $this->repairSqlitePayrollRunsForeignKey();
    }

    /**
     * Earlier SQLite migration renamed payroll_periods → payroll_periods_old,
     * leaving payroll_runs FK pointing at a dropped table.
     */
    protected function repairSqlitePayrollRunsForeignKey(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        $createSql = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'payroll_runs'"
        )?->sql ?? '';

        if (! str_contains($createSql, 'payroll_periods_old')) {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::rename('payroll_runs', 'payroll_runs_broken');

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('total_payroll', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_payroll', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO payroll_runs (
                id, payroll_period_id, total_employees, total_payroll, total_deductions,
                net_payroll, status, processed_at, created_at, updated_at
            )
            SELECT
                id, payroll_period_id, total_employees, total_payroll, total_deductions,
                net_payroll, status, processed_at, created_at, updated_at
            FROM payroll_runs_broken
        ');

        Schema::drop('payroll_runs_broken');

        DB::statement('PRAGMA foreign_keys=ON');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payroll_periods', 'half')) {
            return;
        }

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropUnique(['year', 'month', 'half']);
            $table->dropColumn('half');
            $table->unique(['year', 'month']);
        });
    }
};
