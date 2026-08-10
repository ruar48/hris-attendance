<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->string('applicant_name');
            $table->string('position_applied')->nullable();
            $table->date('date_of_application')->nullable();
            $table->string('application_status')->default('for_initial_interview');

            // Requirements checklist
            $table->boolean('req_psa_birth_certificate')->default(false);
            $table->boolean('req_government_ids')->default(false);
            $table->boolean('req_nbi_clearance')->default(false);
            $table->boolean('req_police_clearance')->default(false);
            $table->boolean('req_barangay_clearance')->default(false);
            $table->boolean('req_sss_number')->default(false);
            $table->boolean('req_philhealth_number')->default(false);
            $table->boolean('req_pagibig_id')->default(false);
            $table->boolean('req_tin_number')->default(false);
            $table->boolean('req_college_diploma_tor')->default(false);
            $table->boolean('req_marriage_certificate')->default(false);
            $table->boolean('req_solo_parent_id')->default(false);
            $table->boolean('req_bir_form_2305_1905')->default(false);
            $table->boolean('req_latest_bir_form_2316')->default(false);
            $table->boolean('req_certificate_of_employment')->default(false);

            $table->date('date_submitted')->nullable();
            $table->string('requirement_status')->default('incomplete');

            $table->date('onboarding_date')->nullable();
            $table->date('onboarding_training_1')->nullable();
            $table->date('onboarding_training_2')->nullable();
            $table->date('onboarding_training_3')->nullable();
            $table->date('onboarding_training_4')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
