'use client';

import { useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { InputLabel } from '@/components/ui/InputLabel';
import { ApiError } from '@/lib/api';
import {
    dmsApi,
    KLASIFIKASI_LABEL,
    muatNaikFail,
    saizMesra,
    type Kategori,
    type KeputusanFail,
    type Klasifikasi,
    type MedanMetadata,
} from '@/lib/dms';

interface Item {
    key: string;
    file: File;
    progress: number;
    state: 'menunggu' | 'memuat_naik' | 'diterima' | 'ditolak';
    result?: KeputusanFail;
    clientError?: string;
}

interface UploadDialogProps {
    open: boolean;
    onClose: () => void;
    onUploaded?: () => void;
    /** Attach to a record (two-way link, SRS-DMS-16). */
    ownerType?: string;
    ownerId?: string | number;
    defaultCategory?: string;
    /** Frontend path of the parent record, shown as the document's deep link. */
    ownerLink?: string;
    sourceModule?: string;
}

const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

/** SRS-DMS-02: drag-and-drop batch upload (≥ 20 files), per-file progress and rejection reasons. */
export function UploadDialog({ open, onClose, onUploaded, ownerType, ownerId, defaultCategory, ownerLink, sourceModule }: UploadDialogProps) {
    const [items, setItems] = useState<Item[]>([]);
    const [dragging, setDragging] = useState(false);
    const [kategori, setKategori] = useState(defaultCategory ?? '');
    const [klasifikasi, setKlasifikasi] = useState<Klasifikasi | ''>('');
    const [tajuk, setTajuk] = useState('');
    const [tarikhDokumen, setTarikhDokumen] = useState('');
    const [tarikhLuput, setTarikhLuput] = useState('');
    const [kodDaerah, setKodDaerah] = useState('');
    const [kodMukim, setKodMukim] = useState('');
    const [kataKunci, setKataKunci] = useState('');
    const [extra, setExtra] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const { data: config } = useQuery({ queryKey: ['dms', 'konfigurasi-muat-naik'], queryFn: dmsApi.konfigurasiMuatNaik, enabled: open });
    const { data: kategoriList } = useQuery({ queryKey: ['dms', 'kategori'], queryFn: () => dmsApi.kategori(), enabled: open });

    const categories: Kategori[] = kategoriList?.data ?? [];
    const selected = categories.find((k) => k.kod === kategori);
    const fields: MedanMetadata[] = selected?.medan.filter((m) => m.aktif) ?? [];
    const limits = config?.data;
    const accept = limits?.sambungan.map((e) => `.${e}`).join(',');

    function limitFor(file: File): number | null {
        if (!limits) return null;
        const ext = file.name.includes('.') ? file.name.split('.').pop()!.toLowerCase() : '';
        const group = limits.kumpulan[ext] ?? 'pejabat';
        return selected?.had_saiz_mb ?? limits.had_saiz_mb[group];
    }

    function addFiles(list: FileList | File[]) {
        const max = limits?.had_fail_kelompok ?? 50;
        // Copy now: the input is cleared right after, which empties a live FileList.
        const files = Array.from(list);
        setItems((current) => {
            const next = [...current];
            for (const file of files) {
                if (next.length >= max) break;
                const limit = limitFor(file);
                next.push({
                    key: `${file.name}-${file.size}-${file.lastModified}-${Math.random()}`,
                    file,
                    progress: 0,
                    state: 'menunggu',
                    // Early hint only — the server is authoritative.
                    clientError: limit && file.size > limit * 1024 * 1024 ? `Saiz ${saizMesra(file.size)} melebihi had ${limit} MB.` : undefined,
                });
            }
            return next;
        });
    }

    function onDrop(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setDragging(false);
        if (e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
    }

    function reset() {
        setItems([]);
        setFormError(null);
        setTajuk('');
        setExtra({});
    }

    async function start() {
        if (!kategori) {
            setFormError('Sila pilih kategori dokumen.');
            return;
        }
        setFormError(null);
        setBusy(true);
        const transaction = crypto.randomUUID();
        const path = ownerType && ownerId !== undefined ? `/dms/rekod/${ownerType}/${ownerId}/dokumen` : '/dms/dokumen';
        const pending = items.filter((i) => i.state === 'menunggu' || i.state === 'ditolak');
        let accepted = 0;

        for (const item of pending) {
            setItems((all) => all.map((i) => (i.key === item.key ? { ...i, state: 'memuat_naik', progress: 0 } : i)));
            try {
                const result = await muatNaikFail(
                    path,
                    item.file,
                    {
                        kategori,
                        klasifikasi: klasifikasi || undefined,
                        tajuk: pending.length === 1 ? tajuk : undefined,
                        tarikh_dokumen: tarikhDokumen,
                        tarikh_luput: tarikhLuput,
                        kod_daerah: kodDaerah,
                        kod_mukim: kodMukim,
                        kata_kunci: kataKunci,
                        modul_sumber: sourceModule,
                        pautan_induk: ownerLink,
                        id_transaksi: transaction,
                        metadata: extra,
                    },
                    (p) => setItems((all) => all.map((i) => (i.key === item.key ? { ...i, progress: p } : i))),
                );
                if (result.status === 'diterima') accepted++;
                setItems((all) => all.map((i) => (i.key === item.key ? { ...i, state: result.status, result, progress: 1 } : i)));
            } catch (error) {
                const message =
                    error instanceof ApiError && error.errors
                        ? Object.values(error.errors).flat().join(' ')
                        : error instanceof Error
                          ? error.message
                          : 'Muat naik gagal.';
                setItems((all) =>
                    all.map((i) =>
                        i.key === item.key
                            ? { ...i, state: 'ditolak', result: { indeks: 0, nama_fail: i.file.name, status: 'ditolak', mesej: message } }
                            : i,
                    ),
                );
            }
        }
        setBusy(false);
        if (accepted > 0) onUploaded?.();
    }

    const done = items.length > 0 && items.every((i) => i.state === 'diterima');
    const counts = {
        diterima: items.filter((i) => i.state === 'diterima').length,
        ditolak: items.filter((i) => i.state === 'ditolak').length,
    };

    return (
        <Modal
            open={open}
            onClose={() => {
                if (busy) return;
                reset();
                onClose();
            }}
        >
            <div className="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Muat naik dokumen</h2>

                <div
                    role="button"
                    tabIndex={0}
                    aria-label="Seret dan lepas fail di sini atau klik untuk memilih fail"
                    onClick={() => inputRef.current?.click()}
                    onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && inputRef.current?.click()}
                    onDragOver={(e) => {
                        e.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={onDrop}
                    className={`flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-8 text-center cursor-pointer transition-colors ${
                        dragging ? 'border-primary-600 bg-bg-primary-50' : 'border-otl-gray-300 hover:border-primary-600'
                    }`}
                >
                    <p className="text-body-sm font-medium text-txt-black-900">Seret & lepas fail di sini, atau klik untuk pilih</p>
                    <p className="text-body-xs text-txt-black-500">
                        Sehingga {limits?.had_fail_kelompok ?? 50} fail serentak · {limits?.senarai_putih.map((s) => s.toUpperCase()).join(', ')}
                    </p>
                    {limits && (
                        <p className="text-body-xs text-txt-black-500">
                            Had: {limits.had_saiz_mb.pejabat} MB (pejabat/imej), {limits.had_saiz_mb.spatial} MB (spatial), {limits.had_transaksi_mb} MB setiap transaksi
                        </p>
                    )}
                    <input
                        ref={inputRef}
                        type="file"
                        multiple
                        accept={accept}
                        className="sr-only"
                        data-testid="dms-file-input"
                        onChange={(e) => {
                            if (e.target.files) addFiles(e.target.files);
                            e.target.value = '';
                        }}
                    />
                </div>

                {items.length > 0 && (
                    <ul className="divide-y divide-otl-divider border border-otl-gray-200 rounded-md" aria-live="polite">
                        {items.map((item) => (
                            <li key={item.key} className="px-3 py-2 space-y-1">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-body-sm text-txt-black-900 truncate min-w-0" title={item.file.name}>
                                        {item.file.name}
                                    </span>
                                    <span className="flex items-center gap-2 shrink-0">
                                        <span className="text-body-xs text-txt-black-500">{saizMesra(item.file.size)}</span>
                                        {item.state === 'diterima' && <Badge variant="success">Diterima</Badge>}
                                        {item.state === 'ditolak' && <Badge variant="danger">Ditolak</Badge>}
                                        {item.state === 'menunggu' && !busy && (
                                            <button
                                                type="button"
                                                className="text-body-xs text-danger-600 hover:underline"
                                                onClick={() => setItems((all) => all.filter((i) => i.key !== item.key))}
                                            >
                                                Buang
                                            </button>
                                        )}
                                    </span>
                                </div>
                                {item.state === 'memuat_naik' && (
                                    <div className="h-1.5 rounded-full bg-bg-washed overflow-hidden" role="progressbar" aria-valuenow={Math.round(item.progress * 100)}>
                                        <div className="h-full bg-primary-600 transition-all" style={{ width: `${Math.round(item.progress * 100)}%` }} />
                                    </div>
                                )}
                                {item.result?.mesej && <p className="text-body-xs text-txt-danger">{item.result.mesej}</p>}
                                {item.clientError && item.state === 'menunggu' && <p className="text-body-xs text-txt-warning">{item.clientError}</p>}
                            </li>
                        ))}
                    </ul>
                )}

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="dms-kategori" className="mb-1.5">
                            Kategori <span className="text-txt-danger">*</span>
                        </InputLabel>
                        <select id="dms-kategori" value={kategori} onChange={(e) => setKategori(e.target.value)} className={inputClass}>
                            <option value="">— Pilih —</option>
                            {categories.map((k) => (
                                <option key={k.kod} value={k.kod}>
                                    {k.nama}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="dms-klasifikasi" className="mb-1.5">
                            Klasifikasi keselamatan
                        </InputLabel>
                        <select
                            id="dms-klasifikasi"
                            value={klasifikasi}
                            onChange={(e) => setKlasifikasi(e.target.value as Klasifikasi | '')}
                            className={inputClass}
                        >
                            <option value="">Ikut kategori ({selected ? KLASIFIKASI_LABEL[selected.klasifikasi_lalai] : '—'})</option>
                            {(Object.keys(KLASIFIKASI_LABEL) as Klasifikasi[]).map((k) => (
                                <option key={k} value={k}>
                                    {KLASIFIKASI_LABEL[k]}
                                </option>
                            ))}
                        </select>
                    </div>
                    {items.length <= 1 && (
                        <Field label="Tajuk" id="dms-tajuk" value={tajuk} onChange={setTajuk} placeholder="Lalai: nama fail" />
                    )}
                    <Field label="Tarikh dokumen" id="dms-tarikh" type="date" value={tarikhDokumen} onChange={setTarikhDokumen} />
                    <Field label="Tarikh luput" id="dms-luput" type="date" value={tarikhLuput} onChange={setTarikhLuput} />
                    <Field label="Kod daerah" id="dms-daerah" value={kodDaerah} onChange={setKodDaerah} />
                    <Field label="Kod mukim" id="dms-mukim" value={kodMukim} onChange={setKodMukim} />
                    <Field label="Kata kunci" id="dms-kata-kunci" value={kataKunci} onChange={setKataKunci} placeholder="Pisahkan dengan koma" />
                    {fields.map((f) => (
                        <ExtraField key={f.id} field={f} value={extra[f.kod] ?? ''} onChange={(v) => setExtra((x) => ({ ...x, [f.kod]: v }))} />
                    ))}
                </div>

                {formError && <p className="text-body-sm text-txt-danger">{formError}</p>}
                {items.length > 0 && !busy && (counts.diterima > 0 || counts.ditolak > 0) && (
                    <p className="text-body-sm text-txt-black-700">
                        {counts.diterima} fail diterima, {counts.ditolak} fail ditolak.
                    </p>
                )}

                <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                    <Button
                        variant="secondary"
                        disabled={busy}
                        onClick={() => {
                            reset();
                            onClose();
                        }}
                    >
                        {done ? 'Tutup' : 'Batal'}
                    </Button>
                    <Button variant="primary" disabled={busy || items.every((i) => i.state === 'diterima')} onClick={start}>
                        {busy ? 'Memuat naik…' : counts.ditolak > 0 ? 'Cuba semula yang ditolak' : 'Muat naik'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function Field({
    label,
    id,
    value,
    onChange,
    type = 'text',
    placeholder,
}: {
    label: string;
    id: string;
    value: string;
    onChange: (v: string) => void;
    type?: string;
    placeholder?: string;
}) {
    return (
        <div>
            <InputLabel htmlFor={id} className="mb-1.5">
                {label}
            </InputLabel>
            <input id={id} type={type} value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} className={inputClass} />
        </div>
    );
}

export function ExtraField({ field, value, onChange }: { field: MedanMetadata; value: string; onChange: (v: string) => void }) {
    const id = `dms-medan-${field.kod}`;
    const label = (
        <InputLabel htmlFor={id} className="mb-1.5">
            {field.label} {field.wajib && <span className="text-txt-danger">*</span>}
        </InputLabel>
    );
    if (field.jenis === 'senarai') {
        return (
            <div>
                {label}
                <select id={id} value={value} onChange={(e) => onChange(e.target.value)} className={inputClass}>
                    <option value="">— Pilih —</option>
                    {(field.pilihan ?? []).map((p) => (
                        <option key={p} value={p}>
                            {p}
                        </option>
                    ))}
                </select>
            </div>
        );
    }
    if (field.jenis === 'ya_tidak') {
        return (
            <div>
                {label}
                <select id={id} value={value} onChange={(e) => onChange(e.target.value)} className={inputClass}>
                    <option value="">—</option>
                    <option value="1">Ya</option>
                    <option value="0">Tidak</option>
                </select>
            </div>
        );
    }
    return (
        <div>
            {label}
            <input
                id={id}
                type={field.jenis === 'tarikh' ? 'date' : field.jenis === 'nombor' ? 'number' : 'text'}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={inputClass}
            />
        </div>
    );
}
