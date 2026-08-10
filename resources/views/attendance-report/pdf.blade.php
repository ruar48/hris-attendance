<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        @page {
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            color: #1d4ed8;
            margin: 0 0 2px;
        }

        .range {
            color: #64748b;
            margin: 0 0 10px;
        }

        .card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            width: 33.33%;
        }

        .meta .label {
            color: #64748b;
            display: block;
            font-size: 8px;
            text-transform: uppercase;
        }

        .meta .value {
            font-weight: bold;
        }

        table.stats, table.days {
            width: 100%;
            border-collapse: collapse;
        }

        table.stats th, table.stats td,
        table.days th, table.days td {
            border: 1px solid #e2e8f0;
            padding: 3px 4px;
            text-align: center;
        }

        table.stats th {
            background: #f8fafc;
            color: #64748b;
            font-weight: normal;
        }

        table.days th {
            background: #f8fafc;
            color: #64748b;
            font-weight: normal;
        }

        table.days .date-col {
            width: 10%;
            color: #475569;
        }

        table.days .ot-value {
            color: #1d4ed8;
        }
    </style>
</head>
<body>
    <p class="title">Attendance Report</p>
    <p class="range">Date: {{ $start->toDateString() }} ~ {{ $end->toDateString() }}</p>

    @foreach ($employees as $employee)
        <div class="card">
            <table class="meta">
                <tr>
                    <td><span class="label">Dept.</span><span class="value">{{ $employee['department'] ?? '—' }}</span></td>
                    <td><span class="label">Name</span><span class="value">{{ $employee['name'] }}</span></td>
                    <td><span class="label">ID</span><span class="value">{{ $employee['employee_code'] }}</span></td>
                </tr>
            </table>

            <table class="stats">
                <tr>
                    <th>Absences (Day)</th>
                    <th>Leave (Day)</th>
                    <th>Business trip (Day)</th>
                    <th>Attendance (Day)</th>
                    <th>OT Normal</th>
                    <th>OT Special</th>
                    <th>Late Freq</th>
                    <th>Late Min</th>
                    <th>Early Freq</th>
                    <th>Early Min</th>
                </tr>
                <tr>
                    <td>{{ $employee['absences'] }}</td>
                    <td>{{ $employee['leave'] }}</td>
                    <td>{{ $employee['business_trip'] }}</td>
                    <td>{{ $employee['attend_standard'] }}</td>
                    <td>{{ $employee['ot_normal_hours'] }}</td>
                    <td>{{ $employee['ot_special_hours'] }}</td>
                    <td>{{ $employee['late_frequency'] }}</td>
                    <td>{{ $employee['late_minutes'] }}</td>
                    <td>{{ $employee['early_frequency'] }}</td>
                    <td>{{ $employee['early_minutes'] }}</td>
                </tr>
            </table>

            <table class="days">
                <tr>
                    <th rowspan="2" class="date-col">Date/Week</th>
                    <th colspan="2">Time1</th>
                    <th colspan="2">Time2</th>
                    <th colspan="2">OT</th>
                </tr>
                <tr>
                    <th>In</th>
                    <th>Out</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>In</th>
                    <th>Out</th>
                </tr>
                @foreach ($employee['days'] as $day)
                    <tr>
                        <td class="date-col">{{ $day['label'] }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="ot-value">{{ $day['ot_in'] }}</td>
                        <td class="ot-value">{{ $day['ot_out'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
