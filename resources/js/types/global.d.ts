import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

export type OptionListEntry = {
    id: number;
    value: string;
    label: string;
    time_in: string | null;
    time_out: string | null;
};

export type OptionListCategory =
    | 'employee_status'
    | 'employment_status'
    | 'position'
    | 'department'
    | 'job_level'
    | 'shift_schedule';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            optionLists: Record<OptionListCategory, OptionListEntry[]>;
            [key: string]: unknown;
        };
    }
}
