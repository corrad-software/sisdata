'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api, ApiError } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { InputLabel } from '@/components/ui/InputLabel';
import { errorMessage, fmtDate, useCan } from '@/lib/wfe';
import { Card } from '../_components/common';

interface Holiday {
    id: number;
    tarikh: string;
    nama: string;
    jenis: 'persekutuan' | 'negeri' | 'ganti' | 'khas';
    sumber: string | null;
    catatan: string | null;
}

const JENIS: Record<Holiday['jenis'], string> = { persekutuan: 'Persekutuan', negeri: 'Negeri', ganti: 'Cuti ganti', khas: 'Cuti khas' };
const DAYS = ['', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu', 'Ahad'];

/** SRS-WFE-06: Selangor public-holiday calendar used by SLA working-day arithmetic. */
export default function KalendarPage() {
    const can = useCan();
    const queryClient = useQueryClient();
    const [year, setYear] = useState(new Date().getFullYear());
    const [editing, setEditing] = useState<Partial<Holiday> | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const { data, isLoading } = useQuery({
        queryKey: ['wfe', 'kalendar', year],
        queryFn: () =>
            api.get<{ data: Holiday[]; meta: { tahun_ada: number[]; hujung_minggu: number[]; waktu_kerja: [string, string]; negeri: string } }>(`/wfe/kalendar?tahun=${year}`),
    });

    const save = useMutation({
        mutationFn: (h: Partial<Holiday>) => (h.id ? api.put<{ message: string }>(`/wfe/kalendar/${h.id}`, h) : api.post<{ message: string }>('/wfe/kalendar', h)),
        onSuccess: (r) => {
            toast.success(r.message);
            setEditing(null);
            queryClient.invalidateQueries({ queryKey: ['wfe', 'kalendar'] });
        },
        onError: (e) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(errorMessage(e))),
    });
    const remove = useMutation({
        mutationFn: (id: number) => api.delete<{ message: string }>(`/wfe/kalendar/${id}`),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['wfe', 'kalendar'] });
        },
    });

    const editable = can('wfe.kalendar.kemas_kini');
    const years = Array.from(new Set([...(data?.meta.tahun_ada ?? []), year, new Date().getFullYear() + 1])).sort();

    function submit(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        if (editing) save.mutate(editing);
    }

    return (
        <PageContainer>
            <Card
                title={`Cuti umum ${data?.meta.negeri === 'selangor' ? 'Negeri Selangor' : (data?.meta.negeri ?? '')} ${year}`}
                actions={
                    <div className="flex gap-2 items-center">
                        <select aria-label="Tahun" value={year} onChange={(e) => setYear(Number(e.target.value))} className="rounded-md border-otl-gray-300 bg-bg-white text-body-sm">
                            {years.map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
                        {editable && (
                            <Button size="sm" onClick={() => setEditing({ jenis: 'khas', tarikh: `${year}-01-01`, nama: '' })}>
                                Tambah cuti
                            </Button>
                        )}
                    </div>
                }
            >
                {data && (
                    <p className="text-body-xs text-txt-black-500">
                        Hujung minggu: {data.meta.hujung_minggu.map((d) => DAYS[d]).join(' & ')} · Waktu bekerja (SLA jam): {data.meta.waktu_kerja[0]}–{data.meta.waktu_kerja[1]}.
                        Sumber utama: Jadual Hari Kelepasan Am, Bahagian Kabinet, JPM (kabinet.gov.my).
                    </p>
                )}
                <Table head={<><th>Tarikh</th><th>Cuti</th><th>Jenis</th><th>Sumber</th>{editable && <th className="text-right">Tindakan</th>}</>}>
                    {isLoading ? (
                        <tr><td colSpan={5} className="text-center text-txt-black-500 py-6">Memuatkan…</td></tr>
                    ) : (data?.data ?? []).length === 0 ? (
                        <tr><td colSpan={5} className="text-center text-txt-black-500 py-6">Tiada cuti direkodkan bagi tahun ini.</td></tr>
                    ) : (
                        data?.data.map((h) => (
                            <tr key={h.id}>
                                <td className="whitespace-nowrap">
                                    {fmtDate(`${h.tarikh}T00:00:00+08:00`)}
                                    <div className="text-body-xs text-txt-black-500">{DAYS[((new Date(`${h.tarikh}T00:00:00Z`).getUTCDay() + 6) % 7) + 1]}</div>
                                </td>
                                <td>
                                    {h.nama}
                                    {h.catatan && <div className="text-body-xs text-txt-warning">{h.catatan}</div>}
                                </td>
                                <td><Badge variant={h.jenis === 'ganti' ? 'warning' : h.jenis === 'negeri' ? 'info' : 'neutral'}>{JENIS[h.jenis]}</Badge></td>
                                <td className="text-body-xs text-txt-black-500 max-w-xs">{h.sumber ?? '-'}</td>
                                {editable && (
                                    <td className="text-right whitespace-nowrap">
                                        <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => setEditing(h)}>Ubah</button>
                                        <button type="button" className="ms-3 text-danger-600 text-body-sm font-medium" onClick={() => remove.mutate(h.id)}>Padam</button>
                                    </td>
                                )}
                            </tr>
                        ))
                    )}
                </Table>
            </Card>

            <Modal open={!!editing} onClose={() => setEditing(null)}>
                {editing && (
                    <form onSubmit={submit} className="p-6 space-y-4">
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{editing.id ? 'Ubah cuti' : 'Tambah cuti'}</h2>
                        <FieldInput label="Tarikh" name="tarikh" type="date" value={editing.tarikh ?? ''} onChange={(v) => setEditing({ ...editing, tarikh: v })} required error={errors.tarikh?.[0]} />
                        <FieldInput label="Nama cuti" name="nama" value={editing.nama ?? ''} onChange={(v) => setEditing({ ...editing, nama: v })} required error={errors.nama?.[0]} />
                        <div>
                            <InputLabel htmlFor="jenis" className="mb-1.5">Jenis</InputLabel>
                            <select id="jenis" value={editing.jenis} onChange={(e) => setEditing({ ...editing, jenis: e.target.value as Holiday['jenis'] })} className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-body-sm">
                                {Object.entries(JENIS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>
                        <FieldInput label="Sumber" name="sumber" value={editing.sumber ?? ''} onChange={(v) => setEditing({ ...editing, sumber: v })} help="cth. pewartaan / pengumuman rasmi" />
                        <FieldInput label="Catatan" name="catatan" value={editing.catatan ?? ''} onChange={(v) => setEditing({ ...editing, catatan: v })} />
                        <div className="flex justify-end gap-3">
                            <Button variant="secondary" onClick={() => setEditing(null)}>Batal</Button>
                            <Button type="submit" disabled={save.isPending}>Simpan</Button>
                        </div>
                    </form>
                )}
            </Modal>
        </PageContainer>
    );
}
