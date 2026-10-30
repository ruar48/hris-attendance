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
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('site')->nullable()->after('name');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['date']);
            $table->unique(['date', 'site']);
            $table->index('site');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropIndex(['site']);
            $table->dropUnique(['date', 'site']);
            $table->unique('date');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('site');
        });
    }
};
