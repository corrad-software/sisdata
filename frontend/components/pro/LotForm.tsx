'use client';

import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button, LinkButton } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Callout } from '@/components/ui/Callout';
import { Badge } from '@/components/ui/Badge';
import { InputLabel } from '@/components/ui/InputLabel';
import { Card, FieldError, SelectField, Terhad, UserPicker, inputClass } from '@/components/pro/common';
import { ApiError } from '@/lib/api';
import { MEDAN_LABEL, errorMessage, fieldErrors, proApi, useRujukan, type InputLot, type KemasKiniLotRespons, type LotButiran, type Pengguna } from '@/lib/pro';

type Values = {
    no_lot: string;
    kod_daerah: string;
    kod_mukim: string;
    keluasan: string;
    unit_keluasan: string;
    kategori_guna_tanah: string;
    zoning: string;
    syarat_nyata: string;
    bahagian_pemilik: string;
    status_lot: string;
    klasifikasi: string;
    tag: string;
    catatan: string;
};

const EDITABLE_STATUS = ['draf', 'aktif', 'tidak_aktif'];

function initialValues(lot?: LotButiran): Values {
    return {
        no_lot: lot?.no_lot ?? '',
        kod_daerah: lot?.kod_daerah ?? '',
        kod_mukim: lot?.kod_mukim ?? '',
        keluasan: lot?.keluasan !== null && lot?.keluasan !== undefined ? String(lot.keluasan) : '',
        unit_keluasan: lot?.unit_keluasan ?? 'hektar',
        kategori_guna_tanah: lot?.kategori_guna_tanah ?? '',
        zoning: lot?.zoning ?? '',
        syarat_nyata: lot?.syarat_nyata ?? '',
        bahagian_pemilik: lot?.bahagian_pemilik ?? '',
        status_lot: lot?.status_lot ?? 'draf',
        klasifikasi: lot?.klasifikasi ?? 'dalaman',
        tag: (lot?.tag ?? []).join(', '),
        catatan: lot?.catatan ?? '',
    };
}

function toPayload(v: Values): InputLot {
    const n = (s: string) => (s.trim() === '' ? null : s.trim());
    return {
        no_lot: n(v.no_lot),
        kod_daerah: n(v.kod_daerah),
        kod_mukim: n(v.kod_mukim),
        keluasan: n(v.keluasan),
        unit_keluasan: n(v.unit_keluasan),
        kategori_guna_tanah: n(v.kategori_guna_tanah),
        zoning: n(v.zoning),
        syarat_nyata: n(v.syarat_nyata),
        bahagian_pemilik: n(v.bahagian_pemilik),
        status_lot: n(v.status_lot),
        klasifikasi: n(v.klasifikasi),
        tag: v.tag
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean),
        catatan: n(v.catatan),
    };
}

export function LotForm({ lot }: { lot?: LotButiran }) {
    const router = useRouter();
    const queryClient = useQueryClient();
    const { data: ruj } = useRujukan();
    const editing = !!lot;
    const masked = lot?.medan_disamarkan ?? [];
    const [initial] = useState(() => initialValues(lot));
    const [v, setV] = useState<Values>(initial);
    const [owner, setOwner] = useState<Pengguna | null>(lot?.id_data_owner ? { id: lot.id_data_owner, name: lot.nama_data_owner ?? `Pengguna #${lot.id_data_owner}`, email: '' } : null);
    const [steward, setSteward] = useState<Pengguna | null>(
        lot?.id_data_steward ? { id: lot.id_data_steward, name: lot.nama_data_steward ?? `Pengguna #${lot.id_data_steward}`, email: '' } : null,
    );
    const [justifikasi, setJustifikasi] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [conflict, setConflict] = useState(false);
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState<KemasKiniLotRespons | null>(null);

    const critical = new Set(ruj?.medan_kritikal ?? []);
    const set = (key: keyof Values) => (value: string) => setV((cur) => ({ ...cur, [key]: value, ...(key === 'kod_daerah' ? { kod_mukim: '' } : {}) }));

    // Changed fields (edit mode), never including fields masked for this user.
    const full = toPayload(v);
    const base = toPayload(initial);
    const changed: InputLot = {};
    (Object.keys(full) as Array<keyof InputLot>).forEach((k) => {
        if (masked.includes(k)) return;
        if (JSON.stringify(full[k]) !== JSON.stringify(base[k])) (changed as Record<string, unknown>)[k] = full[k];
    });
    if ((owner?.id ?? null) !== (lot?.id_data_owner ?? null)) changed.id_data_owner = owner?.id ?? null;
    if ((steward?.id ?? null) !== (lot?.id_data_steward ?? null)) changed.id_data_steward = steward?.id ?? null;
    const changedKeys = Object.keys(changed);
    const criticalChanged = editing ? changedKeys.filter((k) => critical.has(k)) : [];

    async function submit(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        setError(null);
        setConflict(false);

        if (editing && changedKeys.length === 0) {
            setError('Tiada perubahan untuk disimpan.');
            return;
        }
        if (criticalChanged.length > 0 && !justifikasi.trim()) {
            setErrors({ justifikasi: 'Justifikasi wajib diisi apabila medan kritikal diubah.' });
            return;
        }

        setBusy(true);
        try {
            if (!lot) {
                const res = await proApi.ciptaLot({ ...full, id_data_owner: owner?.id ?? null, id_data_steward: steward?.id ?? null });
                toast.success(res.message);
                queryClient.invalidateQueries({ queryKey: ['pro'] });
                router.push(`/admin/pro/lot/${res.data.id}`);
                return;
            }
            const res = await proApi.kemasKiniLot(lot.id, {
                ...changed,
                justifikasi: criticalChanged.length > 0 ? justifikasi.trim() : undefined,
                versi_rekod: lot.versi_rekod,
            });
            queryClient.invalidateQueries({ queryKey: ['pro'] });
            if (res.tertunda) {
                setResult(res);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                toast.success(res.message);
                router.push(`/admin/pro/lot/${lot.id}`);
            }
        } catch (err) {
            if (err instanceof ApiError && err.status === 409) setConflict(true);
            setErrors(fieldErrors(err));
            setError(errorMessage(err, 'Rekod tidak dapat disimpan.'));
        } finally {
            setBusy(false);
        }
    }

    if (result?.tertunda) {
        return (
            <Card>
                <Callout type="warning" title="Perubahan medan kritikal menunggu pengesahan">
                    <p>{result.message}</p>
                    {result.diterapkan.length > 0 && (
                        <p className="mt-2">
                            Telah diterapkan serta-merta: <strong>{result.diterapkan.map((m) => MEDAN_LABEL[m] ?? m).join(', ')}</strong>.
                        </p>
                    )}
                    <p className="mt-2">
                        Menunggu pengesahan pegawai kedua melalui Workflow: <strong>{result.tertunda.medan.map((m) => MEDAN_LABEL[m] ?? m).join(', ')}</strong>.
                    </p>
                </Callout>
                <div className="flex flex-wrap gap-2">
                    <LinkButton href={`/admin/pro/lot/${result.data.id}`}>Kembali ke profil</LinkButton>
                    {result.tertunda.id_aliran_kerja && (
                        <LinkButton variant="secondary" href={`/admin/wfe/instans/${result.tertunda.id_aliran_kerja}`}>
                            Lihat aliran kerja
                        </LinkButton>
                    )}
                </div>
            </Card>
        );
    }

    const mukim = (ruj?.mukim ?? []).filter((m) => !v.kod_daerah || m.daerah_kod === v.kod_daerah);
    const statusOptions = (ruj?.status_lot ?? []).filter((s) => EDITABLE_STATUS.includes(s.kod) || s.kod === initial.status_lot);

    /** Label with a "kritikal" marker for fields that need second-officer confirmation. */
    const L = (field: string, text: string, required?: boolean): ReactNode => (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            {text}
            {required && <span className="text-danger-600">*</span>}
            {editing && critical.has(field) && (
                <Badge variant="warning" shape="tag">
                    Kritikal
                </Badge>
            )}
        </span>
    );

    const text = (field: keyof Values, label: string, opts: { required?: boolean; type?: string; placeholder?: string; step?: string } = {}) => (
        <div>
            <InputLabel htmlFor={`lot-${field}`} className="mb-1.5">
                {L(field, label, opts.required)}
            </InputLabel>
            {masked.includes(field) ? (
                <div className="rounded-md border border-otl-gray-200 bg-bg-washed px-3 py-2 text-body-sm">
                    <Terhad />
                </div>
            ) : (
                <input
                    id={`lot-${field}`}
                    type={opts.type ?? 'text'}
                    step={opts.step}
                    value={v[field]}
                    onChange={(e) => set(field)(e.target.value)}
                    placeholder={opts.placeholder}
                    className={`${inputClass} ${errors[field] ? 'border-danger-500' : ''}`}
                    aria-invalid={!!errors[field]}
                />
            )}
            <FieldError message={errors[field]} />
        </div>
    );

    return (
        <form onSubmit={submit} className="space-y-6" noValidate>
            {editing && critical.size > 0 && (
                <Callout type="info" title="Medan kritikal">
                    Perubahan pada medan bertanda <strong>Kritikal</strong> ({[...critical].map((m) => MEDAN_LABEL[m] ?? m).join(', ')}) memerlukan justifikasi dan hanya
                    berkuat kuasa selepas disahkan oleh pegawai kedua melalui Workflow.
                </Callout>
            )}

            {conflict && (
                <Alert type="warning">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                        <span>Rekod ini telah dikemas kini oleh pengguna lain sejak anda membukanya. Muat semula untuk melihat versi terkini.</span>
                        <Button size="sm" variant="secondary" onClick={() => window.location.reload()}>
                            Muat semula
                        </Button>
                    </div>
                </Alert>
            )}
            {error && !conflict && <Alert type="error">{error}</Alert>}

            <Card title="Identiti lot">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {text('no_lot', 'No. Lot', { required: true, placeholder: 'cth. Lot 12345 / PT 678' })}
                    <SelectField
                        id="lot-bahagian"
                        label={L('bahagian_pemilik', 'Bahagian Pemilik MAIS', true)}
                        value={v.bahagian_pemilik}
                        onChange={set('bahagian_pemilik')}
                        options={ruj?.bahagian_pemilik ?? []}
                        placeholder="Pilih bahagian"
                        error={errors.bahagian_pemilik}
                        disabled={masked.includes('bahagian_pemilik')}
                    />
                    <SelectField
                        id="lot-daerah"
                        label={L('kod_daerah', 'Daerah', true)}
                        value={v.kod_daerah}
                        onChange={set('kod_daerah')}
                        options={ruj?.daerah ?? []}
                        placeholder="Pilih daerah"
                        error={errors.kod_daerah}
                    />
                    <SelectField
                        id="lot-mukim"
                        label={L('kod_mukim', 'Mukim', true)}
                        value={v.kod_mukim}
                        onChange={set('kod_mukim')}
                        options={mukim}
                        placeholder={v.kod_daerah ? 'Pilih mukim' : 'Pilih daerah dahulu'}
                        error={errors.kod_mukim}
                        disabled={!v.kod_daerah}
                    />
                    {text('keluasan', 'Keluasan', { required: true, type: 'number', step: 'any' })}
                    <SelectField
                        id="lot-unit"
                        label={L('unit_keluasan', 'Unit Keluasan')}
                        value={v.unit_keluasan}
                        onChange={set('unit_keluasan')}
                        options={ruj?.unit_keluasan ?? []}
                        placeholder={null}
                        error={errors.unit_keluasan}
                    />
                </div>
            </Card>

            <Card title="Guna tanah & syarat">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SelectField
                        id="lot-kategori"
                        label={L('kategori_guna_tanah', 'Kategori Guna Tanah')}
                        value={v.kategori_guna_tanah}
                        onChange={set('kategori_guna_tanah')}
                        options={ruj?.kategori_guna_tanah ?? []}
                        placeholder="Tiada"
                        error={errors.kategori_guna_tanah}
                    />
                    {text('zoning', 'Zoning')}
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="lot-syarat" className="mb-1.5">
                            {L('syarat_nyata', 'Syarat Nyata')}
                        </InputLabel>
                        {masked.includes('syarat_nyata') ? (
                            <div className="rounded-md border border-otl-gray-200 bg-bg-washed px-3 py-2 text-body-sm">
                                <Terhad />
                            </div>
                        ) : (
                            <textarea id="lot-syarat" rows={3} maxLength={5000} className={inputClass} value={v.syarat_nyata} onChange={(e) => set('syarat_nyata')(e.target.value)} />
                        )}
                        <FieldError message={errors.syarat_nyata} />
                    </div>
                </div>
            </Card>

            <Card title="Status, klasifikasi & tadbir urus">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SelectField
                        id="lot-status"
                        label={L('status_lot', 'Status Lot')}
                        value={v.status_lot}
                        onChange={set('status_lot')}
                        options={statusOptions}
                        placeholder={null}
                        error={errors.status_lot}
                    />
                    <SelectField
                        id="lot-klasifikasi"
                        label={L('klasifikasi', 'Klasifikasi')}
                        value={v.klasifikasi}
                        onChange={set('klasifikasi')}
                        options={ruj?.klasifikasi ?? []}
                        placeholder={null}
                        error={errors.klasifikasi}
                    />
                    <UserPicker id="lot-owner" label={L('id_data_owner', 'Data Owner')} value={owner} onChange={setOwner} error={errors.id_data_owner} />
                    <UserPicker id="lot-steward" label={L('id_data_steward', 'Data Steward')} value={steward} onChange={setSteward} error={errors.id_data_steward} />
                    <div className="sm:col-span-2">{text('tag', 'Tag', { placeholder: 'Pisahkan dengan koma, cth. wakaf khas, tepi jalan' })}</div>
                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="lot-catatan" className="mb-1.5">
                            Catatan
                        </InputLabel>
                        <textarea id="lot-catatan" rows={3} maxLength={2000} className={inputClass} value={v.catatan} onChange={(e) => set('catatan')(e.target.value)} />
                        <FieldError message={errors.catatan} />
                    </div>
                </div>
            </Card>

            {editing && (
                <Card title="Justifikasi perubahan">
                    {criticalChanged.length > 0 ? (
                        <Alert type="warning">
                            Anda mengubah medan kritikal: <strong>{criticalChanged.map((m) => MEDAN_LABEL[m] ?? m).join(', ')}</strong>. Justifikasi wajib dan perubahan ini akan
                            dihantar untuk pengesahan pegawai kedua.
                        </Alert>
                    ) : (
                        <p className="text-body-sm text-txt-black-500">Justifikasi hanya wajib apabila medan kritikal diubah.</p>
                    )}
                    <div>
                        <InputLabel htmlFor="lot-justifikasi" className="mb-1.5">
                            Justifikasi {criticalChanged.length > 0 && <span className="text-danger-600">*</span>}
                        </InputLabel>
                        <textarea
                            id="lot-justifikasi"
                            rows={3}
                            maxLength={1000}
                            className={`${inputClass} ${errors.justifikasi ? 'border-danger-500' : ''}`}
                            value={justifikasi}
                            onChange={(e) => setJustifikasi(e.target.value)}
                            aria-invalid={!!errors.justifikasi}
                        />
                        <FieldError message={errors.justifikasi} />
                    </div>
                </Card>
            )}

            <div className="flex flex-wrap justify-end gap-3">
                <Link href={lot ? `/admin/pro/lot/${lot.id}` : '/admin/pro/lot'} className="inline-flex items-center px-4 py-2 text-body-sm font-medium text-txt-black-700 hover:underline">
                    Batal
                </Link>
                <Button type="submit" disabled={busy}>
                    {busy ? 'Menyimpan…' : editing ? 'Simpan perubahan' : 'Simpan lot'}
                </Button>
            </div>
        </form>
    );
}
