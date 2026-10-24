'use client';

import { useState } from 'react';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { ralat, type Kod } from '@/lib/kes';
import { Field, inputClass, ModalBody, Select } from './common';

export interface Medan {
    name: string;
    label: string;
    type?: 'text' | 'textarea' | 'date' | 'datetime-local' | 'select' | 'decimal' | 'checkbox';
    options?: Kod[];
    required?: boolean;
    help?: string;
    initial?: string;
}

/** Small generic form-in-a-modal for the case actions (proceedings, costs, decisions, status…). */
export function BorangModal({
    open,
    title,
    fields,
    submitLabel = 'Simpan',
    onClose,
    onSubmit,
}: {
    open: boolean;
    title: string;
    fields: Medan[];
    submitLabel?: string;
    onClose: () => void;
    onSubmit: (values: Record<string, string | boolean>) => Promise<void>;
}) {
    const [values, setValues] = useState<Record<string, string | boolean>>({});
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const v = (f: Medan) => values[f.name] ?? (f.type === 'checkbox' ? false : (f.initial ?? ''));

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const out: Record<string, string | boolean> = {};
            for (const f of fields) {
                const value = v(f);
                if (value !== '') out[f.name] = value;
            }
            await onSubmit(out);
            setValues({});
            onClose();
        } catch (err) {
            setError(ralat(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={open} onClose={onClose}>
            <form onSubmit={submit}>
                <ModalBody
                    title={title}
                    footer={
                        <>
                            <Button variant="secondary" onClick={onClose}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={busy}>
                                {busy ? 'Menyimpan…' : submitLabel}
                            </Button>
                        </>
                    }
                >
                    {fields.map((f) => {
                        const id = `bm-${f.name}`;
                        const set = (x: string | boolean) => setValues((s) => ({ ...s, [f.name]: x }));
                        if (f.type === 'checkbox') {
                            return (
                                <label key={f.name} className="flex items-center gap-2 text-body-sm text-txt-black-700">
                                    <input type="checkbox" checked={Boolean(v(f))} onChange={(e) => set(e.target.checked)} className="rounded border-otl-gray-300" />
                                    {f.label}
                                </label>
                            );
                        }
                        return (
                            <Field key={f.name} label={f.label} htmlFor={id} help={f.help}>
                                {f.type === 'select' ? (
                                    <Select id={id} value={String(v(f))} onChange={set} options={f.options ?? []} empty="Pilih" required={f.required} />
                                ) : f.type === 'textarea' ? (
                                    <textarea id={id} rows={4} className={inputClass} required={f.required} value={String(v(f))} onChange={(e) => set(e.target.value)} />
                                ) : (
                                    <input
                                        id={id}
                                        type={f.type === 'decimal' ? 'text' : (f.type ?? 'text')}
                                        inputMode={f.type === 'decimal' ? 'decimal' : undefined}
                                        className={inputClass}
                                        required={f.required}
                                        value={String(v(f))}
                                        onChange={(e) => set(e.target.value)}
                                    />
                                )}
                            </Field>
                        );
                    })}
                    {error && (
                        <p className="text-body-sm text-txt-danger" role="alert">
                            {error}
                        </p>
                    )}
                </ModalBody>
            </form>
        </Modal>
    );
}
