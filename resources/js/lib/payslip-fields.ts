/**
 * Payslip amounts HR enters after a payroll run. Kept apart from
 * payslip-pdf.tsx so pages can use them without loading the PDF renderer.
 */

/** Added to net pay (same list as Payslip::ADJUSTMENT_FIELDS). */
export const ADJUSTMENT_FIELDS = [
    { key: 'reimbursement', label: 'Reimbursement' },
    { key: 'allowance', label: 'Allowance' },
] as const;

/** Listed under net pay (same list as Payslip::WAREHOUSE_SUBSIDY_FIELDS). */
export const WAREHOUSE_SUBSIDY_FIELDS = [
    { key: 'forklift_diesel', label: 'Forklift Diesel' },
    { key: 'disbursement_batangas', label: 'Disbursement – Batangas' },
    { key: 'disbursement_dasma', label: 'Disbursement – Dasma' },
    { key: 'disbursement_san_pablo', label: 'Disbursement – San Pablo' },
    { key: 'disbursement_tayabas', label: 'Disbursement – Tayabas' },
    { key: 'disbursement_taguig', label: 'Disbursement – Taguig' },
] as const;

export type PayslipAmountField =
    | (typeof ADJUSTMENT_FIELDS)[number]['key']
    | (typeof WAREHOUSE_SUBSIDY_FIELDS)[number]['key'];
