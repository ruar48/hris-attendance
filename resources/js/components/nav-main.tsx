import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

type NavItemWithTone = NavItem & {
    tone?: string;
    /** Extra route prefixes that should also mark this item active (e.g. a tabbed sub-page). */
    matchHrefs?: string[];
};

function isNavActive(currentUrl: string, href: string): boolean {
    if (currentUrl === href) {
        return true;
    }

    // Match nested routes like /payroll/5, but not /payroll-settings
    return currentUrl.startsWith(`${href}/`);
}

export function NavMain({
    items = [],
    label = 'Menu',
}: {
    items: NavItemWithTone[];
    label?: string;
}) {
    const { currentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-1">
            <SidebarGroupLabel className="mb-1 px-2 text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-0.5 overflow-hidden">
                {items.map((item) => {
                    const href =
                        typeof item.href === 'string' ? item.href : String(item.href);
                    const active =
                        isNavActive(currentUrl, href) ||
                        (item.matchHrefs?.some((extra) => isNavActive(currentUrl, extra)) ?? false);

                    return (
                        <SidebarMenuItem key={item.title} className="overflow-hidden">
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className={cn(
                                    'relative h-9 overflow-hidden rounded-lg px-2.5 text-slate-600 transition-colors',
                                    'hover:bg-blue-50 hover:text-blue-700',
                                    active &&
                                        'bg-blue-600 text-white shadow-sm shadow-blue-600/20 hover:bg-blue-600 hover:text-white',
                                )}
                            >
                                <Link href={item.href} prefetch className="min-w-0">
                                    {active && (
                                        <span className="absolute top-1/2 left-0 h-5 w-0.5 -translate-y-1/2 rounded-r-full bg-white" />
                                    )}
                                    {item.icon && (
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded-md',
                                                active
                                                    ? 'bg-white/20 text-white'
                                                    : cn(
                                                          'bg-slate-100 text-slate-500',
                                                          item.tone,
                                                      ),
                                            )}
                                        >
                                            <item.icon className="size-3.5" />
                                        </span>
                                    )}
                                    <span
                                        className={cn(
                                            'truncate text-sm font-medium',
                                            active && 'font-semibold',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
