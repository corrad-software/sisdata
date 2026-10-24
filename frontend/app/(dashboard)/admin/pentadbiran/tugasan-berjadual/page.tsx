'use client';

import Link from 'next/link';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { InputLabel } from '@/components/ui/InputLabel';
import { Toggle } from '@/components/ui/Toggle';
import { api, ApiError } from '@/lib/api';
import { errorText, formatDateTime, KATEGORI_TUGASAN, STATUS_LABEL, statusVariant, type Tugasan, type TugasanSistem } from '@/lib/adm';

type ListResponse = { data: Tugasan[]; kategori: string[]; arahan: string[] };

export default function TugasanPage() {
    const queryClient = useQueryClient();
    const { data, isLoading } = useQuery({ queryKey: ['adm', 'tugasan'], queryFn: () => api.get<ListResponse>('/adm/tugasan') });
    const sistem = useQuery({ queryKey: ['adm', 'tugasan-sistem'], queryFn: () => api.get<{ data: TugasanSistem[] }>('/adm/tugasan/sistem') });
    const [editing, setEditing] = useState<Tugasan | 'baru' | null>(null);

    const refresh = () => queryClient.invalidateQueries({ queryKey: ['adm'] });
    const onErr = (e: unknown) => toast.error(errorText(e));

    const run = useMutation({
        mutationFn: (t: Tugasan) => api.post<{ message: string }>(`/adm/tugasan/${t.id}/jalankan`, {}),
        onSuccess: (r) => {
            toast.success(r.message);
            refresh();
        },
        onError: onErr,
    });
    const toggle = useMutation({
        mutationFn: (t: Tugasan) => api.put<{ message: string }>(`/adm/tugasan/${t.id}`, { aktif: !t.aktif }),
        onSuccess: refresh,
        onError: onErr,
    });
    const adopt = useMutation({
        mutationFn: (s: TugasanSistem) => api.post<{ message: string }>('/adm/tugasan/sistem/ambil', { kunci: s.kunci, nama: s.perihal, kategori: 'penyelenggaraan' }),
        onSuccess: (r) => {
            toast.success(r.message);
            refresh();
        },
        onError: onErr,
    });

    return (
        <PageContainer>
            <div className="flex justify-end">
                <Button onClick={() => setEditing('baru')}>Tambah Tugasan</Button>
            </div>

            <Table
                head={
                    <>
                        <th>Tugasan</th>
                        <th>Kategori</th>
                        <th>Jadual</th>
                        <th>Larian terakhir</th>
                        <th>Seterusnya</th>
                        <th>Kejayaan 30 hari</th>
                        <th>Aktif</th>
                        <th className="text-right">Tindakan</th>
                    </>
                }
            >
                {isLoading ? (
                    <tr>
                        <td colSpan={8} className="text-center text-txt-black-500">Memuatkan…</td>
                    </tr>
                ) : (
                    data?.data.map((t) => (
                        <tr key={t.id}>
                            <td>
                                <Link href={`/admin/pentadbiran/tugasan-berjadual/${t.id}`} className="font-medium text-primary-600 hover:underline">
                                    {t.nama}
                                </Link>
                                <p className="text-body-xs text-txt-black-500 font-mono">{t.arahan}</p>
                                {t.sumber === 'sistem' && <Badge variant="info">Sistem</Badge>}
                            </td>
                            <td className="whitespace-nowrap">{KATEGORI_TUGASAN[t.kategori] ?? t.kategori}</td>
                            <td className="font-mono text-body-xs whitespace-nowrap">{t.jenis_jadual === 'cron' ? t.ungkapan_cron : `setiap ${t.selang_minit} min`}</td>
                            <td className="whitespace-nowrap">
                                {t.terakhir_status ? <Badge variant={statusVariant(t.terakhir_status)}>{STATUS_LABEL[t.terakhir_status] ?? t.terakhir_status}</Badge> : '—'}
                                <p className="text-body-xs text-txt-black-500">{formatDateTime(t.terakhir_mula_pada)}</p>
                            </td>
                            <td className="whitespace-nowrap text-body-xs">{formatDateTime(t.seterusnya_pada)}</td>
                            <td>{t.kadar_kejayaan_30h === null || t.kadar_kejayaan_30h === undefined ? '—' : `${t.kadar_kejayaan_30h}%`}</td>
                            <td>
                                <Toggle name={`aktif-${t.id}`} checked={t.aktif} onChange={() => toggle.mutate(t)} />
                            </td>
                            <td className="text-right whitespace-nowrap space-x-3">
                                <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => run.mutate(t)}>
                                    Jalankan
                                </button>
                                {t.sumber === 'adm' && (
                                    <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => setEditing(t)}>
                                        Pinda
                                    </button>
                                )}
                            </td>
                        </tr>
                    ))
                )}
            </Table>

            <section className="space-y-3">
                <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Tugasan modul lain dalam penjadual Laravel</h3>
                <p className="text-body-sm text-txt-black-500">Baca sahaja. &quot;Ambil alih&quot; untuk merekod sejarah larian, menghidup/mematikan dan menerima amaran kegagalan.</p>
                <Table
                    head={
                        <>
                            <th>Perihal</th>
                            <th>Arahan</th>
                            <th>Jadual</th>
                            <th>Seterusnya</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {(sistem.data?.data ?? []).length === 0 ? (
                        <tr>
                            <td colSpan={5} className="text-center text-txt-black-500">Tiada tugasan lain didaftarkan.</td>
                        </tr>
                    ) : (
                        sistem.data?.data.map((s) => (
                            <tr key={s.kunci}>
                                <td>{s.perihal}</td>
                                <td className="font-mono text-body-xs">{s.arahan}</td>
                                <td className="font-mono text-body-xs">{s.ungkapan}</td>
                                <td className="text-body-xs whitespace-nowrap">{formatDateTime(s.seterusnya)}</td>
                                <td className="text-right">
                                    {s.id_tugasan ? (
                                        <Badge variant="success">Diambil alih</Badge>
                                    ) : (
                                        <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => adopt.mutate(s)}>
                                            Ambil alih
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </section>

            {editing && data && (
                <TugasanModal tugasan={editing === 'baru' ? null : editing} arahan={data.arahan} kategori={data.kategori} onClose={() => setEditing(null)} />
            )}
        </PageContainer>
    );
}

function TugasanModal({ tugasan, arahan, kategori, onClose }: { tugasan: Tugasan | null; arahan: string[]; kategori: string[]; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [f, setF] = useState({
        kod: tugasan?.kod ?? '',
        nama: tugasan?.nama ?? '',
        kategori: tugasan?.kategori ?? kategori[0] ?? 'pengemaskinian_data',
        arahan: tugasan?.arahan ?? arahan[0] ?? '',
        jenis_jadual: tugasan?.jenis_jadual ?? 'cron',
        ungkapan_cron: tugasan?.ungkapan_cron ?? '0 1 * * *',
        selang_minit: String(tugasan?.selang_minit ?? 60),
        cubaan_semula: String(tugasan?.cubaan_semula ?? 0),
        toleransi_lewat_minit: tugasan?.toleransi_lewat_minit ? String(tugasan.toleransi_lewat_minit) : '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));

    const save = useMutation({
        mutationFn: () => {
            const body = {
                ...(tugasan ? {} : { kod: f.kod }),
                nama: f.nama,
                kategori: f.kategori,
                arahan: f.arahan,
                jenis_jadual: f.jenis_jadual,
                ungkapan_cron: f.jenis_jadual === 'cron' ? f.ungkapan_cron : null,
                selang_minit: f.jenis_jadual === 'selang' ? Number(f.selang_minit) : null,
                cubaan_semula: Number(f.cubaan_semula),
                toleransi_lewat_minit: f.toleransi_lewat_minit ? Number(f.toleransi_lewat_minit) : null,
            };
            return tugasan ? api.put<{ message: string }>(`/adm/tugasan/${tugasan.id}`, body) : api.post<{ message: string }>('/adm/tugasan', body);
        },
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['adm'] });
            onClose();
        },
        onError: (e) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(errorText(e))),
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        save.mutate();
    }

    const select = 'w-full rounded-md border-otl-gray-300 text-body-sm';

    return (
        <Modal open onClose={onClose}>
            <form onSubmit={submit} className="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{tugasan ? 'Pinda Tugasan' : 'Tambah Tugasan'}</h2>
                {!tugasan && <FieldInput label="Kod" name="kod" value={f.kod} onChange={set('kod')} required error={errors.kod?.[0]} help="Huruf kecil, nombor, garis bawah." />}
                <FieldInput label="Nama" name="nama" value={f.nama} onChange={set('nama')} required error={errors.nama?.[0]} />
                <div>
                    <InputLabel htmlFor="kategori" className="mb-1.5">
                        Kategori
                    </InputLabel>
                    <select id="kategori" className={select} value={f.kategori} onChange={(e) => set('kategori')(e.target.value)}>
                        {kategori.map((k) => (
                            <option key={k} value={k}>
                                {KATEGORI_TUGASAN[k] ?? k}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="arahan" className="mb-1.5">
                        Arahan
                    </InputLabel>
                    <select id="arahan" className={select} value={f.arahan} onChange={(e) => set('arahan')(e.target.value)}>
                        {arahan.map((a) => (
                            <option key={a} value={a}>
                                {a}
                            </option>
                        ))}
                    </select>
                    {errors.arahan && <p className="mt-1 text-body-xs text-danger-600">{errors.arahan[0]}</p>}
                </div>
                <div>
                    <InputLabel htmlFor="jenis_jadual" className="mb-1.5">
                        Jenis jadual
                    </InputLabel>
                    <select id="jenis_jadual" className={select} value={f.jenis_jadual} onChange={(e) => set('jenis_jadual')(e.target.value)}>
                        <option value="cron">Ungkapan cron</option>
                        <option value="selang">Selang masa tetap</option>
                    </select>
                </div>
                {f.jenis_jadual === 'cron' ? (
                    <FieldInput label="Ungkapan cron" name="ungkapan_cron" value={f.ungkapan_cron} onChange={set('ungkapan_cron')} error={errors.ungkapan_cron?.[0]} help="cth. 30 2 * * * = setiap hari 2:30 pagi" />
                ) : (
                    <FieldInput label="Selang (minit)" name="selang_minit" type="number" value={f.selang_minit} onChange={set('selang_minit')} error={errors.selang_minit?.[0]} />
                )}
                <div className="grid grid-cols-2 gap-3">
                    <FieldInput label="Cubaan semula" name="cubaan_semula" type="number" value={f.cubaan_semula} onChange={set('cubaan_semula')} error={errors.cubaan_semula?.[0]} />
                    <FieldInput label="Toleransi lewat (minit)" name="toleransi" type="number" value={f.toleransi_lewat_minit} onChange={set('toleransi_lewat_minit')} />
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={save.isPending}>
                        Simpan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
