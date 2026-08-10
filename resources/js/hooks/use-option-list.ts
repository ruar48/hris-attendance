import { usePage } from '@inertiajs/react';
import type { OptionListCategory, OptionListEntry } from '@/types/global';

export function useOptionList(category: OptionListCategory): OptionListEntry[] {
    const { optionLists } = usePage().props;

    return optionLists?.[category] ?? [];
}
