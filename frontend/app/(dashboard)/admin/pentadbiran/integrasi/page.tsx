'use client';

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
import { Alert } from '@/components/ui/Alert';
import { api, ApiError } from '@/lib/api';
import { errorText, formatDateTime, statusVariant, type Integrasi, type Rahsia } from '@/lib/adm';

export default function IntegrasiPage() {
    const queryClient = useQueryClient();
    const list = useQuery({ queryKey: ['adm', 'integrasi'], queryFn: () => api.get<{ data: Integrasi[] }>('/adm/integrasi') });
    const secrets = useQuery({ queryKey: ['adm', 'rahsia'], queryFn: () => api.get<{ data: Rahsia[] }>('/adm/rahsia'), retry: false });
    const [editing, setEditing] = useState<Integrasi | 'baru' | null>(null);
    const [secretKey, setSecretKey] = useState<string | null>(null);

    const test = useMutation({
        mutationFn: (i: Integrasi) => api.post<{ data: { berjaya: boolean; status_http: number | null; tempoh_ms: number; mesej: string | null } }>(`/adm/integrasi/${i.id}/uji`, {}),
        onSuccess: (r) => {
            const d = r.data;
            if (d.berjaya) toast.success(`Sambungan berjaya (${d.status_http ?? '—'}, ${d.tempoh_ms} ms)`);
            else toast.error(`Sambungan gagal: ${d.mesej ?? d.status_http}`);
            queryClient.invalidateQueries({ queryKey: ['adm', 'integrasi'] });
        },
        onError: (e) => toast.error(errorText(e)),
    });

    return (
        <PageContainer>
            <Alert type="info">Semua perubahan tetapan integrasi dan rahsia memerlukan kelulusan pentadbir kedua sebelum berkuat kuasa.</Alert>

            <div className="flex justify-end">
                <Button onClick={() => setEditing('baru')}>Tambah Integrasi</Button>
            </div>

            <Table
                head={
                    <>
                        <th>Integrasi</th>
                        <th>Endpoint</th>
                        <th>Tamat masa</th>
                        <th>Kadar / min</th>
                        <th>Jadual</th>
                        <th>Rahsia</th>
                        <th>Status</th>
                        <th className="text-right">Tindakan</th>
                    </>
                }
            >
                {(list.data?.data ?? []).length === 0 ? (
                    <tr>
                        <td colSpan={8} className="text-center text-txt-black-500">{list.isLoading ? 'Memuatkan…' : 'Tiada integrasi.'}</td>
                    </tr>
                ) : (
                    list.data?.data.map((i) => (
                        <tr key={i.id}>
                            <td>
                                <p className="font-medium">{i.nama}</p>
                                <p className="text-body-xs text-txt-black-500 font-mono">
                                    {i.kod} · {i.jenis}
                                </p>
                            </td>
                            <td className="max-w-xs truncate font-mono text-body-xs">{i.endpoint}</td>
                            <td>{i.tempoh_tamat_masa} s</td>
                            <td>{i.kadar_capaian ?? '—'}</td>
                            <td className="font-mono text-body-xs">{i.jadual ?? '—'}</td>
                            <td>
                                {i.id_rahsia ? (
                                    <Badge variant={i.rahsia_wujud ? 'success' : 'warning'}>{i.rahsia_wujud ? 'Ditetapkan' : 'Belum ditetapkan'}</Badge>
                                ) : (
                                    '—'
                                )}
                            </td>
                            <td>
                                <Badge variant={statusVariant(i.status)}>{i.status === 'aktif' ? 'Aktif' : 'Tidak aktif'}</Badge>
                                {i.kegagalan_berturut > 0 && <p className="text-body-xs text-danger-600">{i.kegagalan_berturut} gagal berturut</p>}
                            </td>
                            <td className="text-right whitespace-nowrap space-x-3">
                                <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => test.mutate(i)}>
                                    Uji
                                </button>
                                <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => setEditing(i)}>
                                    Pinda
                                </button>
                                {i.id_rahsia && (
                                    <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => setSecretKey(i.id_rahsia)}>
                                        Rahsia
                                    </button>
                                )}
                            </td>
                        </tr>
                    ))
                )}
            </Table>

            <section className="space-y-3" aria-labelledby="rahsia-tajuk">
                <div className="flex items-center justify-between">
                    <h3 id="rahsia-tajuk" className="font-heading font-semibold text-body-lg text-txt-black-900">
                        Stor rahsia
                    </h3>
                    <Button size="sm" variant="secondary" onClick={() => setSecretKey('')}>
                        Tetapkan rahsia
                    </Button>
                </div>
                <p className="text-body-sm text-txt-black-500">
                    Nilai disulitkan dan tidak dipaparkan semula. Dari konsol pelayan: <code>php artisan adm:secret &lt;kunci&gt;</code>.
                </p>
                <Table
                    head={
                        <>
                            <th>Kunci</th>
                            <th>Keterangan</th>
                            <th>Dikemas kini</th>
                        </>
                    }
                >
                    {secrets.error ? (
                        <tr>
                            <td colSpan={3} className="text-center text-txt-black-500">{errorText(secrets.error)}</td>
                        </tr>
                    ) : (secrets.data?.data ?? []).length === 0 ? (
                        <tr>
                            <td colSpan={3} className="text-center text-txt-black-500">Tiada rahsia.</td>
                        </tr>
                    ) : (
                        secrets.data?.data.map((r) => (
                            <tr key={r.id}>
                                <td className="font-mono text-body-xs">{r.kunci}</td>
                                <td>{r.keterangan ?? '—'}</td>
                                <td>{formatDateTime(r.updated_at)}</td>
                            </tr>
                        ))
                    )}
                </Table>
            </section>

            {editing && <IntegrasiModal integrasi={editing === 'baru' ? null : editing} onClose={() => setEditing(null)} />}
            {secretKey !== null && <SecretModal initialKey={secretKey} onClose={() => setSecretKey(null)} />}
        </PageContainer>
    );
}

function IntegrasiModal({ integrasi, onClose }: { integrasi: Integrasi | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [f, setF] = useState({
        kod: integrasi?.kod ?? '',
        nama: integrasi?.nama ?? '',
        jenis: integrasi?.jenis ?? 'rest',
        endpoint: integrasi?.endpoint ?? 'https://',
        id_rahsia: integrasi?.id_rahsia ?? '',
        tempoh_tamat_masa: String(integrasi?.tempoh_tamat_masa ?? 30),
        kadar_capaian: integrasi?.kadar_capaian ? String(integrasi.kadar_capaian) : '',
        jadual: integrasi?.jadual ?? '',
        status: integrasi?.status ?? 'aktif',
        justifikasi: '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));

    const save = useMutation({
        mutationFn: () => {
            const body = {
                ...(integrasi ? {} : { kod: f.kod }),
                nama: f.nama,
                jenis: f.jenis,
                endpoint: f.endpoint,
                id_rahsia: f.id_rahsia || null,
                tempoh_tamat_masa: Number(f.tempoh_tamat_masa),
                kadar_capaian: f.kadar_capaian ? Number(f.kadar_capaian) : null,
                jadual: f.jadual || null,
                status: f.status,
                justifikasi: f.justifikasi,
            };
            return integrasi ? api.put<{ message: string }>(`/adm/integrasi/${integrasi.id}`, body) : api.post<{ message: string }>('/adm/integrasi', body);
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
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{integrasi ? `Pinda ${integrasi.nama}` : 'Tambah Integrasi'}</h2>
                {!integrasi && <FieldInput label="Kod" name="kod" value={f.kod} onChange={set('kod')} required error={errors.kod?.[0]} />}
                <FieldInput label="Nama" name="nama" value={f.nama} onChange={set('nama')} required error={errors.nama?.[0]} />
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="jenis" className="mb-1.5">
                            Jenis
                        </InputLabel>
                        <select id="jenis" className={select} value={f.jenis} onChange={(e) => set('jenis')(e.target.value)}>
                            {['rest', 'soap', 'sftp', 'fail', 'ogc'].map((j) => (
                                <option key={j} value={j}>
                                    {j.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="status" className="mb-1.5">
                            Status
                        </InputLabel>
                        <select id="status" className={select} value={f.status} onChange={(e) => set('status')(e.target.value)}>
                            <option value="aktif">Aktif</option>
                            <option value="tidak_aktif">Tidak aktif</option>
                        </select>
                    </div>
                </div>
                <FieldInput label="Endpoint" name="endpoint" value={f.endpoint} onChange={set('endpoint')} required error={errors.endpoint?.[0]} />
                <FieldInput label="Rujukan rahsia (kunci)" name="id_rahsia" value={f.id_rahsia} onChange={set('id_rahsia')} error={errors.id_rahsia?.[0]} help="cth. integrasi.jupem.token — nilai ditetapkan dalam stor rahsia." />
                <div className="grid grid-cols-2 gap-3">
                    <FieldInput label="Tamat masa (saat)" name="tempoh" type="number" value={f.tempoh_tamat_masa} onChange={set('tempoh_tamat_masa')} error={errors.tempoh_tamat_masa?.[0]} />
                    <FieldInput label="Kadar capaian / minit" name="kadar" type="number" value={f.kadar_capaian} onChange={set('kadar_capaian')} error={errors.kadar_capaian?.[0]} />
                </div>
                <FieldInput label="Jadual pertukaran data (cron)" name="jadual" value={f.jadual} onChange={set('jadual')} error={errors.jadual?.[0]} />
                <FieldInput label="Justifikasi" name="justifikasi" value={f.justifikasi} onChange={set('justifikasi')} required error={errors.justifikasi?.[0]} />
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={save.isPending}>
                        Hantar untuk kelulusan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function SecretModal({ initialKey, onClose }: { initialKey: string; onClose: () => void }) {
    const [kunci, setKunci] = useState(initialKey);
    const [nilai, setNilai] = useState('');
    const [keterangan, setKeterangan] = useState('');
    const [justifikasi, setJustifikasi] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const save = useMutation({
        mutationFn: () => api.put<{ message: string }>(`/adm/rahsia/${encodeURIComponent(kunci)}`, { nilai, keterangan: keterangan || null, justifikasi }),
        onSuccess: (r) => {
            toast.success(r.message);
            onClose();
        },
        onError: (e) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(errorText(e))),
    });

    return (
        <Modal open onClose={onClose}>
            <form
                className="p-6 space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tetapkan rahsia</h2>
                <FieldInput label="Kunci" name="kunci" value={kunci} onChange={setKunci} required />
                <FieldInput label="Nilai" name="nilai" type="password" value={nilai} onChange={setNilai} required autoComplete="new-password" error={errors.nilai?.[0]} />
                <FieldInput label="Keterangan" name="keterangan" value={keterangan} onChange={setKeterangan} />
                <FieldInput label="Justifikasi" name="justifikasi" value={justifikasi} onChange={setJustifikasi} required error={errors.justifikasi?.[0]} />
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={save.isPending || !kunci}>
                        Hantar untuk kelulusan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
