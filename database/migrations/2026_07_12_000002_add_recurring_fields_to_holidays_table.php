<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('name');
            $table->unsignedTinyInteger('month')->nullable()->after('is_recurring');
            $table->unsignedTinyInteger('day')->nullable()->after('month');
        });

        // SQLite: recreate unique constraint so date can be null for yearly regular holidays
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropUnique(['date']);
            });
        } else {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropUnique(['date']);
                $table->date('date')->nullable()->change();
            });
        }

        // For SQLite, rebuild table to make date nullable
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::rename('holidays', 'holidays_old');

            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_recurring')->default(false);
                $table->unsignedTinyInteger('month')->nullable();
                $table->unsignedTinyInteger('day')->nullable();
                $table->date('date')->nullable();
                $table->string('type')->default('regular');
                $table->decimal('pay_multiplier', 4, 2)->default(2.00);
                $table->timestamps();
                $table->unique('date');
            });

            DB::statement('
                INSERT INTO holidays (id, name, is_recurring, month, day, date, type, pay_multiplier, created_at, updated_at)
                SELECT id, name, is_recurring, month, day, date, type, pay_multiplier, created_at, updated_at
                FROM holidays_old
            ');

            Schema::drop('holidays_old');
        }
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'month', 'day']);
        });
    }
};
