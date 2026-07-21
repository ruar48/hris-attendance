import {
    Document,
    Page,
    Text,
    View,
    StyleSheet,
    pdf,
} from '@react-pdf/renderer';

export type PayslipPdfData = {
    id: number;
    employee_name: string | null;
    employee_code: string | null;
    position: string | null;
    department?: string | null;
    basic_pay: number;
    holiday_pay: number;
    sunday_route: number;
    overtime_pay: number;
    thirteenth_month: number;
    late_deduction: number;
    undertime_deduction: number;
    absence_deduction: number;
    absent_days: number;
    cash_advance_deduction: number;
    sss: number;
    philhealth: number;
    pagibig: number;
    total_earnings: number;
    total_deductions: number;
    net_pay: number;
};

export type PayrollPdfMeta = {
    period: string | null;
    processed_at: string | null;
};

const styles = StyleSheet.create({
    page: {
        paddingTop: 18,
        paddingBottom: 18,
        paddingHorizontal: 22,
        fontSize: 9,
        fontFamily: 'Helvetica',
        color: '#1e293b',
    },
    copy: {
        borderWidth: 1.5,
        borderColor: '#2563eb',
        borderRadius: 6,
        padding: 10,
        minHeight: 350,
    },
    copyHr: {
        borderColor: '#64748b',
    },
    header: {
        borderBottomWidth: 1,
        borderBottomColor: '#dbeafe',
        paddingBottom: 6,
        marginBottom: 6,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
    },
    brand: {
        fontSize: 12,
        fontFamily: 'Helvetica-Bold',
        color: '#1d4ed8',
    },
    subtitle: {
        fontSize: 8,
        color: '#64748b',
        marginTop: 2,
    },
    badge: {
        backgroundColor: '#2563eb',
        color: '#ffffff',
        fontSize: 7,
        fontFamily: 'Helvetica-Bold',
        paddingVertical: 3,
        paddingHorizontal: 7,
        borderRadius: 8,
        textTransform: 'uppercase',
    },
    badgeHr: {
        backgroundColor: '#475569',
    },
    metaRow: {
        flexDirection: 'row',
        marginBottom: 3,
    },
    metaLabel: {
        width: 70,
        color: '#64748b',
    },
    metaValue: {
        width: 120,
        fontFamily: 'Helvetica-Bold',
    },
    cols: {
        flexDirection: 'row',
        marginTop: 6,
        gap: 10,
    },
    col: {
        flex: 1,
    },
    sectionTitle: {
        fontSize: 8,
        fontFamily: 'Helvetica-Bold',
        textTransform: 'uppercase',
        marginBottom: 4,
        paddingBottom: 3,
        borderBottomWidth: 1,
        borderBottomColor: '#e2e8f0',
    },
    earningsTitle: {
        color: '#059669',
    },
    deductionsTitle: {
        color: '#dc2626',
    },
    row: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginBottom: 2,
    },
    rowLabel: {
        color: '#64748b',
    },
    rowAmount: {
        fontFamily: 'Helvetica-Bold',
    },
    totalBox: {
        marginTop: 5,
        paddingVertical: 4,
        paddingHorizontal: 6,
        borderRadius: 4,
        flexDirection: 'row',
        justifyContent: 'space-between',
        fontFamily: 'Helvetica-Bold',
    },
    totalEarnings: {
        backgroundColor: '#ecfdf5',
        color: '#047857',
    },
    totalDeductions: {
        backgroundColor: '#fef2f2',
        color: '#b91c1c',
    },
    net: {
        marginTop: 8,
        textAlign: 'center',
        backgroundColor: '#eff6ff',
        borderWidth: 1,
        borderColor: '#bfdbfe',
        borderRadius: 5,
        padding: 7,
        alignItems: 'center',
    },
    netLabel: {
        fontSize: 8,
        color: '#2563eb',
        textTransform: 'uppercase',
    },
    netAmount: {
        fontSize: 14,
        fontFamily: 'Helvetica-Bold',
        color: '#1d4ed8',
        marginTop: 2,
    },
    signatures: {
        flexDirection: 'row',
        marginTop: 16,
        gap: 20,
    },
    sigBlock: {
        flex: 1,
        alignItems: 'center',
    },
    sigLine: {
        width: '80%',
        borderTopWidth: 1,
        borderTopColor: '#94a3b8',
        marginBottom: 4,
    },
    sigLabel: {
        fontSize: 7,
        color: '#64748b',
    },
    cutLine: {
        marginVertical: 8,
        borderTopWidth: 1,
        borderTopColor: '#94a3b8',
        borderStyle: 'dashed',
        alignItems: 'center',
    },
    cutText: {
        marginTop: -7,
        backgroundColor: '#ffffff',
        paddingHorizontal: 6,
        fontSize: 7,
        color: '#64748b',
        textTransform: 'uppercase',
    },
    footer: {
        marginTop: 6,
        textAlign: 'center',
        color: '#94a3b8',
        fontSize: 7,
    },
});

function peso(value: number): string {
    return `PHP ${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function Meta({
    payslip,
    period,
    processedAt,
    showPayslipId,
}: {
    payslip: PayslipPdfData;
    period: string;
    processedAt: string;
    showPayslipId?: boolean;
}) {
    return (
        <View>
            <View style={styles.metaRow}>
                <Text style={styles.metaLabel}>Employee</Text>
                <Text style={styles.metaValue}>{payslip.employee_name ?? '—'}</Text>
                <Text style={styles.metaLabel}>Period</Text>
                <Text style={styles.metaValue}>{period}</Text>
            </View>
            <View style={styles.metaRow}>
                <Text style={styles.metaLabel}>Employee ID</Text>
                <Text style={styles.metaValue}>{payslip.employee_code ?? '—'}</Text>
                <Text style={styles.metaLabel}>Position</Text>
                <Text style={styles.metaValue}>{payslip.position ?? '—'}</Text>
            </View>
            <View style={styles.metaRow}>
                <Text style={styles.metaLabel}>Department</Text>
                <Text style={styles.metaValue}>{payslip.department ?? '—'}</Text>
                <Text style={styles.metaLabel}>{showPayslipId ? 'Payslip ID' : 'Processed'}</Text>
                <Text style={styles.metaValue}>
                    {showPayslipId
                        ? `#${String(payslip.id).padStart(6, '0')}`
                        : processedAt}
                </Text>
            </View>
        </View>
    );
}

function Amounts({ payslip }: { payslip: PayslipPdfData }) {
    return (
        <View style={styles.cols}>
            <View style={styles.col}>
                <Text style={[styles.sectionTitle, styles.earningsTitle]}>Earnings</Text>
                <View style={styles.row}>
                    <Text style={styles.rowLabel}>Basic Pay</Text>
                    <Text style={styles.rowAmount}>{peso(payslip.basic_pay)}</Text>
                </View>
                {payslip.holiday_pay > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>Holiday Pay</Text>
                        <Text style={styles.rowAmount}>{peso(payslip.holiday_pay)}</Text>
                    </View>
                )}
                {payslip.sunday_route > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>Sunday Route</Text>
                        <Text style={styles.rowAmount}>{peso(payslip.sunday_route)}</Text>
                    </View>
                )}
                {payslip.overtime_pay > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>Overtime Pay</Text>
                        <Text style={styles.rowAmount}>{peso(payslip.overtime_pay)}</Text>
                    </View>
                )}
                {payslip.thirteenth_month > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>13th Month Pay</Text>
                        <Text style={styles.rowAmount}>{peso(payslip.thirteenth_month)}</Text>
                    </View>
                )}
                <View style={[styles.totalBox, styles.totalEarnings]}>
                    <Text>Total Earnings</Text>
                    <Text>{peso(payslip.total_earnings)}</Text>
                </View>
            </View>

            <View style={styles.col}>
                <Text style={[styles.sectionTitle, styles.deductionsTitle]}>Deductions</Text>
                {payslip.late_deduction > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>Late</Text>
                        <Text style={styles.rowAmount}>{peso(payslip.late_deduction)}</Text>
                    </View>
                )}
                {payslip.undertime_deduction > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>Undertime</Text>
                        <Text style={styles.rowAmount}>{peso(payslip.undertime_deduction)}</Text>
                    </View>
                )}
                {payslip.absence_deduction > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>
                            Absences{payslip.absent_days > 0 ? ` (${payslip.absent_days})` : ''}
                        </Text>
                        <Text style={styles.rowAmount}>{peso(payslip.absence_deduction)}</Text>
                    </View>
                )}
                {payslip.cash_advance_deduction > 0 && (
                    <View style={styles.row}>
                        <Text style={styles.rowLabel}>Cash Advance</Text>
                        <Text style={styles.rowAmount}>{peso(payslip.cash_advance_deduction)}</Text>
                    </View>
                )}
                <View style={styles.row}>
                    <Text style={styles.rowLabel}>SSS</Text>
                    <Text style={styles.rowAmount}>{peso(payslip.sss)}</Text>
                </View>
                <View style={styles.row}>
                    <Text style={styles.rowLabel}>PhilHealth</Text>
                    <Text style={styles.rowAmount}>{peso(payslip.philhealth)}</Text>
                </View>
                <View style={styles.row}>
                    <Text style={styles.rowLabel}>Pag-IBIG</Text>
                    <Text style={styles.rowAmount}>{peso(payslip.pagibig)}</Text>
                </View>
                <View style={[styles.totalBox, styles.totalDeductions]}>
                    <Text>Total Deductions</Text>
                    <Text>{peso(payslip.total_deductions)}</Text>
                </View>
            </View>
        </View>
    );
}

function PayslipCopy({
    payslip,
    period,
    processedAt,
    variant,
}: {
    payslip: PayslipPdfData;
    period: string;
    processedAt: string;
    variant: 'employee' | 'hr';
}) {
    const isHr = variant === 'hr';

    return (
        <View style={isHr ? [styles.copy, styles.copyHr] : styles.copy}>
            <View style={styles.header}>
                <View>
                    <Text style={styles.brand}>PayFlow Payroll System</Text>
                    <Text style={styles.subtitle}>
                        {isHr
                            ? 'File copy for HR records · Retain with payroll documents'
                            : 'Official Payslip · Keep this for your records'}
                    </Text>
                </View>
                <Text style={isHr ? [styles.badge, styles.badgeHr] : styles.badge}>
                    {isHr ? 'HR / Company Copy' : 'Employee Copy'}
                </Text>
            </View>

            <Meta
                payslip={payslip}
                period={period}
                processedAt={processedAt}
                showPayslipId={isHr}
            />
            <Amounts payslip={payslip} />

            <View style={styles.net}>
                <Text style={styles.netLabel}>Net Salary</Text>
                <Text style={styles.netAmount}>{peso(payslip.net_pay)}</Text>
            </View>

            <View style={styles.signatures}>
                <View style={styles.sigBlock}>
                    <View style={styles.sigLine} />
                    <Text style={styles.sigLabel}>
                        {isHr ? 'Employee Received / Date' : 'Employee Signature / Date'}
                    </Text>
                </View>
                <View style={styles.sigBlock}>
                    <View style={styles.sigLine} />
                    <Text style={styles.sigLabel}>
                        {isHr ? 'HR / Payroll Officer' : 'Authorized Signature / Date'}
                    </Text>
                </View>
            </View>

            <Text style={styles.footer}>
                {isHr
                    ? 'HR file copy — do not release without authorization.'
                    : 'This is a system-generated payslip from PayFlow.'}
            </Text>
        </View>
    );
}

export function PayslipsDocument({
    payslips,
    meta,
}: {
    payslips: PayslipPdfData[];
    meta: PayrollPdfMeta;
}) {
    const period = meta.period ?? 'Payroll Period';
    const processedAt = meta.processed_at ?? '—';

    return (
        <Document>
            {payslips.map((payslip) => (
                <Page key={payslip.id} size="A4" style={styles.page}>
                    <PayslipCopy
                        payslip={payslip}
                        period={period}
                        processedAt={processedAt}
                        variant="employee"
                    />
                    <View style={styles.cutLine}>
                        <Text style={styles.cutText}>
                            Cut here — Employee copy above · HR copy below
                        </Text>
                    </View>
                    <PayslipCopy
                        payslip={payslip}
                        period={period}
                        processedAt={processedAt}
                        variant="hr"
                    />
                </Page>
            ))}
        </Document>
    );
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

export async function downloadPayslipsPdf(
    payslips: PayslipPdfData[],
    meta: PayrollPdfMeta,
    filename: string,
): Promise<void> {
    const blob = await pdf(
        <PayslipsDocument payslips={payslips} meta={meta} />,
    ).toBlob();

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

export function payslipFilename(payslip: PayslipPdfData, period: string | null): string {
    return `payslip-${payslip.employee_code ?? payslip.id}-${slugify(period ?? 'payroll')}.pdf`;
}

export function allPayslipsFilename(period: string | null): string {
    return `payslips-all-${slugify(period ?? 'payroll')}.pdf`;
}
