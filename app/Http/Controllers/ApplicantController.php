<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantController extends Controller
{
    public function index(Request $request): Response
    {
        $applicants = Applicant::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('applicant_name', 'like', "%{$search}%")
                        ->orWhere('application_number', 'like', "%{$search}%")
                        ->orWhere('position_applied', 'like', "%{$search}%");
                });
            })
            ->orderBy('application_number')
            ->get()
            ->map(fn (Applicant $applicant) => [
                'id' => $applicant->id,
                'application_number' => $applicant->application_number,
                'applicant_name' => $applicant->applicant_name,
                'position_applied' => $applicant->position_applied,
                'date_of_application' => $applicant->date_of_application?->toDateString(),
                'application_status' => $applicant->application_status,
                ...collect(Applicant::REQUIREMENT_FIELDS)
                    ->mapWithKeys(fn (string $field) => [$field => (bool) $applicant->{$field}])
                    ->all(),
                'date_submitted' => $applicant->date_submitted?->toDateString(),
                'requirement_status' => $applicant->requirement_status,
                'onboarding_date' => $applicant->onboarding_date?->toDateString(),
                'onboarding_training_1' => $applicant->onboarding_training_1?->toDateString(),
                'onboarding_training_2' => $applicant->onboarding_training_2?->toDateString(),
                'onboarding_training_3' => $applicant->onboarding_training_3?->toDateString(),
                'onboarding_training_4' => $applicant->onboarding_training_4?->toDateString(),
            ]);

        return Inertia::render('applicants/index', [
            'applicants' => $applicants,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
            'nextApplicationNumber' => $this->nextApplicationNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateApplicant($request);

        Applicant::query()->create([
            ...$data,
            'application_number' => ($data['application_number'] ?? null) ?: $this->nextApplicationNumber(),
        ]);

        return $this->toast('success', 'Applicant added successfully.');
    }

    public function update(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $this->validateApplicant($request, $applicant);

        $applicant->update([
            ...$data,
            'application_number' => ($data['application_number'] ?? null) ?: $applicant->application_number,
        ]);

        return $this->toast('success', "{$applicant->applicant_name} was updated.");
    }

    public function destroy(Applicant $applicant): RedirectResponse
    {
        $applicant->delete();

        return $this->toast('warning', "{$applicant->applicant_name} was removed.");
    }

    private function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateApplicant(Request $request, ?Applicant $applicant = null): array
    {
        $requirementRules = array_fill_keys(
            array_map(fn (string $field) => $field, Applicant::REQUIREMENT_FIELDS),
            ['nullable', 'boolean'],
        );

        return $request->validate([
            'application_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('applicants', 'application_number')->ignore($applicant?->id),
            ],
            'applicant_name' => ['required', 'string', 'max:150'],
            'position_applied' => ['nullable', 'string', 'max:100'],
            'date_of_application' => ['nullable', 'date'],
            'application_status' => ['nullable', Rule::in(Applicant::APPLICATION_STATUSES)],
            ...$requirementRules,
            'date_submitted' => ['nullable', 'date'],
            'requirement_status' => ['nullable', Rule::in(Applicant::REQUIREMENT_STATUSES)],
            'onboarding_date' => ['nullable', 'date'],
            'onboarding_training_1' => ['nullable', 'date'],
            'onboarding_training_2' => ['nullable', 'date'],
            'onboarding_training_3' => ['nullable', 'date'],
            'onboarding_training_4' => ['nullable', 'date'],
        ]);
    }

    private function nextApplicationNumber(): string
    {
        $highest = Applicant::query()
            ->where('application_number', 'like', 'APPLICATION-%')
            ->pluck('application_number')
            ->map(fn (string $code) => (int) substr($code, 12))
            ->max();

        return sprintf('APPLICATION-%07d', ($highest ?? 8202600000) + 1);
    }
}
