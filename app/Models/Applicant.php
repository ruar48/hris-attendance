<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_number',
        'applicant_name',
        'position_applied',
        'date_of_application',
        'application_status',
        'req_psa_birth_certificate',
        'req_government_ids',
        'req_nbi_clearance',
        'req_police_clearance',
        'req_barangay_clearance',
        'req_sss_number',
        'req_philhealth_number',
        'req_pagibig_id',
        'req_tin_number',
        'req_college_diploma_tor',
        'req_marriage_certificate',
        'req_solo_parent_id',
        'req_bir_form_2305_1905',
        'req_latest_bir_form_2316',
        'req_certificate_of_employment',
        'date_submitted',
        'requirement_status',
        'onboarding_date',
        'onboarding_training_1',
        'onboarding_training_2',
        'onboarding_training_3',
        'onboarding_training_4',
    ];

    protected $casts = [
        'date_of_application' => 'date',
        'date_submitted' => 'date',
        'onboarding_date' => 'date',
        'onboarding_training_1' => 'date',
        'onboarding_training_2' => 'date',
        'onboarding_training_3' => 'date',
        'onboarding_training_4' => 'date',
        'req_psa_birth_certificate' => 'boolean',
        'req_government_ids' => 'boolean',
        'req_nbi_clearance' => 'boolean',
        'req_police_clearance' => 'boolean',
        'req_barangay_clearance' => 'boolean',
        'req_sss_number' => 'boolean',
        'req_philhealth_number' => 'boolean',
        'req_pagibig_id' => 'boolean',
        'req_tin_number' => 'boolean',
        'req_college_diploma_tor' => 'boolean',
        'req_marriage_certificate' => 'boolean',
        'req_solo_parent_id' => 'boolean',
        'req_bir_form_2305_1905' => 'boolean',
        'req_latest_bir_form_2316' => 'boolean',
        'req_certificate_of_employment' => 'boolean',
    ];

    /** @var list<string> */
    public const APPLICATION_STATUSES = [
        'for_initial_interview',
        'initial_interview_failed',
        'for_final_interview',
        'final_interview_failed',
        'job_offer',
        'job_offer_declined',
        'cancelled_application',
        'onboarding',
        'terminated',
        'endo',
        'resigned',
    ];

    /** @var list<string> */
    public const REQUIREMENT_STATUSES = ['complete', 'incomplete', 'pending'];

    /** @var list<string> */
    public const REQUIREMENT_FIELDS = [
        'req_psa_birth_certificate',
        'req_government_ids',
        'req_nbi_clearance',
        'req_police_clearance',
        'req_barangay_clearance',
        'req_sss_number',
        'req_philhealth_number',
        'req_pagibig_id',
        'req_tin_number',
        'req_college_diploma_tor',
        'req_marriage_certificate',
        'req_solo_parent_id',
        'req_bir_form_2305_1905',
        'req_latest_bir_form_2316',
        'req_certificate_of_employment',
    ];
}
