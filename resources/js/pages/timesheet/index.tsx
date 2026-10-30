import { DtrWorkbook } from '@/components/timesheet/dtr-workbook';
import type { TimesheetPageProps } from '@/components/timesheet/dtr-workbook';

export default function TimesheetIndex(props: TimesheetPageProps) {
    return <DtrWorkbook {...props} />;
}

TimesheetIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Time Sheet', href: '/timesheet' },
    ],
};
