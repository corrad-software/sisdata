'use client';

import { Fragment, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { kbaApi, tarikhMasa } from '@/lib/kba';
import { Card, Field, inputClass, KlasifikasiBadge, Kosong, Memuat, RalatAkses, Select } from '../_components/common';

const STATUS = [
    { value: 'berjaya', label: 'Berjaya' },
    { value: 'tiada_sumber', label: 'Tiada sumber' },
    { value: 'ditolak', label: 'Ditolak (had kadar)' },
];

function Butiran({ id }: { id: number }) {
    const { data, error } = useQuery({ queryKey: ['kba', 'log-prompt', id], queryFn: () => kbaApi.logPromptSatu(id).then((r) => r.data) });
    if (error) return <RalatAkses error={error} />;
    if (!data) return <Memuat />;
    return (
        <div className="space-y-2 rounded-md bg-bg-washed p-3 text-body-sm">
            <p className="whitespace-pre-wrap break-words">
                <strong>Jawapan:</strong> {data.jawapan ?? '—'}
            </p>
            <p>
                <strong>Model:</strong> {data.model_digunakan ?? '—'} · embedding {data.model_embedding ?? '—'} · {data.bilangan_token} token · {data.masa_respons_ms} ms
                {data.sebab ? ` · ${data.sebab}` : ''}
            </p>
            <div>
                <strong>Segmen dimasukkan ke dalam konteks:</strong>
                {data.segmen_dicapai.length === 0 ? (
                    <span> tiada</span>
                ) : (
                    <ul className="mt-1 space-y-1">
                        {data.segmen_dicapai.map((s) => (
                            <li key={s.id} className="flex flex-wrap items-center gap-2">
                                <Link href={`/admin/kba/sumber/${s.id_sumber}?segmen=${s.id}#segmen-${s.id}`} className="text-txt-primary hover:underline break-words">
                                    #{s.id} {s.dokumen} {s.nombor_seksyen ? `§${s.nombor_seksyen}` : ''}
                                </Link>
                                <KlasifikasiBadge k={s.klasifikasi} />
                                {s.status !== 'aktif' && <span className="text-body-xs text-txt-black-500">(versi lama)</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

/** SRS-KBA-16: prompt / answer / sources audit for ICT security and internal audit (read-only; viewing is audited). */
export default function LogPromptPage() {
    const [f, setF] = useState({ q: '', status: '', dari: '', hingga: '', id_pengguna: '' });
    const [halaman, setHalaman] = useState(1);
    const [buka, setBuka] = useState<number | null>(null);
    const { data, error, isLoading } = useQuery({ queryKey: ['kba', 'log-prompt', f, halaman], queryFn: () => kbaApi.logPrompt({ ...f, halaman }) });
    const set = (k: keyof typeof f) => (v: string) => {
        setHalaman(1);
        setF((x) => ({ ...x, [k]: v }));
    };

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }
    return (
        <PageContainer>
            <Card title="Penapis">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Field label="Teks prompt" htmlFor="lp-q">
                        <input id="lp-q" className={inputClass} value={f.q} onChange={(e) => set('q')(e.target.value)} />
                    </Field>
                    <Field label="Status" htmlFor="lp-st">
                        <Select id="lp-st" value={f.status} onChange={set('status')} options={STATUS} empty="Semua" />
                    </Field>
                    <Field label="ID pengguna" htmlFor="lp-u">
                        <input id="lp-u" inputMode="numeric" className={inputClass} value={f.id_pengguna} onChange={(e) => set('id_pengguna')(e.target.value.replace(/\D/g, ''))} />
                    </Field>
                    <Field label="Dari" htmlFor="lp-dari">
                        <input id="lp-dari" type="date" className={inputClass} value={f.dari} onChange={(e) => set('dari')(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="lp-hingga">
                        <input id="lp-hingga" type="date" className={inputClass} value={f.hingga} onChange={(e) => set('hingga')(e.target.value)} />
                    </Field>
                </div>
            </Card>
            <Card title={data ? `${data.meta.jumlah} rekod prompt` : 'Rekod prompt'}>
                {isLoading || !data ? (
                    <Memuat />
                ) : data.data.length === 0 ? (
                    <Kosong>Tiada rekod.</Kosong>
                ) : (
                    <>
                        <Table
                            head={
                                <>
                                    <th>Masa</th>
                                    <th>Pengguna / peranan</th>
                                    <th>Prompt</th>
                                    <th>Status</th>
                                    <th>Sumber dipetik</th>
                                    <th />
                                </>
                            }
                        >
                            {data.data.map((l) => (
                                <Fragment key={l.id}>
                                    <tr>
                                        <td className="whitespace-nowrap text-body-xs">{tarikhMasa(l.cap_masa)}</td>
                                        <td className="min-w-[10rem] text-body-xs">
                                            {l.nama_pengguna ?? (l.id_pengguna ? `#${l.id_pengguna}` : 'Awam')}
                                            <div className="text-txt-black-500 break-all">{l.peranan.join(', ') || '—'}</div>
                                            <div className="text-txt-black-500">
                                                {l.alamat_ip ?? '—'} · {l.saluran}
                                            </div>
                                        </td>
                                        <td className="min-w-[14rem] break-words">{l.prompt}</td>
                                        <td>
                                            <Badge variant={l.status === 'berjaya' ? 'success' : l.status === 'ditolak' ? 'danger' : 'warning'}>{STATUS.find((s) => s.value === l.status)?.label ?? l.status}</Badge>
                                            <div className="text-body-xs text-txt-black-500">{l.mod_jawapan ?? ''}</div>
                                        </td>
                                        <td className="min-w-[12rem] text-body-xs break-words">{l.senarai_sumber_dipetik.map((s) => `[${s.no}] ${s.dokumen} ${s.seksyen ?? ''}`).join('; ') || '—'}</td>
                                        <td>
                                            <Button size="sm" variant="secondary" onClick={() => setBuka(buka === l.id ? null : l.id)}>
                                                {buka === l.id ? 'Tutup' : 'Butiran'}
                                            </Button>
                                        </td>
                                    </tr>
                                    {buka === l.id && (
                                        <tr>
                                            <td colSpan={6}>
                                                <Butiran id={l.id} />
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </Table>
                        <div className="flex items-center justify-between text-body-sm">
                            <Button size="sm" variant="secondary" disabled={halaman <= 1} onClick={() => setHalaman((h) => h - 1)}>
                                Sebelum
                            </Button>
                            <span>
                                Halaman {data.meta.halaman} / {data.meta.halaman_akhir}
                            </span>
                            <Button size="sm" variant="secondary" disabled={halaman >= data.meta.halaman_akhir} onClick={() => setHalaman((h) => h + 1)}>
                                Seterusnya
                            </Button>
                        </div>
                    </>
                )}
            </Card>
        </PageContainer>
    );
}
