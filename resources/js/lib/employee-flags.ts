// Employment statuses that mark a row for visual review — mirrors the red
// row-highlighting HR used manually in the source spreadsheet for staff
// who are no longer actively working (e.g. AWOL).
const FLAGGED_EMPLOYMENT_STATUSES = new Set(['awol', 'resigned', 'end_of_contract']);

export function isFlaggedEmploymentStatus(employmentStatus: string | null | undefined): boolean {
    return !!employmentStatus && FLAGGED_EMPLOYMENT_STATUSES.has(employmentStatus);
}

/** Row background/text classes for a flagged employee, falling back to zebra striping otherwise. */
export function employeeRowClass(flagged: boolean, index: number): string {
    if (flagged) {
        return 'bg-red-50 text-red-950 hover:bg-red-100';
    }

    return index % 2 === 0 ? 'bg-white' : 'bg-slate-50';
}
