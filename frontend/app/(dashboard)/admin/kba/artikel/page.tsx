'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { kbaApi, KLASIFIKASI_LABEL, STATUS_ARTIKEL_LABEL, tarikhMasa, useCan, type Artikel, type Klasifikasi } from '@/lib/kba';
import { Card, Field, inputClass, KlasifikasiBadge, Kosong, Memuat, ralat, RalatAkses, Select } from '../_components/common';

const STATUS_VARIANT: Record<Artikel['status'], 'neutral' | 'warning' | 'success' | 'danger'> = { draf: 'neutral', dalam_semakan: 'warning', diterbitkan: 'success', lapuk: 'danger' };

function ArtikelBaharu() {
    const router = useRouter();
    const [tajuk, setTajuk] = useState('');
    const [kandungan, setKandungan] = useState('');
    const [modul, setModul] = useState('');
    const [kategori, setKategori] = useState('');
    const [klas, setKlas] = useState<Klasifikasi>('dalaman');
    const [versi, setVersi] = useState('');
    const [err, setErr] = useState<string | null>(null);
    const m = useMutation({
        mutationFn: () => kbaApi.ciptaArtikel({ tajuk, kandungan, modul: modul || null, kategori: kategori || null, klasifikasi: klas, versi_sistem_dirujuk: versi || null }),
        onSuccess: (r) => router.push(`/admin/kba/artikel/${r.data.id}`),
        onError: (e) => setErr(ralat(e)),
    });
    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                m.mutate();
            }}
            className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Field label="Tajuk" htmlFor="a-tajuk" className="sm:col-span-2">
                <input id="a-tajuk" className={inputClass} value={tajuk} onChange={(e) => setTajuk(e.target.value)} required maxLength={250} />
            </Field>
            <Field label="Kategori" htmlFor="a-kat">
                <input id="a-kat" className={inputClass} value={kategori} onChange={(e) => setKategori(e.target.value)} maxLength={60} />
            </Field>
            <Field label="Modul" htmlFor="a-modul">
                <input id="a-modul" className={inputClass} value={modul} onChange={(e) => setModul(e.target.value)} maxLength={3} placeholder="cth. pjk" />
            </Field>
            <Field label="Klasifikasi" htmlFor="a-klas">
                <Select id="a-klas" value={klas} onChange={(v) => setKlas(v as Klasifikasi)} options={(Object.keys(KLASIFIKASI_LABEL) as Klasifikasi[]).map((k) => ({ value: k, label: KLASIFIKASI_LABEL[k] }))} />
            </Field>
            <Field label="Versi sistem dirujuk" htmlFor="a-versi" help="Artikel ditanda perlu semakan apabila keluaran lebih baharu direkod.">
                <input id="a-versi" className={inputClass} value={versi} onChange={(e) => setVersi(e.target.value)} placeholder="cth. 1.2" />
            </Field>
            <Field label="Kandungan (Markdown)" htmlFor="a-isi" className="sm:col-span-2 lg:col-span-4">
                <textarea id="a-isi" rows={8} className={inputClass} value={kandungan} onChange={(e) => setKandungan(e.target.value)} required />
            </Field>
            <div className="sm:col-span-2 lg:col-span-4 flex gap-2">
                <Button type="submit" disabled={m.isPending}>
                    Simpan draf
                </Button>
            </div>
            {err && (
                <div className="sm:col-span-2 lg:col-span-4">
                    <Alert type="error">{err}</Alert>
                </div>
            )}
        </form>
    );
}

function Keluaran() {
    const qc = useQueryClient();
    const [versi, setVersi] = useState('');
    const [catatan, setCatatan] = useState('');
    const [msg, setMsg] = useState<string | null>(null);
    const { data } = useQuery({ queryKey: ['kba', 'keluaran'], queryFn: () => kbaApi.keluaran().then((r) => r.data) });
    const m = useMutation({
        mutationFn: () => kbaApi.simpanKeluaran(versi, catatan),
        onSuccess: (r) => {
            setMsg(r.message);
            setVersi('');
            qc.invalidateQueries({ queryKey: ['kba', 'keluaran'] });
            qc.invalidateQueries({ queryKey: ['kba', 'artikel'] });
        },
        onError: (e) => setMsg(ralat(e)),
    });
    return (
        <div className="space-y-3">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    m.mutate();
                }}
                className="grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <Field label="Versi keluaran" htmlFor="k-versi">
                    <input id="k-versi" className={inputClass} value={versi} onChange={(e) => setVersi(e.target.value)} required placeholder="cth. 2.0" />
                </Field>
                <Field label="Catatan" htmlFor="k-catatan">
                    <input id="k-catatan" className={inputClass} value={catatan} onChange={(e) => setCatatan(e.target.value)} maxLength={1000} />
                </Field>
                <div className="flex items-end">
                    <Button type="submit" disabled={m.isPending}>
                        Rekod keluaran
                    </Button>
                </div>
            </form>
            {msg && <p className="text-body-sm text-txt-black-700">{msg}</p>}
            {(data ?? []).length > 0 && (
                <p className="text-body-xs text-txt-black-500">
                    Keluaran terdahulu: {(data ?? []).map((k) => `${k.versi} (${k.bilangan_artikel_ditanda} ditanda)`).join(', ')}
                </p>
            )}
        </div>
    );
}

/** SRS-KBA-11..14: articles (published for readers; drafts, review queue and action list for content admins). */
export default function ArtikelPage() {
    const can = useCan();
    const editor = can('kba.artikel.kemas_kini');
    const search = useSearchParams();
    const tindakan = search.get('tindakan') === '1';
    const [status, setStatus] = useState('');
    const [q, setQ] = useState('');
    const { data, error, isLoading } = useQuery({
        queryKey: ['kba', 'artikel', status, q, tindakan],
        queryFn: () => kbaApi.artikel({ status, q, tindakan }).then((r) => r.data),
    });

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            <Card
                title={tindakan ? 'Senarai tindakan pentadbir kandungan' : 'Artikel Pangkalan Pengetahuan'}
                actions={
                    <>
                        <input aria-label="Cari tajuk" className={`${inputClass} w-48`} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari tajuk…" />
                        {editor && (
                            <>
                                <div className="w-44">
                                    <Select id="f-status" value={status} onChange={setStatus} empty="Semua status" options={Object.entries(STATUS_ARTIKEL_LABEL).map(([value, label]) => ({ value, label }))} />
                                </div>
                                <Link href={tindakan ? '/admin/kba/artikel' : '/admin/kba/artikel?tindakan=1'} className="self-center text-body-sm font-medium text-txt-primary hover:underline">
                                    {tindakan ? 'Semua artikel' : 'Perlu tindakan'}
                                </Link>
                            </>
                        )}
                    </>
                }
            >
                {isLoading || !data ? (
                    <Memuat />
                ) : data.length === 0 ? (
                    <Kosong>Tiada artikel.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>Tajuk</th>
                                <th>Status</th>
                                <th>Versi</th>
                                <th>Klasifikasi</th>
                                <th>Semakan seterusnya</th>
                                <th>Dikemas kini</th>
                            </>
                        }
                    >
                        {data.map((a) => (
                            <tr key={a.id}>
                                <td className="min-w-[14rem]">
                                    <Link href={`/admin/kba/artikel/${a.id}`} className="font-medium text-txt-primary hover:underline break-words">
                                        {a.tajuk}
                                    </Link>
                                    <div className="text-body-xs text-txt-black-500">
                                        {a.modul ? a.modul.toUpperCase() : '—'}
                                        {a.sumber_asal === 'tiket' && a.id_tiket_asal ? ` · daripada tiket ${a.id_tiket_asal}` : ''}
                                    </div>
                                    {a.perlu_semakan && <div className="text-body-xs text-txt-warning">{a.sebab_perlu_semakan ?? 'Perlu semakan'}</div>}
                                </td>
                                <td>
                                    <Badge variant={STATUS_VARIANT[a.status]}>{STATUS_ARTIKEL_LABEL[a.status]}</Badge>
                                </td>
                                <td>v{a.versi}</td>
                                <td>
                                    <KlasifikasiBadge k={a.klasifikasi} />
                                </td>
                                <td>{a.tarikh_semakan_seterusnya ?? '—'}</td>
                                <td className="whitespace-nowrap text-body-xs">{tarikhMasa(a.updated_at)}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            {can('kba.artikel.cipta') && (
                <Card title="Artikel baharu">
                    <ArtikelBaharu />
                </Card>
            )}
            {can('kba.keluaran.kemas_kini') && (
                <Card title="Keluaran versi sistem (tanda artikel lapuk)">
                    <Keluaran />
                </Card>
            )}
        </PageContainer>
    );
}
