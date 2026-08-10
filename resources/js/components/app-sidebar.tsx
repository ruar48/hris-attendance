import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    Building2,
    CalendarDays,
    ClipboardList,
    FileBarChart,
    Fingerprint,
    LayoutGrid,
    Settings,
    SlidersHorizontal,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';

const mainNavItems = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
        tone: 'bg-blue-100 text-blue-600',
    },
    {
        title: 'Employees',
        href: '/employees',
        matchHrefs: ['/applicants', '/employees/profile'],
        icon: Users,
        tone: 'bg-sky-100 text-sky-600',
    },
    {
        title: 'Payroll',
        href: '/payroll',
        icon: Wallet,
        tone: 'bg-emerald-100 text-emerald-600',
    },
    {
        title: 'Cash Advances',
        href: '/cash-advances',
        icon: Banknote,
        tone: 'bg-violet-100 text-violet-600',
    },
    {
        title: 'Gov Benefits',
        href: '/government-benefits',
        icon: Building2,
        tone: 'bg-indigo-100 text-indigo-600',
    },
    {
        title: 'Holidays',
        href: '/holidays',
        icon: CalendarDays,
        tone: 'bg-rose-100 text-rose-600',
    },
];

const attendanceNavItems = [
    {
        title: 'Biometrics',
        href: '/biometrics',
        icon: Fingerprint,
        tone: 'bg-indigo-100 text-indigo-600',
    },
    {
        title: 'DTR Fallback',
        href: '/dtr',
        icon: ClipboardList,
        tone: 'bg-amber-100 text-amber-600',
    },
    {
        title: 'Schedules',
        href: '/schedules',
        icon: CalendarDays,
        tone: 'bg-teal-100 text-teal-600',
    },
    {
        title: 'Attendance Summary',
        href: '/attendance-summary',
        icon: FileBarChart,
        tone: 'bg-cyan-100 text-cyan-600',
    },
    {
        title: 'Abnormal',
        href: '/attendance-summary/abnormal',
        icon: AlertTriangle,
        tone: 'bg-red-100 text-red-600',
    },
    {
        title: 'Attendance Report',
        href: '/attendance-summary/report',
        icon: FileBarChart,
        tone: 'bg-purple-100 text-purple-600',
    },
];

const systemNavItems = [
    {
        title: 'Payroll Settings',
        href: '/payroll-settings',
        icon: SlidersHorizontal,
        tone: 'bg-blue-100 text-blue-700',
    },
    {
        title: 'Account Settings',
        href: '/settings/profile',
        icon: Settings,
        tone: 'bg-slate-200 text-slate-600',
    },
];

export function AppSidebar() {
    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="overflow-hidden border-r border-slate-200/80"
        >
            <SidebarHeader className="overflow-hidden border-b border-blue-100/80 bg-gradient-to-br from-blue-50 to-white px-2 py-2.5">
                <SidebarMenu className="overflow-hidden">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-12 overflow-hidden rounded-lg hover:bg-blue-100/60"
                        >
                            <Link href={dashboard()} prefetch className="min-w-0">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0 overflow-x-hidden overflow-y-auto bg-gradient-to-b from-white to-slate-50/80 py-2">
                <NavMain items={mainNavItems} label="Main" />
                <SidebarSeparator className="mx-3 my-2 bg-slate-200/80" />
                <NavMain items={attendanceNavItems} label="Attendance" />
                <SidebarSeparator className="mx-3 my-2 bg-slate-200/80" />
                <NavMain items={systemNavItems} label="System" />
            </SidebarContent>

            <SidebarFooter className="overflow-hidden border-t border-slate-200/80 bg-white px-1 py-2">
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
