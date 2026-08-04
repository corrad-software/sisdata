import { InputLabel } from './InputLabel';

interface FieldInputProps {
    label?: string;
    name: string;
    type?: string;
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
    help?: string;
    error?: string;
    placeholder?: string;
}

export function FieldInput({
    label,
    name,
    type = 'text',
    value,
    onChange,
    required,
    help,
    error,
    placeholder,
}: FieldInputProps) {
    return (
        <div>
            {label && (
                <InputLabel htmlFor={name} className="mb-1.5">
                    {label} {required && <span className="text-danger-600">*</span>}
                </InputLabel>
            )}

            <input
                id={name}
                name={name}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={`block w-full rounded-md border bg-bg-white text-txt-black-900 ${
                    error
                        ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500'
                        : 'border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary'
                } text-body-sm shadow-sm focus:ring-1 disabled:bg-bg-washed disabled:opacity-60`}
            />

            {help && !error && <p className="mt-1 text-body-xs text-txt-black-500">{help}</p>}
            {error && <p className="mt-1 text-body-xs text-danger-600">{error}</p>}
        </div>
    );
}
