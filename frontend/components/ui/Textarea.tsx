import { InputLabel } from './InputLabel';

interface TextareaProps {
    label?: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    help?: string;
    required?: boolean;
    rows?: number;
    maxLength?: number;
    placeholder?: string;
}

export function Textarea({
    label,
    name,
    value,
    onChange,
    error,
    help,
    required,
    rows = 4,
    maxLength,
    placeholder,
}: TextareaProps) {
    return (
        <div>
            {label && (
                <InputLabel htmlFor={name} className="mb-1.5">
                    {label} {required && <span className="text-danger-600">*</span>}
                </InputLabel>
            )}

            <textarea
                id={name}
                name={name}
                rows={rows}
                maxLength={maxLength}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={`block w-full rounded-md border bg-bg-white text-txt-black-900 ${
                    error
                        ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500'
                        : 'border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary'
                } text-body-sm shadow-sm focus:ring-1 disabled:bg-bg-washed disabled:opacity-60`}
            />

            <div className="mt-1 flex items-center justify-between gap-2">
                <div>
                    {help && !error && <p className="text-body-xs text-txt-black-500">{help}</p>}
                    {error && <p className="text-body-xs text-danger-600">{error}</p>}
                </div>

                {maxLength && (
                    <p className="flex-shrink-0 text-body-xs text-txt-black-500">
                        {value.length} / {maxLength}
                    </p>
                )}
            </div>
        </div>
    );
}
