import { InputLabel } from './InputLabel';

const HEX_PATTERN = /^#[0-9A-Fa-f]{6}$/;

interface ColorPickerProps {
    id: string;
    label?: string;
    help?: string;
    value: string;
    onChange: (hex: string) => void;
}

export function ColorPicker({ id, label, help, value, onChange }: ColorPickerProps) {
    const swatchValue = HEX_PATTERN.test(value) ? value : '#000000';

    return (
        <div>
            {label && (
                <InputLabel htmlFor={id} className="mb-1.5">
                    {label}
                </InputLabel>
            )}

            <div className="flex items-center gap-2">
                <input
                    type="color"
                    id={id}
                    value={swatchValue}
                    onChange={(e) => onChange(e.target.value.toUpperCase())}
                    className="h-10 w-14 flex-shrink-0 cursor-pointer rounded-md border border-otl-gray-300 bg-bg-white p-1"
                />
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value.toUpperCase())}
                    placeholder="#2563EB"
                    maxLength={7}
                    className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm font-mono uppercase"
                />
            </div>

            {help && <p className="mt-1 text-body-xs text-txt-black-500">{help}</p>}
        </div>
    );
}
