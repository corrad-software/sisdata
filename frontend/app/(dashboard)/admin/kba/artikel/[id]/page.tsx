'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { kbaApi, STATUS_ARTIKEL_LABEL, tarikhMasa, useCan, type Artikel } from '@/lib/kba';
import { Card, Field, inputClass, KlasifikasiBadge, Memuat, ralat, RalatAkses } from '../../_components/common';

function Sunting({ a, onDone }: { a: Artikel; onDone: (m: string) => void }) {
    const [tajuk, setTajuk] = useState(a.tajuk);
    const [kandungan, setKandungan] = useState(a.kandungan ?? '');
    const [versi, setVersi] = useState(a.versi_sistem_dirujuk ?? '');
    const [sebab, setSebab] = useState('');
    const [err, setErr] = useState<string | null>(null);
    const m = useMutation({
        mutationFn: () => kbaApi.kemasKiniArtikel(a.id, { tajuk, kandungan, versi_sistem_dirujuk: versi || null, sebab_perubahan: sebab || undefined }),
        onSuccess: (r) => onDone(r.message),
        onError: (e) => setErr(ralat(e)),
    });
    return (
        <div className="space-y-4">
            <Field label="Tajuk" htmlFor="e-tajuk">
                <input id="e-tajuk" className={inputClass} value={tajuk} onChange={(e) => setTajuk(e.target.value)} maxLength={250} />
            </Field>
            <Field label="Kandungan (Markdown)" htmlFor="e-isi">
                <textarea id="e-isi" rows={12} className={inputClass} value={kandungan} onChange={(e) => setKandungan(e.target.value)} />
            </Field>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label="Versi sistem dirujuk" htmlFor="e-versi">
                    <input id="e-versi" className={inputClass} value={versi} onChange={(e) => setVersi(e.target.value)} />
                </Field>
                <Field label="Sebab perubahan" htmlFor="e-sebab">
                    <input id="e-sebab" className={inputClass} value={sebab} onChange={(e) => setSebab(e.target.value)} maxLength={500} />
                </Field>
            </div>
            {a.status === 'diterbitkan' && <Alert type="info">Suntingan menghasilkan versi draf baharu. Pengguna terus melihat versi diterbitkan sehingga versi baharu diluluskan.</Alert>}
            {err && <Alert type="error">{err}</Alert>}
            <Button onClick={() => m.mutate()} disabled={m.isPending}>
                Simpan versi baharu
            </Button>
        </div>
    );
}

/** SRS-KBA-11/13/14: article detail, versions, review submission (MOD-WFE) and link to the originating ticket. */
export default function ArtikelSatuPage() {
    const { id } = useParams<{ id: string }>();
    const can = useCan();
    const editor = can('kba.artikel.kemas_kini');
    const qc = useQueryClient();
    const [msg, setMsg] = useState<string | null>(null);
    const [sunting, setSunting] = useState(false);
    const { data: a, error, isLoading } = useQuery({ queryKey: ['kba', 'artikel', id], queryFn: () => kbaApi.artikelSatu(id).then((r) => r.data) });
    const { data: versi } = useQuery({ queryKey: ['kba', 'artikel', id, 'versi'], queryFn: () => kbaApi.versiArtikel(Number(id)).then((r) => r.data), enabled: editor });
    const refresh = (m: string) => {
        setMsg(m);
        setSunting(false);
        qc.invalidateQueries({ queryKey: ['kba', 'artikel'] });
    };
    const hantar = useMutation({ mutationFn: () => kbaApi.hantarArtikel(Number(id)), onSuccess: (r) => refresh(r.message), onError: (e) => setMsg(ralat(e)) });
    const lapuk = useMutation({ mutationFn: () => kbaApi.lapukArtikel(Number(id)), onSuccess: (r) => refresh(r.message), onError: (e) => setMsg(ralat(e)) });

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }
    if (isLoading || !a) {
        return (
            <PageContainer>
                <Memuat />
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            {msg && (
                <Alert type="info" dismissible>
                    {msg}
                </Alert>
            )}
            <Card
                title={a.tajuk}
                actions={
                    editor && (
                        <>
                            {a.status === 'draf' && (
                                <Button onClick={() => hantar.mutate()} disabled={hantar.isPending}>
                                    Hantar untuk semakan
                                </Button>
                            )}
                            {a.status !== 'dalam_semakan' && (
                                <Button variant="secondary" onClick={() => setSunting((s) => !s)}>
                                    {sunting ? 'Tutup suntingan' : 'Sunting'}
                                </Button>
                            )}
                            {a.status !== 'lapuk' && a.status !== 'dalam_semakan' && (
                                <Button variant="danger" onClick={() => lapuk.mutate()} disabled={lapuk.isPending}>
                                    Tanda lapuk
                                </Button>
                            )}
                        </>
                    )
                }
            >
                <div className="flex flex-wrap items-center gap-2 text-body-sm text-txt-black-700">
                    <Badge variant={a.status === 'diterbitkan' ? 'success' : a.status === 'lapuk' ? 'danger' : 'warning'}>{STATUS_ARTIKEL_LABEL[a.status]}</Badge>
                    <KlasifikasiBadge k={a.klasifikasi} />
                    <span>v{a.versi}</span>
                    {a.versi_terbit !== null && a.versi_terbit !== a.versi && <span>(diterbitkan: v{a.versi_terbit})</span>}
                    {a.modul && <span>· {a.modul.toUpperCase()}</span>}
                    <span>· Terbit {tarikhMasa(a.tarikh_terbit)}</span>
                    <span>· Semakan seterusnya {a.tarikh_semakan_seterusnya ?? '—'}</span>
                </div>
                {a.pautan_tiket && (
                    <p className="text-body-sm">
                        Daripada tiket sokongan:{' '}
                        <Link href={a.pautan_tiket} className="font-medium text-txt-primary hover:underline">
                            {a.id_tiket_asal}
                        </Link>
                    </p>
                )}
                {a.perlu_semakan && <Alert type="warning">{a.sebab_perlu_semakan ?? 'Artikel perlu disemak.'}</Alert>}
                {a.catatan_semakan && <Alert type="info">Catatan penyemak: {a.catatan_semakan}</Alert>}
                {a.pautan_sumber && (
                    <Link href={a.pautan_sumber} className="text-body-sm font-medium text-txt-primary hover:underline">
                        Lihat seperti diindeks
                    </Link>
                )}
                {sunting ? <Sunting a={a} onDone={refresh} /> : <div className="whitespace-pre-wrap break-words text-body-sm text-txt-black-900">{a.kandungan}</div>}
            </Card>
            {editor && versi && (
                <Card title="Sejarah versi">
                    <ol className="space-y-2">
                        {versi.map((v) => (
                            <li key={v.id} className="text-body-sm">
                                <strong>v{v.nombor_versi}</strong> · {tarikhMasa(v.cap_masa)} · {v.sebab_perubahan ?? '—'}
                            </li>
                        ))}
                    </ol>
                </Card>
            )}
        </PageContainer>
    );
}
