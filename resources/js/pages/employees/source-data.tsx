import { Head, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmployeeSectionTabs } from '@/components/employee-section-tabs';
import type { OptionListCategory, OptionListEntry } from '@/types/global';

type Props = {
    optionLists: Record<OptionListCategory, OptionListEntry[]>;
};

type CategoryConfig = {
    key: OptionListCategory;
    title: string;
    hasCode: boolean;
    hasTime: boolean;
};

const CATEGORIES: CategoryConfig[] = [
    { key: 'employee_status', title: 'Employee Statuses', hasCode: true, hasTime: false },
    { key: 'employment_status', title: 'Employment Statuses', hasCode: true, hasTime: false },
    { key: 'position', title: 'Company Positions', hasCode: false, hasTime: false },
    { key: 'department', title: 'Departments', hasCode: false, hasTime: false },
    { key: 'job_level', title: 'Company Job Levels / Ranks', hasCode: true, hasTime: false },
    { key: 'shift_schedule', title: 'Company Shift & Work Schedules', hasCode: true, hasTime: true },
];

export default function SourceDataIndex({ optionLists }: Props) {
    const inputClass =
        'w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs outline-none focus:border-emerald-400';

    const patchOption = (entry: OptionListEntry, changes: Partial<OptionListEntry>) => {
        router.put(
            `/option-lists/${entry.id}`,
            { ...entry, ...changes },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    const count = Object.keys(errors).length;
                    toast.error(count === 1 ? Object.values(errors)[0] : `Please fix ${count} fields.`);
                },
            },
        );
    };

    const deleteOption = (entry: OptionListEntry) => {
        router.delete(`/option-lists/${entry.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Source Data" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <EmployeeSectionTabs />

                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Source Data</h1>
                    <p className="text-sm text-slate-500">
                        The picklists behind every dropdown across the Employees section — add, rename, or remove
                        entries here and they update everywhere immediately.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {CATEGORIES.map((category) => (
                        <CategoryCard
                            key={category.key}
                            category={category}
                            entries={optionLists[category.key] ?? []}
                            inputClass={inputClass}
                            onPatch={patchOption}
                            onDelete={deleteOption}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function CategoryCard({
    category,
    entries,
    inputClass,
    onPatch,
    onDelete,
}: {
    category: CategoryConfig;
    entries: OptionListEntry[];
    inputClass: string;
    onPatch: (entry: OptionListEntry, changes: Partial<OptionListEntry>) => void;
    onDelete: (entry: OptionListEntry) => void;
}) {
    const [newValue, setNewValue] = useState('');
    const [newLabel, setNewLabel] = useState('');
    const [newTimeIn, setNewTimeIn] = useState('');
    const [newTimeOut, setNewTimeOut] = useState('');

    const addEntry = (event: React.FormEvent) => {
        event.preventDefault();
        if (!newLabel.trim()) return;

        const value = category.hasCode ? newValue.trim() : newLabel.trim();
        if (!value) {
            toast.error('Enter a code for this entry.');
            return;
        }

        router.post(
            '/option-lists',
            {
                category: category.key,
                value,
                label: newLabel.trim(),
                time_in: category.hasTime ? newTimeIn.trim() || null : null,
                time_out: category.hasTime ? newTimeOut.trim() || null : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewValue('');
                    setNewLabel('');
                    setNewTimeIn('');
                    setNewTimeOut('');
                },
                onError: (errors) => {
                    const count = Object.keys(errors).length;
                    toast.error(count === 1 ? Object.values(errors)[0] : `Please fix ${count} fields.`);
                },
            },
        );
    };

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-300 bg-white">
            <div className="bg-rose-800 px-4 py-2.5 text-sm font-bold tracking-wide text-white uppercase">
                {category.title}
            </div>
            <div className="divide-y divide-slate-100">
                {entries.length === 0 && <div className="px-4 py-4 text-xs text-slate-400">No entries yet.</div>}
                {entries.map((entry) => (
                    <div key={entry.id} className="flex items-center gap-2 px-3 py-2">
                        {category.hasCode && (
                            <input
                                className={`${inputClass} w-20 shrink-0 font-mono`}
                                defaultValue={entry.value}
                                onBlur={(e) => e.target.value !== entry.value && onPatch(entry, { value: e.target.value })}
                            />
                        )}
                        <input
                            className={inputClass}
                            defaultValue={entry.label}
                            onBlur={(e) => e.target.value !== entry.label && onPatch(entry, { label: e.target.value })}
                        />
                        {category.hasTime && (
                            <>
                                <input
                                    className={`${inputClass} w-24 shrink-0`}
                                    placeholder="Time In"
                                    defaultValue={entry.time_in ?? ''}
                                    onBlur={(e) =>
                                        e.target.value !== (entry.time_in ?? '') &&
                                        onPatch(entry, { time_in: e.target.value || null })
                                    }
                                />
                                <input
                                    className={`${inputClass} w-24 shrink-0`}
                                    placeholder="Time Out"
                                    defaultValue={entry.time_out ?? ''}
                                    onBlur={(e) =>
                                        e.target.value !== (entry.time_out ?? '') &&
                                        onPatch(entry, { time_out: e.target.value || null })
                                    }
                                />
                            </>
                        )}
                        <button
                            type="button"
                            onClick={() => onDelete(entry)}
                            className="shrink-0 rounded p-1 text-rose-500 hover:bg-rose-50"
                            title="Remove"
                        >
                            <Trash2 className="size-3.5" />
                        </button>
                    </div>
                ))}
            </div>
            <form onSubmit={addEntry} className="flex items-center gap-2 border-t border-slate-200 bg-slate-50 px-3 py-2">
                {category.hasCode && (
                    <input
                        className={`${inputClass} w-20 shrink-0 font-mono`}
                        placeholder="Code"
                        value={newValue}
                        onChange={(e) => setNewValue(e.target.value)}
                    />
                )}
                <input
                    className={inputClass}
                    placeholder="New entry"
                    value={newLabel}
                    onChange={(e) => setNewLabel(e.target.value)}
                />
                {category.hasTime && (
                    <>
                        <input
                            className={`${inputClass} w-24 shrink-0`}
                            placeholder="Time In"
                            value={newTimeIn}
                            onChange={(e) => setNewTimeIn(e.target.value)}
                        />
                        <input
                            className={`${inputClass} w-24 shrink-0`}
                            placeholder="Time Out"
                            value={newTimeOut}
                            onChange={(e) => setNewTimeOut(e.target.value)}
                        />
                    </>
                )}
                <button
                    type="submit"
                    className="shrink-0 rounded bg-emerald-600 p-1.5 text-white hover:bg-emerald-700"
                    title="Add"
                >
                    <Plus className="size-3.5" />
                </button>
            </form>
        </div>
    );
}

SourceDataIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
        { title: 'Source Data', href: '/employees/source-data' },
    ],
};
