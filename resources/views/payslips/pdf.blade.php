<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        @page {
            margin: 12mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .copy {
            border: 1.5px solid #2563eb;
            border-radius: 6px;
            padding: 10px 12px;
            height: 118mm;
            overflow: hidden;
        }

        .copy-hr {
            border-color: #64748b;
        }

        .cut-line {
            margin: 8px 0;
            border-top: 1.5px dashed #94a3b8;
            position: relative;
            text-align: center;
            height: 14px;
        }

        .cut-line span {
            position: relative;
            top: -8px;
            background: #fff;
            padding: 0 8px;
            color: #64748b;
            font-size: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .header {
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .brand {
            font-size: 14px;
            font-weight: bold;
            color: #1d4ed8;
            margin: 0;
        }

        .copy-badge {
            float: right;
            background: #2563eb;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .copy-hr .copy-badge {
            background: #475569;
        }

        .subtitle {
            color: #64748b;
            font-size: 9px;
            margin-top: 2px;
        }

        .meta {
            width: 100%;
            margin-bottom: 8px;
        }

        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }

        .meta .label {
            color: #64748b;
            width: 90px;
        }

        .meta .value {
            font-weight: bold;
        }

        .cols {
            width: 100%;
            border-collapse: collapse;
        }

        .cols td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        .cols td:first-child {
            padding-right: 8px;
        }

        .cols td:last-child {
            padding-left: 8px;
            border-left: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0 0 4px;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
        }

        .earnings-title {
            color: #059669;
        }

        .deductions-title {
            color: #dc2626;
        }

        .row {
            width: 100%;
            border-collapse: collapse;
        }

        .row td {
            padding: 2px 0;
        }

        .row .label {
            color: #64748b;
        }

        .row .amount {
            text-align: right;
            font-weight: 600;
        }

        .total-box {
            margin-top: 6px;
            padding: 5px 7px;
            border-radius: 4px;
            font-weight: bold;
        }

        .total-earnings {
            background: #ecfdf5;
            color: #047857;
        }

        .total-deductions {
            background: #fef2f2;
            color: #b91c1c;
        }

        .net {
            margin-top: 8px;
            text-align: center;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            padding: 7px;
        }

        .net .label {
            font-size: 9px;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .net .amount {
            font-size: 16px;
            font-weight: bold;
            color: #1d4ed8;
            margin-top: 2px;
        }

        .signatures {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            padding-top: 18px;
            vertical-align: bottom;
        }

        .sig-line {
            border-top: 1px solid #94a3b8;
            width: 80%;
            margin: 0 auto 4px;
        }

        .sig-label {
            color: #64748b;
            font-size: 8px;
        }

        .footer-note {
            margin-top: 6px;
            text-align: center;
            color: #94a3b8;
            font-size: 7px;
        }
    </style>
</head>
<body>
@foreach ($payslips as $payslip)
    @php
        $employee = $payslip->employee;
        $periodName = $run->period?->name ?? 'Payroll Period';
        $peso = fn ($value) => 'PHP ' . number_format((float) $value, 2);
    @endphp

    <div class="page">
        {{-- Employee Copy --}}
        <div class="copy">
            <div class="header">
                <span class="copy-badge">Employee Copy</span>
                <p class="brand">PayFlow Payroll System</p>
                <div class="subtitle">Official Payslip · Keep this for your records</div>
            </div>

            <table class="meta">
                <tr>
                    <td class="label">Employee</td>
                    <td class="value">{{ $employee?->full_name }}</td>
                    <td class="label">Period</td>
                    <td class="value">{{ $periodName }}</td>
                </tr>
                <tr>
                    <td class="label">Employee ID</td>
                    <td class="value">{{ $employee?->employee_code }}</td>
                    <td class="label">Position</td>
                    <td class="value">{{ $employee?->position ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Department</td>
                    <td class="value">{{ $employee?->department ?? '—' }}</td>
                    <td class="label">Processed</td>
                    <td class="value">{{ optional($run->processed_at)->format('M d, Y') ?? now()->format('M d, Y') }}</td>
                </tr>
            </table>

            <table class="cols">
                <tr>
                    <td>
                        <p class="section-title earnings-title">Earnings</p>
                        <table class="row">
                            <tr><td class="label">Basic Pay</td><td class="amount">{{ $peso($payslip->basic_pay) }}</td></tr>
                            @if ((float) $payslip->holiday_pay > 0)
                                <tr><td class="label">Holiday Pay</td><td class="amount">{{ $peso($payslip->holiday_pay) }}</td></tr>
                            @endif
                            @if ((float) $payslip->sunday_route > 0)
                                <tr><td class="label">Sunday Route</td><td class="amount">{{ $peso($payslip->sunday_route) }}</td></tr>
                            @endif
                            @if ((float) $payslip->overtime_pay > 0)
                                <tr><td class="label">Overtime Pay</td><td class="amount">{{ $peso($payslip->overtime_pay) }}</td></tr>
                            @endif
                            @if ((float) $payslip->thirteenth_month > 0)
                                <tr><td class="label">13th Month Pay</td><td class="amount">{{ $peso($payslip->thirteenth_month) }}</td></tr>
                            @endif
                        </table>
                        <div class="total-box total-earnings">
                            <table class="row">
                                <tr>
                                    <td>Total Earnings</td>
                                    <td class="amount">{{ $peso($payslip->total_earnings) }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                    <td>
                        <p class="section-title deductions-title">Deductions</p>
                        <table class="row">
                            @if ((float) $payslip->late_deduction > 0)
                                <tr><td class="label">Late</td><td class="amount">{{ $peso($payslip->late_deduction) }}</td></tr>
                            @endif
                            @if ((float) $payslip->cash_advance_deduction > 0)
                                <tr><td class="label">Cash Advance</td><td class="amount">{{ $peso($payslip->cash_advance_deduction) }}</td></tr>
                            @endif
                            <tr><td class="label">SSS</td><td class="amount">{{ $peso($payslip->sss) }}</td></tr>
                            <tr><td class="label">PhilHealth</td><td class="amount">{{ $peso($payslip->philhealth) }}</td></tr>
                            <tr><td class="label">Pag-IBIG</td><td class="amount">{{ $peso($payslip->pagibig) }}</td></tr>
                            <tr><td class="label">Withholding Tax</td><td class="amount">{{ $peso($payslip->withholding_tax) }}</td></tr>
                        </table>
                        <div class="total-box total-deductions">
                            <table class="row">
                                <tr>
                                    <td>Total Deductions</td>
                                    <td class="amount">{{ $peso($payslip->total_deductions) }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="net">
                <div class="label">Net Salary</div>
                <div class="amount">{{ $peso($payslip->net_pay) }}</div>
            </div>

            <table class="signatures">
                <tr>
                    <td>
                        <div class="sig-line"></div>
                        <div class="sig-label">Employee Signature / Date</div>
                    </td>
                    <td>
                        <div class="sig-line"></div>
                        <div class="sig-label">Authorized Signature / Date</div>
                    </td>
                </tr>
            </table>
            <div class="footer-note">This is a system-generated payslip from PayFlow.</div>
        </div>

        <div class="cut-line"><span>✂ Cut here — Employee copy above · HR copy below</span></div>

        {{-- HR Copy --}}
        <div class="copy copy-hr">
            <div class="header">
                <span class="copy-badge">HR / Company Copy</span>
                <p class="brand">PayFlow Payroll System</p>
                <div class="subtitle">File copy for HR records · Retain with payroll documents</div>
            </div>

            <table class="meta">
                <tr>
                    <td class="label">Employee</td>
                    <td class="value">{{ $employee?->full_name }}</td>
                    <td class="label">Period</td>
                    <td class="value">{{ $periodName }}</td>
                </tr>
                <tr>
                    <td class="label">Employee ID</td>
                    <td class="value">{{ $employee?->employee_code }}</td>
                    <td class="label">Position</td>
                    <td class="value">{{ $employee?->position ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Department</td>
                    <td class="value">{{ $employee?->department ?? '—' }}</td>
                    <td class="label">Payslip ID</td>
                    <td class="value">#{{ str_pad((string) $payslip->id, 6, '0', STR_PAD_LEFT) }}</td>
                </tr>
            </table>

            <table class="cols">
                <tr>
                    <td>
                        <p class="section-title earnings-title">Earnings</p>
                        <table class="row">
                            <tr><td class="label">Basic Pay</td><td class="amount">{{ $peso($payslip->basic_pay) }}</td></tr>
                            @if ((float) $payslip->holiday_pay > 0)
                                <tr><td class="label">Holiday Pay</td><td class="amount">{{ $peso($payslip->holiday_pay) }}</td></tr>
                            @endif
                            @if ((float) $payslip->sunday_route > 0)
                                <tr><td class="label">Sunday Route</td><td class="amount">{{ $peso($payslip->sunday_route) }}</td></tr>
                            @endif
                            @if ((float) $payslip->overtime_pay > 0)
                                <tr><td class="label">Overtime Pay</td><td class="amount">{{ $peso($payslip->overtime_pay) }}</td></tr>
                            @endif
                            @if ((float) $payslip->thirteenth_month > 0)
                                <tr><td class="label">13th Month Pay</td><td class="amount">{{ $peso($payslip->thirteenth_month) }}</td></tr>
                            @endif
                        </table>
                        <div class="total-box total-earnings">
                            <table class="row">
                                <tr>
                                    <td>Total Earnings</td>
                                    <td class="amount">{{ $peso($payslip->total_earnings) }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                    <td>
                        <p class="section-title deductions-title">Deductions</p>
                        <table class="row">
                            @if ((float) $payslip->late_deduction > 0)
                                <tr><td class="label">Late</td><td class="amount">{{ $peso($payslip->late_deduction) }}</td></tr>
                            @endif
                            @if ((float) $payslip->cash_advance_deduction > 0)
                                <tr><td class="label">Cash Advance</td><td class="amount">{{ $peso($payslip->cash_advance_deduction) }}</td></tr>
                            @endif
                            <tr><td class="label">SSS</td><td class="amount">{{ $peso($payslip->sss) }}</td></tr>
                            <tr><td class="label">PhilHealth</td><td class="amount">{{ $peso($payslip->philhealth) }}</td></tr>
                            <tr><td class="label">Pag-IBIG</td><td class="amount">{{ $peso($payslip->pagibig) }}</td></tr>
                            <tr><td class="label">Withholding Tax</td><td class="amount">{{ $peso($payslip->withholding_tax) }}</td></tr>
                        </table>
                        <div class="total-box total-deductions">
                            <table class="row">
                                <tr>
                                    <td>Total Deductions</td>
                                    <td class="amount">{{ $peso($payslip->total_deductions) }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="net">
                <div class="label">Net Salary</div>
                <div class="amount">{{ $peso($payslip->net_pay) }}</div>
            </div>

            <table class="signatures">
                <tr>
                    <td>
                        <div class="sig-line"></div>
                        <div class="sig-label">Employee Received / Date</div>
                    </td>
                    <td>
                        <div class="sig-line"></div>
                        <div class="sig-label">HR / Payroll Officer</div>
                    </td>
                </tr>
            </table>
            <div class="footer-note">HR file copy — do not release without authorization.</div>
        </div>
    </div>
@endforeach
</body>
</html>
