'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { InputLabel } from '@/components/ui/InputLabel';
import { Card, EmptyRow, ExportMenu, FieldError, SelectField, UserPicker, inputClass } from '@/components/pro/common';
import { BAHAGIAN_LABEL, errorMessage, fieldErrors, fmtTarikh, namaRuj, proApi, useCan, useRujukan, type Pengguna, type Stewardship } from '@/lib/pro';

type Form = {
    komponen_data: string;
    entiti_berkaitan: string;
    jawatan_data_owner: string;
    bahagian_data_owner: string;
    jawatan_data_steward: string;
    bahagian_data_steward: string;
    bahagian_pemilik: string;
    kod_daerah: string;
    peranan_tanggungjawab: string;
    tarikh_berkuat_kuasa: string;
    status: string;
};

const EMPTY: Form = {
    komponen_data: '',
    entiti_berkaitan: '',
    jawatan_data_owner: '',
    bahagian_data_owner: '',
    jawatan_data_steward: '',
    bahagian_data_steward: '',
    bahagian_pemilik: '',
    kod_daerah: '',
    peranan_tanggungjawab: '',
    tarikh_berkuat_kuasa: '',
    status: 'aktif',
};

function StewardshipModal({ entry, open, onClose }: { entry: Stewardship | null; open: boolean; onClose: () => void }) {
    const queryClient = useQueryClient();
    const { data: ruj } = useRujukan();
    const [f, setF] = useState<Form>(() =>
        entry
            ? {
                  komponen_data: entry.komponen_data,
                  entiti_berkaitan: entry.entiti_berkaitan,
                  jawatan_data_owner: entry.jawatan_data_owner,
                  bahagian_data_owner: entry.bahagian_data_owner,
                  jawatan_data_steward: entry.jawatan_data_steward,
                  bahagian_data_steward: entry.bahagian_data_steward,
                  bahagian_pemilik: entry.bahagian_pemilik ?? '',
                  kod_daerah: entry.kod_daerah ?? '',
                  peranan_tanggungjawab: entry.peranan_tanggungjawab,
                  tarikh_berkuat_kuasa: (entry.tarikh_berkuat_kuasa ?? '').slice(0, 10),
                  status: entry.status,
              }
            : EMPTY,
    );
    const [owner, setOwner] = useState<Pengguna | null>(entry?.id_data_owner ? { id: entry.id_data_owner, name: entry.nama_data_owner ?? `#${entry.id_data_owner}`, email: '' } : null);
    const [steward, setSteward] = useState<Pengguna | null>(
        entry?.id_data_steward ? { id: entry.id_data_steward, name: entry.nama_data_steward ?? `#${entry.id_data_steward}`, email: '' } : null,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const set = (k: keyof Form) => (v: string) => setF((cur) => ({ ...cur, [k]: v }));

    async function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setErrors({});
        setError(null);
        try {
            const res = await proApi.simpanStewardship(
                { ...f, bahagian_pemilik: f.bahagian_pemilik || null, kod_daerah: f.kod_daerah || null, id_data_owner: owner?.id ?? null, id_data_steward: steward?.id ?? null },
                entry?.id,
            );
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro', 'stewardship'] });
            onClose();
        } catch (err) {
            setErrors(fieldErrors(err));
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    const text = (k: keyof Form, label: string, required = true, placeholder?: string) => (
        <div>
            <InputLabel htmlFor={`st-${k}`} className="mb-1.5">
                {label} {required && <span className="text-danger-600">*</span>}
            </InputLabel>
            <input id={`st-${k}`} className={`${inputClass} ${errors[k] ? 'border-danger-500' : ''}`} value={f[k]} onChange={(e) => set(k)(e.target.value)} placeholder={placeholder} />
            <FieldError message={errors[k]} />
        </div>
    );

    return (
        <Modal open={open} onClose={onClose}>
            <form onSubmit={submit} className="p-6 space-y-4 max-h-[85vh] overflow-y-auto" noValidate>
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{entry ? 'Kemas kini stewardship' : 'Tambah stewardship'}</h2>
                {text('komponen_data', 'Komponen data', true, 'cth. Lot tanah wakaf')}
                {text('entiti_berkaitan', 'Entiti berkaitan', true, 'Jadual, dipisah koma — cth. lot_tanah,data_spatial')}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {text('jawatan_data_owner', 'Jawatan data owner')}
                    {text('bahagian_data_owner', 'Bahagian data owner')}
                    {text('jawatan_data_steward', 'Jawatan data steward')}
                    {text('bahagian_data_steward', 'Bahagian data steward')}
                </div>
                <UserPicker id="st-owner" label="Pegawai pemilik data" value={owner} onChange={setOwner} error={errors.id_data_owner} />
                <UserPicker id="st-steward" label="Pegawai data steward" value={steward} onChange={setSteward} error={errors.id_data_steward} />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SelectField
                        id="st-bahagian"
                        label="Hadkan kepada bahagian lot"
                        value={f.bahagian_pemilik}
                        onChange={set('bahagian_pemilik')}
                        options={ruj?.bahagian_pemilik ?? []}
                        placeholder="Semua bahagian"
                        error={errors.bahagian_pemilik}
                    />
                    <SelectField id="st-daerah" label="Hadkan kepada daerah" value={f.kod_daerah} onChange={set('kod_daerah')} options={ruj?.daerah ?? []} placeholder="Semua daerah" error={errors.kod_daerah} />
                    <div>
                        <InputLabel htmlFor="st-tarikh" className="mb-1.5">
                            Tarikh berkuat kuasa <span className="text-danger-600">*</span>
                        </InputLabel>
                        <input id="st-tarikh" type="date" className={inputClass} value={f.tarikh_berkuat_kuasa} onChange={(e) => set('tarikh_berkuat_kuasa')(e.target.value)} />
                        <FieldError message={errors.tarikh_berkuat_kuasa} />
                    </div>
                    <SelectField
                        id="st-status"
                        label="Status"
                        value={f.status}
                        onChange={set('status')}
                        options={[
                            { kod: 'aktif', nama: 'Aktif' },
                            { kod: 'tidak_aktif', nama: 'Tidak aktif' },
                        ]}
                        placeholder={null}
                        error={errors.status}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="st-peranan" className="mb-1.5">
                        Peranan dan tanggungjawab <span className="text-danger-600">*</span>
                    </InputLabel>
                    <textarea id="st-peranan" rows={4} maxLength={2000} className={inputClass} value={f.peranan_tanggungjawab} onChange={(e) => set('peranan_tanggungjawab')(e.target.value)} />
                    <FieldError message={errors.peranan_tanggungjawab} />
                </div>
                {error && <Alert type="error">{error}</Alert>}
                <div className="flex justify-end gap-3">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function StewardshipPage() {
    const can = useCan();
    const { data: ruj } = useRujukan();
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'stewardship'], queryFn: () => proApi.stewardship().then((r) => r.data) });
    const [editing, setEditing] = useState<Stewardship | null>(null);
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);
    const rows = data ?? [];

    function openModal(entry: Stewardship | null) {
        setEditing(entry);
        setFormKey((k) => k + 1);
        setOpen(true);
    }

    return (
        <PageContainer>
            <Card
                title="Daftar stewardship data"
                actions={
                    <>
                        <ExportMenu onExport={(f) => proApi.eksportStewardship(f)} />
                        {can('pro.stewardship.kemas_kini') && <Button onClick={() => openModal(null)}>Tambah</Button>}
                    </>
                }
            >
                <p className="text-body-sm text-txt-black-500">Pemilik data dan data steward bagi setiap komponen data induk. Tugasan kualiti data dihalakan kepada steward mengikut daftar ini.</p>
                {error ? (
                    <Alert type="error">{errorMessage(error)}</Alert>
                ) : (
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>Komponen data</th>
                                    <th className="hidden md:table-cell">Pemilik data</th>
                                    <th>Data steward</th>
                                    <th className="hidden lg:table-cell">Skop</th>
                                    <th className="hidden sm:table-cell">Status</th>
                                    <th className="hidden lg:table-cell text-right">Tugasan terbuka</th>
                                    <th>
                                        <span className="sr-only">Tindakan</span>
                                    </th>
                                </>
                            }
                        >
                            {isLoading ? (
                                <EmptyRow colSpan={7}>Memuatkan…</EmptyRow>
                            ) : rows.length === 0 ? (
                                <EmptyRow colSpan={7}>Tiada rekod stewardship.</EmptyRow>
                            ) : (
                                rows.map((s) => (
                                    <tr key={s.id}>
                                        <td className="min-w-[12rem]">
                                            <span className="font-medium text-txt-black-900">{s.komponen_data}</span>
                                            <span className="block text-body-xs text-txt-black-500 font-mono break-all">{s.entiti_berkaitan}</span>
                                            <span className="block text-body-xs text-txt-black-500">Berkuat kuasa {fmtTarikh(s.tarikh_berkuat_kuasa)}</span>
                                        </td>
                                        <td className="hidden md:table-cell min-w-[10rem]">
                                            {s.jawatan_data_owner}
                                            <span className="block text-body-xs text-txt-black-500">
                                                {s.bahagian_data_owner}
                                                {s.nama_data_owner ? ` · ${s.nama_data_owner}` : ''}
                                            </span>
                                        </td>
                                        <td className="min-w-[10rem]">
                                            {s.jawatan_data_steward}
                                            <span className="block text-body-xs text-txt-black-500">
                                                {s.bahagian_data_steward}
                                                {s.nama_data_steward ? ` · ${s.nama_data_steward}` : ''}
                                            </span>
                                        </td>
                                        <td className="hidden lg:table-cell">
                                            {s.bahagian_pemilik ? (BAHAGIAN_LABEL[s.bahagian_pemilik] ?? s.bahagian_pemilik) : 'Semua bahagian'}
                                            <span className="block text-body-xs text-txt-black-500">{s.kod_daerah ? namaRuj(ruj?.daerah, s.kod_daerah) : 'Semua daerah'}</span>
                                        </td>
                                        <td className="hidden sm:table-cell">
                                            <Badge variant={s.status === 'aktif' ? 'success' : 'neutral'}>{s.status === 'aktif' ? 'Aktif' : 'Tidak aktif'}</Badge>
                                        </td>
                                        <td className="hidden lg:table-cell text-right">{s.bil_tugasan_terbuka}</td>
                                        <td>
                                            {can('pro.stewardship.kemas_kini') && (
                                                <Button size="sm" variant="secondary" onClick={() => openModal(s)}>
                                                    Kemas kini
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </div>
                )}
            </Card>
            <StewardshipModal key={formKey} entry={editing} open={open} onClose={() => setOpen(false)} />
        </PageContainer>
    );
}
