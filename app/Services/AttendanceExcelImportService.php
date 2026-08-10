<?php

namespace App\Services;

use App\Models\DtrLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class AttendanceExcelImportService
{
    protected const SHEET_NAME = 'attendance logs';

    protected const LABEL_ROW_OFFSET_DATES = 1;

    protected const LABEL_ROW_OFFSET_TIMES = 3;

    /**
     * @return array{created: int, updated: int, duplicates: int, employees: int, warnings: string[], range: array{start: string, end: string}|null}
     */
    public function import(UploadedFile $file, ?int $approvedBy): array
    {
        // PhpSpreadsheet reads legacy .xls exports (and some xlsx shared-string
        // tables) with iconv, and already falls back to mb_convert_encoding
        // when a string has a malformed byte sequence — but PHP's iconv()
        // also emits a warning on that same failure, which Laravel's default
        // error handler turns into a fatal ErrorException before that
        // fallback ever gets to run. Swallow just that warning for the
        // duration of the whole read/parse so the built-in fallback can do
        // its job, wherever in the file it's needed.
        $previousHandler = set_error_handler(function (int $errno, string $errstr) use (&$previousHandler) {
            if (str_contains($errstr, 'iconv')) {
                return true;
            }

            return $previousHandler ? $previousHandler(...func_get_args()) : false;
        });

        try {
            return $this->doImport($file, $approvedBy);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return array{created: int, updated: int, duplicates: int, employees: int, warnings: string[], range: array{start: string, end: string}|null}
     */
    protected function doImport(UploadedFile $file, ?int $approvedBy): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $this->findSheet($spreadsheet);
        $rangeStart = $this->findRangeStart($sheet);

        if ($rangeStart === null) {
            throw new RuntimeException(
                "Could not find the sheet's \"Date\" range cell (expected a value like \"2026-07-16 ~ 2026-08-04\")."
            );
        }

        $warnings = [];
        $created = 0;
        $updated = 0;
        $duplicates = 0;
        $employeeIds = [];
        $minDate = null;
        $maxDate = null;

        $highestRow = $sheet->getHighestDataRow();

        DB::transaction(function () use (
            $sheet,
            $highestRow,
            $rangeStart,
            $approvedBy,
            $file,
            &$warnings,
            &$created,
            &$updated,
            &$duplicates,
            &$employeeIds,
            &$minDate,
            &$maxDate
        ) {
            for ($row = 1; $row <= $highestRow; $row++) {
                $idLabel = trim((string) $sheet->getCell([1, $row])->getValue());

                if (strtolower($idLabel) !== 'id') {
                    continue;
                }

                $code = $this->nextNonEmptyValue($sheet, $row, 1);
                $name = $this->valueAfterLabel($sheet, $row, 'name');
                $dept = $this->valueAfterLabel($sheet, $row, 'dept');

                if ($code === null) {
                    $warnings[] = "Row {$row}: found an \"ID\" label but no employee code next to it — block skipped.";

                    continue;
                }

                $employee = Employee::query()->where('employee_code', trim((string) $code))->first();

                if (! $employee) {
                    $warnings[] = "Row {$row}: employee code \"{$code}\" (".($name ?? 'unknown name').") not found — block skipped.";

                    continue;
                }

                $dateRow = $row + self::LABEL_ROW_OFFSET_DATES;
                $timesRow = $row + self::LABEL_ROW_OFFSET_TIMES;
                $dateColumns = $this->detectDateColumns($sheet, $dateRow);

                if (empty($dateColumns)) {
                    $warnings[] = "Row {$row}: employee {$code} has no date columns detected — block skipped.";

                    continue;
                }

                $blockImported = 0;

                foreach ($dateColumns as $index => $column) {
                    $date = $rangeStart->copy()->addDays($index);
                    $raw = (string) $sheet->getCell([$column, $timesRow])->getValue();
                    $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
                    $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));

                    if (empty($lines)) {
                        continue;
                    }

                    $timeIn = $this->parseTime($lines[0] ?? null);
                    $timeOut = isset($lines[1]) ? $this->parseTime($lines[1]) : null;

                    if ($timeIn === null) {
                        $columnLetter = Coordinate::stringFromColumnIndex($column);
                        $warnings[] = "Row {$row} ({$code}), {$columnLetter}{$timesRow}: unreadable time value \"{$lines[0]}\" — cell skipped.";

                        continue;
                    }

                    // updateOrCreate() can't be used here: work_date is cast to
                    // `date`, which Eloquent stores as a full "Y-m-d 00:00:00"
                    // string — a plain "Y-m-d" search value never matches that
                    // on re-import, so it would always insert a fresh row and
                    // collide with the unique index on the second attempt.
                    $log = DtrLog::query()
                        ->where('employee_id', $employee->id)
                        ->whereDate('work_date', $date->toDateString())
                        ->first();

                    $attributes = [
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                        'reason' => 'Imported from '.$file->getClientOriginalName(),
                        'status' => 'approved',
                        'approved_by' => $approvedBy,
                    ];

                    if ($log) {
                        // Same employee, same date, same punches already on file
                        // — nothing actually changed, so don't touch the row or
                        // count it as a fresh import. The summary counts these;
                        // they don't need a line of their own, or a re-import of
                        // a big file turns into a wall of identical notes.
                        if ($log->time_in === $timeIn && $log->time_out === $timeOut) {
                            $duplicates++;
                        } else {
                            $log->update($attributes);
                            $updated++;
                        }
                    } else {
                        DtrLog::query()->create([
                            'employee_id' => $employee->id,
                            'work_date' => $date->toDateString(),
                            ...$attributes,
                        ]);
                        $created++;
                    }

                    $blockImported++;
                    $employeeIds[$employee->id] = true;
                    $minDate = $minDate === null || $date->lt($minDate) ? $date->copy() : $minDate;
                    $maxDate = $maxDate === null || $date->gt($maxDate) ? $date->copy() : $maxDate;
                }

                if ($blockImported === 0) {
                    $warnings[] = "Row {$row}: employee {$code} had no readable time entries in this block.";
                }
            }
        });

        if ($minDate !== null && $maxDate !== null) {
            app(AttendanceResolver::class)->syncPeriod($minDate, $maxDate);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'duplicates' => $duplicates,
            'employees' => count($employeeIds),
            'warnings' => $warnings,
            'range' => $minDate !== null && $maxDate !== null
                ? ['start' => $minDate->toDateString(), 'end' => $maxDate->toDateString()]
                : null,
        ];
    }

    protected function findSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): Worksheet
    {
        $sheets = $spreadsheet->getAllSheets();

        // A file exported as just the attendance log (e.g. straight from the
        // biometric software) has nothing else to disambiguate by — its one
        // sheet IS the attendance log, whatever it's titled.
        if (count($sheets) === 1) {
            return $sheets[0];
        }

        foreach ($sheets as $sheet) {
            if (strtolower(trim($sheet->getTitle())) === self::SHEET_NAME) {
                return $sheet;
            }
        }

        $available = implode(', ', array_map(
            fn ($sheet) => $sheet->getTitle(),
            $sheets
        ));

        throw new RuntimeException(
            "Could not find an \"Attendance Logs\" sheet in this file. Sheets found: {$available}."
        );
    }

    protected function findRangeStart(Worksheet $sheet): ?Carbon
    {
        $maxRow = min(15, $sheet->getHighestDataRow());
        $maxCol = min(10, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($row = 1; $row <= $maxRow; $row++) {
            for ($col = 1; $col <= $maxCol; $col++) {
                $value = trim((string) $sheet->getCell([$col, $row])->getValue());

                if (strtolower($value) !== 'date') {
                    continue;
                }

                for ($lookahead = $col + 1; $lookahead <= $col + 3; $lookahead++) {
                    $candidate = (string) $sheet->getCell([$lookahead, $row])->getValue();

                    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $candidate, $matches)) {
                        return Carbon::parse($matches[1]);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find a cell in the given row whose text matches $label, then return the
     * next non-empty cell value to its right (skipping up to two blanks).
     */
    protected function valueAfterLabel(Worksheet $sheet, int $row, string $label): ?string
    {
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($col = 1; $col <= $maxCol; $col++) {
            $value = trim((string) $sheet->getCell([$col, $row])->getValue());

            if (strtolower($value) === $label) {
                return $this->nextNonEmptyValue($sheet, $row, $col);
            }
        }

        return null;
    }

    protected function nextNonEmptyValue(Worksheet $sheet, int $row, int $afterColumn): ?string
    {
        for ($col = $afterColumn + 1; $col <= $afterColumn + 3; $col++) {
            $value = trim((string) $sheet->getCell([$col, $row])->getValue());

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Columns immediately after the labels that hold a numeric day-of-month
     * (1–31), taken as one contiguous run starting at the first match.
     *
     * @return int[]
     */
    protected function detectDateColumns(Worksheet $sheet, int $dateRow): array
    {
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $columns = [];
        $started = false;

        for ($col = 2; $col <= $maxCol; $col++) {
            $value = trim((string) $sheet->getCell([$col, $dateRow])->getValue());
            $isDayNumber = $value !== '' && ctype_digit($value) && (int) $value >= 1 && (int) $value <= 31;

            if ($isDayNumber) {
                $started = true;
                $columns[] = $col;
            } elseif ($started) {
                break;
            }
        }

        return $columns;
    }

    protected function parseTime(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw, $matches)) {
            return null;
        }

        return sprintf('%02d:%02d:00', (int) $matches[1], (int) $matches[2]);
    }
}
