interface ToggleProps {
    name: string;
    label?: string;
    checked?: boolean;
    onChange?: (checked: boolean) => void;
}

export function Toggle({ name, label, checked, onChange }: ToggleProps) {
    return (
        <label htmlFor={name} className="inline-flex items-center gap-3 cursor-pointer">
            <span className="relative inline-flex h-6 w-11 flex-shrink-0 items-center">
                <input
                    type="checkbox"
                    id={name}
                    name={name}
                    checked={checked}
                    onChange={(e) => onChange?.(e.target.checked)}
                    className="peer absolute inset-0 h-full w-full cursor-pointer opacity-0"
                />
                <span className="pointer-events-none block h-6 w-11 rounded-full bg-bg-black-200 transition-colors peer-checked:bg-primary-600 peer-focus-visible:ring-2 peer-focus-visible:ring-fr-primary peer-focus-visible:ring-offset-2" />
                <span className="pointer-events-none absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5" />
            </span>

            {label && <span className="text-body-sm text-txt-black-900">{label}</span>}
        </label>
    );
}
