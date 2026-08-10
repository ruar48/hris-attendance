import { useOptionList } from '@/hooks/use-option-list';
import type { OptionListCategory } from '@/types/global';

type Props = {
    category: OptionListCategory;
    value: string | null;
    onChange: (value: string) => void;
    className?: string;
    /** Set false for non-nullable columns (e.g. employee status) to hide the blank option. */
    allowEmpty?: boolean;
};

/**
 * A <select> backed by the Employees > Source Data picklists. If the
 * current value isn't in the list (renamed/removed upstream), it's kept as
 * an extra option so existing data doesn't silently disappear.
 */
export function OptionSelect({ category, value, onChange, className, allowEmpty = true }: Props) {
    const options = useOptionList(category);
    const hasCurrentValue = value ? options.some((option) => option.value === value) : true;

    return (
        <select value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={className}>
            {allowEmpty && <option value="">—</option>}
            {value && !hasCurrentValue && <option value={value}>{value}</option>}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}
