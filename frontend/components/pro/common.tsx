'use client';

import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Dropdown } from '@/components/ui/Dropdown';
import { InputLabel } from '@/components/ui/InputLabel';
import { Modal } from '@/components/ui/Modal';
import { Textarea } from '@/components/ui/Textarea';
import { Alert } from '@/components/ui/Alert';
import {
    FORMAT_EKSPORT,
    KLASIFIKASI_LABEL,
    errorMessage,
    proApi,
    toneKlasifikasi,
    toneSpatial,
    type Meta,
    type Pengguna,
    type SpatialFlag,
} from '@/lib/pro';

export const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm disabled:bg-bg-washed disabled:opacity-60';

export function Card({ title, actions, children, className }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={`bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4 min-w-0 ${className ?? ''}`}>
            {(title || actions) && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {title && <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{title}</h3>}
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
            )}
            {children}
        </section>
    );
}

/** SummaryList look, but accepts rich values. */
export function InfoList({ items }: { items: Array<{ label: string; value: ReactNode; key?: string }> }) {
    return (
        <dl className="divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white">
            {items.map((item, i) => (
                <div key={item.key ?? `${item.label}-${i}`} className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
                    <dt className="text-body-sm font-medium text-txt-black-500 sm:w-48 sm:flex-shrink-0">{item.label}</dt>
                    <dd className="flex-1 text-body-sm text-txt-black-900 break-words min-w-0">{item.value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** PRO-10: geometry verification flag, shown wherever a lot appears. */
export function SpatialBadge({ spatial, status, label }: { spatial?: SpatialFlag | null; status?: string | null; label?: string | null }) {
    const s = spatial?.status ?? status ?? 'tiada_geometri';
    const text = spatial?.label ?? label ?? s;
    const tone = toneSpatial(s);
    return (
        <Badge variant={tone}>
            {tone !== 'success' && (
                <svg className="me-1 h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fillRule="evenodd"
                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z"
                        clipRule="evenodd"
                    />
                </svg>
            )}
            {text}
        </Badge>
    );
}

export function KlasifikasiBadge({ value }: { value: string | null | undefined }) {
    if (!value) return <span>—</span>;
    return <Badge variant={toneKlasifikasi(value)}>{KLASIFIKASI_LABEL[value] ?? value}</Badge>;
}

/** Field masked by classification (Sulit/Rahsia) for this user. */
export function Terhad() {
    return (
        <span className="inline-flex items-center gap-1 text-txt-black-500 italic" title="Medan ini diklasifikasikan Sulit/Rahsia">
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clipRule="evenodd" />
            </svg>
            Terhad
        </span>
    );
}

export function Masked({ field, masked, children }: { field: string; masked: string[] | undefined; children: ReactNode }) {
    return masked?.includes(field) ? <Terhad /> : <>{children}</>;
}

export function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-body-xs text-danger-600">{message}</p>;
}

export function SelectField({
    id,
    label,
    value,
    onChange,
    options,
    placeholder = 'Semua',
    required,
    error,
    disabled,
    hint,
}: {
    id: string;
    label: ReactNode;
    value: string;
    onChange: (v: string) => void;
    options: Array<{ kod: string | number; nama: string }>;
    placeholder?: string | null;
    required?: boolean;
    error?: string;
    disabled?: boolean;
    hint?: ReactNode;
}) {
    return (
        <div>
            <InputLabel htmlFor={id} className="mb-1.5">
                {label} {required && <span className="text-danger-600">*</span>}
            </InputLabel>
            <select
                id={id}
                className={`${inputClass} ${error ? 'border-danger-500' : ''}`}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                aria-invalid={!!error}
            >
                {placeholder !== null && <option value="">{placeholder}</option>}
                {options.map((o) => (
                    <option key={String(o.kod)} value={String(o.kod)}>
                        {o.nama}
                    </option>
                ))}
            </select>
            {hint && !error && <p className="mt-1 text-body-xs text-txt-black-500">{hint}</p>}
            <FieldError message={error} />
        </div>
    );
}

export function ExportMenu({ onExport, label = 'Eksport', formats }: { onExport: (format: string) => Promise<void>; label?: string; formats?: string[] }) {
    const [busy, setBusy] = useState(false);
    const list = formats ? FORMAT_EKSPORT.filter((f) => formats.includes(f.kod)) : FORMAT_EKSPORT;

    async function run(format: string) {
        setBusy(true);
        try {
            await onExport(format);
            toast.success('Fail eksport dimuat turun.');
        } catch (e) {
            toast.error(errorMessage(e, 'Eksport gagal.'));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Dropdown
            trigger={
                <Button variant="secondary" disabled={busy} aria-haspopup="menu">
                    {busy ? 'Mengeksport…' : label}
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                    </svg>
                </Button>
            }
        >
            {list.map((f) => (
                <button
                    key={f.kod}
                    type="button"
                    role="menuitem"
                    onClick={() => run(f.kod)}
                    className="block w-full px-4 py-2 text-start text-body-sm text-txt-black-700 hover:bg-bg-washed"
                >
                    {f.label}
                </button>
            ))}
        </Dropdown>
    );
}

export function Pager({ meta, page, onPage }: { meta: Meta | undefined; page: number; onPage: (p: number) => void }) {
    if (!meta || meta.last_page <= 1) return null;
    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-2 text-body-sm text-txt-black-500">
            <span>
                Memaparkan {meta.from ?? 0} hingga {meta.to ?? 0} daripada {meta.total}
            </span>
            <div className="flex gap-2">
                <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
                    Sebelumnya
                </Button>
                <Button variant="secondary" size="sm" disabled={page >= meta.last_page} onClick={() => onPage(page + 1)}>
                    Seterusnya
                </Button>
            </div>
        </div>
    );
}

export function EmptyRow({ colSpan, children }: { colSpan: number; children: ReactNode }) {
    return (
        <tr>
            <td colSpan={colSpan} className="text-center text-txt-black-500 py-6">
                {children}
            </td>
        </tr>
    );
}

/** Asks for a mandatory written reason (arkib, pulih, padam …). */
export function ReasonModal({
    open,
    title,
    description,
    confirmLabel,
    danger,
    onClose,
    onConfirm,
}: {
    open: boolean;
    title: string;
    description?: ReactNode;
    confirmLabel: string;
    danger?: boolean;
    onClose: () => void;
    onConfirm: (sebab: string) => Promise<unknown>;
}) {
    const [sebab, setSebab] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    function close() {
        setSebab('');
        setError(null);
        onClose();
    }

    async function submit(e: FormEvent) {
        e.preventDefault();
        if (!sebab.trim()) {
            setError('Sebab wajib diisi.');
            return;
        }
        setBusy(true);
        try {
            await onConfirm(sebab.trim());
            setSebab('');
            setError(null);
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={open} onClose={close}>
            <form onSubmit={submit} className="p-6 space-y-4">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{title}</h2>
                {description && <div className="text-body-sm text-txt-black-700">{description}</div>}
                <Textarea label="Sebab" name="sebab" value={sebab} onChange={setSebab} required maxLength={500} rows={3} />
                {error && <Alert type="error">{error}</Alert>}
                <div className="flex justify-end gap-3">
                    <Button variant="secondary" onClick={close}>
                        Batal
                    </Button>
                    <Button type="submit" variant={danger ? 'danger' : 'primary'} disabled={busy}>
                        {busy ? 'Memproses…' : confirmLabel}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

/** Search-as-you-type user picker (GET /pro/pengguna). */
export function UserPicker({
    id,
    label,
    value,
    onChange,
    error,
    hint,
}: {
    id: string;
    label: ReactNode;
    value: Pengguna | null;
    onChange: (u: Pengguna | null) => void;
    error?: string;
    hint?: ReactNode;
}) {
    const [term, setTerm] = useState('');
    const [debounced, setDebounced] = useState('');
    useEffect(() => {
        const t = setTimeout(() => setDebounced(term.trim()), 300);
        return () => clearTimeout(t);
    }, [term]);
    const { data, isFetching } = useQuery({
        queryKey: ['pro', 'pengguna', debounced],
        queryFn: () => proApi.pengguna(debounced).then((r) => r.data),
        enabled: debounced.length >= 2,
    });

    return (
        <div>
            <InputLabel htmlFor={id} className="mb-1.5">
                {label}
            </InputLabel>
            {value ? (
                <div className="flex items-center justify-between gap-2 rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm">
                    <span className="min-w-0 break-words">
                        {value.name} {value.email && <span className="text-txt-black-500">({value.email})</span>}
                    </span>
                    <button type="button" className="text-primary-600 font-medium flex-shrink-0" onClick={() => onChange(null)}>
                        Tukar
                    </button>
                </div>
            ) : (
                <>
                    <input
                        id={id}
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Cari nama atau e-mel (min. 2 aksara)"
                        className={inputClass}
                        autoComplete="off"
                    />
                    {debounced.length >= 2 && (
                        <ul className="mt-1 max-h-48 overflow-y-auto rounded-md border border-otl-gray-200 bg-bg-white">
                            {isFetching && <li className="px-3 py-2 text-body-sm text-txt-black-500">Mencari…</li>}
                            {!isFetching && (data ?? []).length === 0 && <li className="px-3 py-2 text-body-sm text-txt-black-500">Tiada pengguna ditemui.</li>}
                            {data?.map((u) => (
                                <li key={u.id}>
                                    <button
                                        type="button"
                                        className="w-full text-left px-3 py-2 text-body-sm hover:bg-bg-washed"
                                        onClick={() => {
                                            setTerm('');
                                            onChange(u);
                                        }}
                                    >
                                        {u.name} <span className="text-txt-black-500">({u.email})</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
            {hint && !error && <p className="mt-1 text-body-xs text-txt-black-500">{hint}</p>}
            <FieldError message={error} />
        </div>
    );
}

export function Loading({ label = 'Memuatkan…' }: { label?: string }) {
    return <p className="text-body-sm text-txt-black-500 py-6 text-center">{label}</p>;
}
