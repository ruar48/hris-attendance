import { Link } from '@inertiajs/react';
import { Briefcase, Building2, ClipboardCheck, Database, FolderKanban, GripVertical, IdCard, Users, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';

type Tab = { title: string; href: string; icon: React.ComponentType<{ className?: string }> };

const DEFAULT_TABS: Tab[] = [
    { title: 'Employee Roster', href: '/employees', icon: Users },
    { title: 'Profile', href: '/employees/profile', icon: IdCard },
    { title: 'Employment Details', href: '/employees/employment-details', icon: Briefcase },
    { title: 'Employee Master File', href: '/employees/master-file', icon: FolderKanban },
    { title: 'Government Benefits', href: '/employees/government-benefits', icon: Building2 },
    { title: 'Compensation & Payroll', href: '/employees/compensation-payroll', icon: Wallet },
    { title: 'Applicants & Onboarding', href: '/applicants', icon: ClipboardCheck },
    { title: 'Source Data', href: '/employees/source-data', icon: Database },
];

const STORAGE_KEY = 'employee-tabs-order';

function loadOrder(): Tab[] {
    if (typeof window === 'undefined') return DEFAULT_TABS;

    try {
        const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]') as string[];
        if (!Array.isArray(saved) || saved.length === 0) return DEFAULT_TABS;

        const byHref = new Map(DEFAULT_TABS.map((tab) => [tab.href, tab]));
        const ordered = saved.map((href) => byHref.get(href)).filter((tab): tab is Tab => Boolean(tab));
        // Append any tabs not present in the saved order (e.g. newly added tabs).
        const missing = DEFAULT_TABS.filter((tab) => !saved.includes(tab.href));

        return [...ordered, ...missing];
    } catch {
        return DEFAULT_TABS;
    }
}

export function EmployeeSectionTabs() {
    const { isCurrentUrl } = useCurrentUrl();
    const [tabs, setTabs] = useState<Tab[]>(DEFAULT_TABS);
    const [dragIndex, setDragIndex] = useState<number | null>(null);
    const [overIndex, setOverIndex] = useState<number | null>(null);

    useEffect(() => {
        setTabs(loadOrder());
    }, []);

    const persist = (next: Tab[]) => {
        setTabs(next);
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next.map((tab) => tab.href)));
    };

    const reorder = (from: number, to: number) => {
        if (from === to) return;

        const next = [...tabs];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);
        persist(next);
    };

    return (
        <div className="flex gap-1 border-b border-slate-200">
            {tabs.map((tab, index) => {
                const active = isCurrentUrl(tab.href);
                const Icon = tab.icon;
                const isDragging = dragIndex === index;
                const isDropTarget = overIndex === index && dragIndex !== null && dragIndex !== index;

                return (
                    <div
                        key={tab.href}
                        onDragEnter={() => dragIndex !== null && setOverIndex(index)}
                        onDragOver={(e) => dragIndex !== null && e.preventDefault()}
                        onDrop={(e) => {
                            e.preventDefault();
                            if (dragIndex !== null) reorder(dragIndex, index);
                            setDragIndex(null);
                            setOverIndex(null);
                        }}
                        className={[
                            'flex items-center rounded-t-lg',
                            active ? 'border border-b-0 border-slate-200 bg-white' : '',
                            isDragging ? 'opacity-40' : '',
                            isDropTarget ? 'bg-emerald-50 ring-2 ring-inset ring-emerald-300' : '',
                        ].join(' ')}
                    >
                        <span
                            draggable
                            title="Drag to reorder"
                            onDragStart={(e) => {
                                setDragIndex(index);
                                e.dataTransfer.effectAllowed = 'move';
                            }}
                            onDragEnd={() => {
                                setDragIndex(null);
                                setOverIndex(null);
                            }}
                            className="cursor-grab pl-1.5 text-slate-300 hover:text-slate-500 active:cursor-grabbing"
                        >
                            <GripVertical className="size-3.5" />
                        </span>
                        <Link
                            href={tab.href}
                            className={[
                                'flex items-center gap-1.5 py-2 pr-3 pl-1.5 text-sm font-medium',
                                active ? 'font-semibold text-emerald-700' : 'text-slate-500 hover:text-slate-700',
                            ].join(' ')}
                        >
                            <Icon className="size-4" />
                            {tab.title}
                        </Link>
                    </div>
                );
            })}
        </div>
    );
}
