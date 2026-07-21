import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

export type EmployeeOption = {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
};

type Props = {
    employees: EmployeeOption[];
    value: string;
    onChange: (employeeId: string) => void;
    placeholder?: string;
};

const label = (employee: EmployeeOption) =>
    `${employee.employee_code} — ${employee.first_name} ${employee.last_name}`;

/**
 * Type-to-filter employee picker. A plain <select> is unusable once the list
 * runs to a hundred-plus people.
 */
export function EmployeeCombobox({
    employees,
    value,
    onChange,
    placeholder = 'Search name or code…',
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlighted, setHighlighted] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const selected = employees.find((employee) => String(employee.id) === value) ?? null;

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) {
            return employees;
        }

        return employees.filter((employee) => label(employee).toLowerCase().includes(needle));
    }, [employees, query]);

    // Close when clicking outside.
    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [open]);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        }
    }, [open]);

    const choose = (employee: EmployeeOption) => {
        onChange(String(employee.id));
        setOpen(false);
        setQuery('');
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlighted((index) => Math.min(index + 1, matches.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlighted((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const employee = matches[highlighted];

            if (employee) {
                choose(employee);
            }
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div ref={containerRef} className="relative">
            <button
                type="button"
                onClick={() => setOpen((isOpen) => !isOpen)}
                className="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-sm outline-none focus:border-emerald-400"
            >
                <span className={selected ? 'text-slate-800' : 'text-slate-400'}>
                    {selected ? label(selected) : 'Select employee'}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 text-slate-400" />
            </button>

            {open && (
                <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    <div className="relative border-b border-slate-100">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            ref={inputRef}
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setHighlighted(0);
                            }}
                            onKeyDown={onKeyDown}
                            placeholder={placeholder}
                            className="w-full py-2.5 pr-3 pl-9 text-sm outline-none"
                        />
                    </div>

                    <ul className="max-h-64 overflow-y-auto py-1">
                        {matches.length === 0 && (
                            <li className="px-3 py-6 text-center text-sm text-slate-500">
                                No employee matches “{query}”.
                            </li>
                        )}
                        {matches.map((employee, index) => {
                            const isSelected = String(employee.id) === value;

                            return (
                                <li key={employee.id}>
                                    <button
                                        type="button"
                                        onMouseEnter={() => setHighlighted(index)}
                                        onClick={() => choose(employee)}
                                        className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm ${
                                            index === highlighted
                                                ? 'bg-emerald-50 text-emerald-900'
                                                : 'text-slate-700'
                                        }`}
                                    >
                                        <span className="truncate">{label(employee)}</span>
                                        {isSelected && (
                                            <Check className="size-4 shrink-0 text-emerald-600" />
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </div>
    );
}
