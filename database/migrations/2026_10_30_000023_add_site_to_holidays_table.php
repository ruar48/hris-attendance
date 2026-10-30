<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A holiday is nationwide (site empty) or local to one site — a town
     * fiesta at Tayabas doesn't pay Taguig. Two sites may each have their own
     * holiday on the same date.
     */
    public function up(): void
    {
        // Each step checks first: on MySQL an earlier migration already
        // dropped the old unique index on `date` (only SQLite kept it).
        if (! Schema::hasColumn('holidays', 'site')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->string('site')->nullable()->after('name');
            });
        }

        if (Schema::hasIndex('holidays', ['date'], 'unique')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropUnique(['date']);
            });
        }

        Schema::table('holidays', function (Blueprint $table) {
            $table->unique(['date', 'site']);
            $table->index('site');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropIndex(['site']);
            $table->dropUnique(['date', 'site']);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('site');
        });
    }
};
