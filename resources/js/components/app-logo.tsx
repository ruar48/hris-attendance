import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/30">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            <div className="ml-1 min-w-0 flex-1 grid text-left text-sm group-data-[collapsible=icon]:hidden">
                <span className="truncate leading-tight font-bold text-slate-900">
                    PayFlow
                </span>
                <span className="truncate text-xs text-blue-600">
                    Payroll System
                </span>
            </div>
        </>
    );
}
