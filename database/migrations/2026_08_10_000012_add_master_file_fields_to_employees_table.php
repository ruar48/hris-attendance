<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('last_name');
            $table->string('suffix')->nullable()->after('middle_name');
            $table->string('photo_url')->nullable()->after('suffix');
            $table->string('personal_email')->nullable()->after('email');
            $table->string('company_email')->nullable()->after('personal_email');
            $table->text('current_address')->nullable()->after('address');
            $table->text('permanent_address')->nullable()->after('current_address');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'middle_name', 'suffix', 'photo_url', 'personal_email', 'company_email',
                'current_address', 'permanent_address',
            ]);
        });
    }
};
