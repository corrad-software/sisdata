'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Modal } from '@/components/ui/Modal';
import { Spinner } from '@/components/ui/Spinner';
import { JENIS_LABEL, KATEGORI_LABEL, lapApi, ralat, useCan, type Katalog } from '@/lib/lap';
import { Card, Field, inputClass, Kosong } from './_components/common';

/** SRS-LAP-02/09/23 — central report catalogue. */
export default function KatalogPage() {
    const can = useCan();
    const [kategori, setKategori] = useState('');
    const [cari, setCari] = useState('');
    const [sunting, setSunting] = useState<Katalog | null>(null);
    const q = useQuery({ queryKey: ['lap', 'katalog'], queryFn: () => lapApi.katalog() });

    const rows = useMemo(() => {
        const s = cari.trim().toLowerCase();
        return (q.data?.data ?? []).filter((k) => (!kategori || k.kategori === kategori) && (!s || `${k.kod} ${k.nama} ${k.definisi_metrik ?? ''}`.toLowerCase().includes(s)));
    }, [q.data, kategori, cari]);

    const byCat = useMemo(() => {
        const m = new Map<string, Katalog[]>();
        for (const r of rows) m.set(r.kategori, [...(m.get(r.kategori) ?? []), r]);
        return m;
    }, [rows]);

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Kategori" htmlFor="kat">
                        <select id="kat" className={inputClass} value={kategori} onChange={(e) => setKategori(e.target.value)}>
                            <option value="">Semua kategori</option>
                            {(q.data?.kategori ?? []).map((c) => (
                                <option key={c.kod} value={c.kod}>
                                    {c.nama}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Cari laporan" htmlFor="cari" className="sm:col-span-2">
                        <input id="cari" className={inputClass} value={cari} onChange={(e) => setCari(e.target.value)} placeholder="cth. tunggakan, SLA, aduan" />
                    </Field>
                </div>
                <p className="text-body-sm text-txt-black-500">
                    Hanya laporan yang anda dibenarkan dipaparkan. Setiap laporan dijana mengikut skop capaian anda (row-level security pada pangkalan data).
                </p>
            </Card>

            {q.isLoading && (
                <div className="flex justify-center py-10">
                    <Spinner />
                </div>
            )}
            {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}
            {q.data && rows.length === 0 && <Kosong>Tiada laporan sepadan.</Kosong>}

            {[...byCat.entries()].map(([cat, items]) => (
                <Card key={cat} title={KATEGORI_LABEL[cat] ?? cat}>
                    <ul className="divide-y divide-otl-gray-200">
                        {items.map((k) => (
                            <li key={k.kod} className="py-3 flex flex-col sm:flex-row sm:items-start gap-3 justify-between">
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-body-xs font-mono text-txt-black-500">{k.kod}</span>
                                        <Badge variant={k.jenis === 'modul' ? 'neutral' : k.jenis === 'komposit' ? 'info' : 'success'}>{JENIS_LABEL[k.jenis]}</Badge>
                                        {k.status_pengesahan === 'disahkan' ? <Badge variant="success">Disahkan CRP-2</Badge> : <Badge variant="warning">Draf</Badge>}
                                        {!k.aktif && <Badge variant="danger">Tidak aktif</Badge>}
                                    </div>
                                    <p className="font-medium text-txt-black-900">{k.nama}</p>
                                    {k.definisi_metrik && <p className="text-body-sm text-txt-black-500">{k.definisi_metrik}</p>}
                                    <p className="text-body-xs text-txt-black-500">
                                        Sumber: {k.sumber_data ?? '—'} · Pemilik: {k.pemilik ?? '—'}
                                    </p>
                                </div>
                                <div className="flex gap-2 shrink-0">
                                    {k.boleh_buka &&
                                        (k.jenis === 'modul' && k.pautan ? (
                                            <Link href={k.pautan} className="inline-flex items-center rounded-md border border-otl-gray-300 px-3 py-1.5 text-body-sm font-medium text-txt-black-700 hover:bg-bg-washed">
                                                Buka di modul
                                            </Link>
                                        ) : (
                                            <Link href={`/admin/lap/laporan/${encodeURIComponent(k.kod)}`} className="inline-flex items-center rounded-md bg-primary-600 px-3 py-1.5 text-body-sm font-medium text-white hover:bg-primary-700">
                                                Jana laporan
                                            </Link>
                                        ))}
                                    {can('lap.katalog.kemas_kini') && (
                                        <Button size="sm" variant="outline" onClick={() => setSunting(k)}>
                                            Tetapan
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            ))}

            {sunting && <SuntingKatalog k={sunting} onClose={() => setSunting(null)} />}
        </PageContainer>
    );
}

function SuntingKatalog({ k, onClose }: { k: Katalog; onClose: () => void }) {
    const qc = useQueryClient();
    const [pemilik, setPemilik] = useState(k.pemilik ?? '');
    const [metrik, setMetrik] = useState(k.definisi_metrik ?? '');
    const [peranan, setPeranan] = useState(k.peranan_dibenarkan.join(', '));
    const [aktif, setAktif] = useState(k.aktif);
    const [sah, setSah] = useState(k.status_pengesahan);
    const m = useMutation({
        mutationFn: () =>
            lapApi.kemasKiniKatalog(k.kod, {
                pemilik, definisi_metrik: metrik, aktif, status_pengesahan: sah,
                peranan_dibenarkan: peranan.split(',').map((s) => s.trim()).filter(Boolean),
            }),
        onSuccess: () => {
            toast.success('Disimpan.');
            qc.invalidateQueries({ queryKey: ['lap', 'katalog'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-4">
                <h3 className="font-heading font-semibold text-body-lg">Tetapan laporan {k.kod}</h3>
                <Field label="Pemilik laporan" htmlFor="pemilik">
                    <input id="pemilik" className={inputClass} value={pemilik} onChange={(e) => setPemilik(e.target.value)} />
                </Field>
                <Field label="Definisi metrik" htmlFor="metrik">
                    <textarea id="metrik" rows={4} className={inputClass} value={metrik} onChange={(e) => setMetrik(e.target.value)} />
                </Field>
                <Field label="Peranan dibenarkan (kod, dipisah koma)" htmlFor="peranan" help="Kosong = semua pengguna yang mempunyai kebenaran set data.">
                    <input id="peranan" className={inputClass} value={peranan} onChange={(e) => setPeranan(e.target.value)} placeholder="cth. pengurusan_tertinggi, ketua_bahagian" />
                </Field>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Field label="Status pengesahan (CRP-2)" htmlFor="sah">
                        <select id="sah" className={inputClass} value={sah} onChange={(e) => setSah(e.target.value as 'draf' | 'disahkan')}>
                            <option value="draf">Draf</option>
                            <option value="disahkan">Disahkan</option>
                        </select>
                    </Field>
                    <Field label="Aktif" htmlFor="aktif">
                        <select id="aktif" className={inputClass} value={aktif ? '1' : '0'} onChange={(e) => setAktif(e.target.value === '1')}>
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                    </Field>
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={() => m.mutate()} disabled={m.isPending}>
                        Simpan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
