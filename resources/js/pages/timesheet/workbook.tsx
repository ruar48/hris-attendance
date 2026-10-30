import type { ScheduleData } from '@/components/schedule/schedule-sheet';
import { DtrWorkbook } from '@/components/timesheet/dtr-workbook';
import type { SheetName, TimesheetPageProps } from '@/components/timesheet/dtr-workbook';
import type { Manning } from '@/components/timesheet/dtr-workbook';

type Props = TimesheetPageProps & {
    sheet: SheetName;
    schedule: ScheduleData | null;
    manning: Manning | null;
};

/**
 * The time sheets as a full-screen workbook — no sidebar or header, just the
 * sheet and its tabs (rendered without a layout; see app.tsx).
 */
export default function TimesheetWorkbook(props: Props) {
    return <DtrWorkbook {...props} fullScreen />;
}
