interface RadioProps {
    name: string;
    value: string;
    label: string;
    checked?: boolean;
    onChange?: () => void;
}

export function Radio({ name, value, label, checked, onChange }: RadioProps) {
    const id = `${name}-${value}`;

    return (
        <label htmlFor={id} className="flex items-center gap-2 cursor-pointer">
            <input
                type="radio"
                id={id}
                name={name}
                value={value}
                checked={checked}
                onChange={onChange}
                className="border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
            />
            <span className="text-body-sm text-txt-black-900">{label}</span>
        </label>
    );
}
