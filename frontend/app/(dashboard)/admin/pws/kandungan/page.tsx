'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useMutation, useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { pwsApi, ralat, STATUS_KANDUNGAN_LABEL, tarikhMasa, useCan } from '@/lib/pws';
import { AiBadge, Card, Field, inputClass, Kosong, Memuat, Select, StatusKandunganBadge } from '../_components/common';

/** SRS-PWS-04: draft with the LOCAL model (Ollama via SISDATA) — always a draft, published only after WFE approval. */
function DrafAi({ jenis }: { jenis: { kod: string; nama: string }[] }) {
    const router = useRouter();
    const [kod, setKod] = useState('hebahan');
    const [arahan, setArahan] = useState('');
    const [bahasa, setBahasa] = useState<'ms' | 'en'>('ms');
    const [err, setErr] = useState<string | null>(null);
    const ai = useQuery({ queryKey: ['pws', 'status-ai'], queryFn: () => pwsApi.statusAi().then((r) => r.data) });
    const m = useMutation({
        mutationFn: () => pwsApi.janaAi({ jenis: kod, arahan, bahasa }),
        onSuccess: (r) => router.push(`/admin/pws/kandungan/${r.data.id}`),
        onError: (e) => setErr(ralat(e)),
    });
    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                setErr(null);
                m.mutate();
            }}
            className="grid grid-cols-1 gap-4 sm:grid-cols-4"
        >
            <div className="sm:col-span-4">
                {ai.data && !ai.data.tersedia ? (
                    <Alert type="warning">Model AI tempatan tidak tersedia buat masa ini. Tulis draf secara manual.</Alert>
                ) : (
                    <p className="text-body-sm text-txt-black-700">
                        Draf dijana oleh model tempatan SISDATA {ai.data?.model ? <code>{ai.data.model}</code> : null} — tiada perkhidmatan AI awam digunakan. Setiap draf mesti disemak, dilengkapkan
                        dan diluluskan oleh pegawai kedua sebelum dipaparkan di portal.
                    </p>
                )}
            </div>
            <Field label="Jenis kandungan" htmlFor="ai-jenis">
                <Select id="ai-jenis" value={kod} onChange={setKod} options={jenis.map((j) => ({ value: j.kod, label: j.nama }))} />
            </Field>
            <Field label="Bahasa" htmlFor="ai-bahasa">
                <Select id="ai-bahasa" value={bahasa} onChange={(v) => setBahasa(v as 'ms' | 'en')} options={[{ value: 'ms', label: 'Bahasa Melayu' }, { value: 'en', label: 'Bahasa Inggeris' }]} />
            </Field>
            <Field label="Arahan (prompt)" htmlFor="ai-arahan" className="sm:col-span-4" help="Jangan masukkan data peribadi. Fakta yang tidak diberi akan ditanda [ISI: …] untuk dilengkapkan.">
                <textarea id="ai-arahan" rows={4} className={inputClass} value={arahan} onChange={(e) => setArahan(e.target.value)} required minLength={10} maxLength={2000} />
            </Field>
            <div className="flex gap-2 sm:col-span-4">
                <Button type="submit" disabled={m.isPending || (ai.data !== undefined && !ai.data.tersedia)}>
                    {m.isPending ? 'Menjana draf…' : 'Jana draf'}
                </Button>
            </div>
            {err && (
                <div className="sm:col-span-4">
                    <Alert type="error">{err}</Alert>
                </div>
            )}
        </form>
    );
}

function KandunganBaharu({ jenis }: { jenis: { kod: string; nama: string }[] }) {
    const router = useRouter();
    const [kod, setKod] = useState('artikel');
    const [tajuk, setTajuk] = useState('');
    const [isi, setIsi] = useState('');
    const [err, setErr] = useState<string | null>(null);
    const m = useMutation({
        mutationFn: () => pwsApi.ciptaKandungan({ jenis: kod, tajuk_bm: tajuk, kandungan_bm: isi }),
        onSuccess: (r) => router.push(`/admin/pws/kandungan/${r.data.id}`),
        onError: (e) => setErr(ralat(e)),
    });
    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                setErr(null);
                m.mutate();
            }}
            className="grid grid-cols-1 gap-4 sm:grid-cols-4"
        >
            <Field label="Jenis kandungan" htmlFor="k-jenis">
                <Select id="k-jenis" value={kod} onChange={setKod} options={jenis.map((j) => ({ value: j.kod, label: j.nama }))} />
            </Field>
            <Field label="Tajuk (BM)" htmlFor="k-tajuk" className="sm:col-span-3">
                <input id="k-tajuk" className={inputClass} value={tajuk} onChange={(e) => setTajuk(e.target.value)} required maxLength={250} />
            </Field>
            <Field label="Kandungan (BM)" htmlFor="k-isi" className="sm:col-span-4">
                <textarea id="k-isi" rows={5} className={inputClass} value={isi} onChange={(e) => setIsi(e.target.value)} required />
            </Field>
            <div className="sm:col-span-4">
                <Button type="submit" variant="secondary" disabled={m.isPending}>
                    Simpan draf
                </Button>
            </div>
            {err && (
                <div className="sm:col-span-4">
                    <Alert type="error">{err}</Alert>
                </div>
            )}
        </form>
    );
}

export default function PwsKandunganPage() {
    const can = useCan();
    const [jenis, setJenis] = useState('');
    const [status, setStatus] = useState('');
    const { data, error, isLoading } = useQuery({ queryKey: ['pws', 'kandungan', jenis, status], queryFn: () => pwsApi.kandungan({ jenis, status }) });

    if (error) {
        return (
            <PageContainer>
                <Alert type="error">{ralat(error)}</Alert>
            </PageContainer>
        );
    }
    const types = data?.jenis ?? [];

    return (
        <PageContainer>
            <Card
                title="Kandungan portal"
                actions={
                    <>
                        <div className="w-44">
                            <Select id="f-jenis" value={jenis} onChange={setJenis} empty="Semua jenis" options={types.map((t) => ({ value: t.kod, label: t.nama }))} />
                        </div>
                        <div className="w-44">
                            <Select id="f-status" value={status} onChange={setStatus} empty="Semua status" options={Object.entries(STATUS_KANDUNGAN_LABEL).map(([value, label]) => ({ value, label }))} />
                        </div>
                    </>
                }
            >
                {isLoading || !data ? (
                    <Memuat />
                ) : data.data.length === 0 ? (
                    <Kosong>Tiada kandungan.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>Tajuk</th>
                                <th>Jenis</th>
                                <th>Status</th>
                                <th>Diterbitkan</th>
                                <th>Dikemas kini</th>
                            </>
                        }
                    >
                        {data.data.map((k) => (
                            <tr key={k.id}>
                                <td className="min-w-[14rem]">
                                    <Link href={`/admin/pws/kandungan/${k.id}`} className="break-words font-medium text-txt-primary hover:underline">
                                        {k.tajuk_bm}
                                    </Link>
                                    {k.dijana_ai && (
                                        <div className="mt-1">
                                            <AiBadge />
                                        </div>
                                    )}
                                </td>
                                <td>{k.jenis_nama}</td>
                                <td>
                                    <StatusKandunganBadge s={k.status} />
                                </td>
                                <td className="whitespace-nowrap text-body-xs">{tarikhMasa(k.tarikh_terbit)}</td>
                                <td className="whitespace-nowrap text-body-xs">{tarikhMasa(k.dikemas_kini)}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            {can('pws.kandungan.cipta') && types.length > 0 && (
                <>
                    <Card title="Draf berbantu AI (model tempatan)">
                        <DrafAi jenis={types} />
                    </Card>
                    <Card title="Kandungan baharu (manual)">
                        <KandunganBaharu jenis={types} />
                    </Card>
                </>
            )}
        </PageContainer>
    );
}
