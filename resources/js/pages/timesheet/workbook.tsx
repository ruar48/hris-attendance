import { DtrWorkbook } from '@/components/timesheet/dtr-workbook';
import type { ScheduleWeek, TimesheetPageProps } from '@/components/timesheet/dtr-workbook';

type Props = TimesheetPageProps & {
    sheet: 'dtr' | 'schedule';
    schedule: ScheduleWeek[];
};

/**
 * The time sheets as a full-screen workbook — no sidebar or header, just the
 * sheet and its tabs (rendered without a layout; see app.tsx).
 */
export default function TimesheetWorkbook(props: Props) {
    return <DtrWorkbook {...props} fullScreen />;
}
