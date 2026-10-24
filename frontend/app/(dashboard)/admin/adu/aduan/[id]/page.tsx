'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Modal } from '@/components/ui/Modal';
import { aduApi, bakiSla, ralat, tarikh, tarikhMasa, useRujukan, type AduanButiran, type Kod } from '@/lib/adu';
import { BakiSla, BenderaAduan, Card, Field, InfoGrid, inputClass, Keutamaan, Kosong, ModalBody, Select, StatusAduan } from '../../_components/common';
import { PetaAduan } from '../../_components/PetaAduan';

type Medan = { key: string; label: string; type: 'text' | 'textarea' | 'date' | 'select' | 'checkbox' | 'pegawai'; options?: Kod[]; required?: boolean; help?: string };

interface Aksi {
    key: string;
    label: string;
    tajuk: string;
    endpoint: string;
    medan: Medan[];
    variant?: 'primary' | 'outline';
    nilaiAwal?: Record<string, string>;
}

function AksiModal({ a, aksi, onClose }: { a: AduanButiran; aksi: Aksi; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [v, setV] = useState<Record<string, string>>(aksi.nilaiAwal ?? {});
    const [cari, setCari] = useState('');
    const pegawai = useQuery({
        queryKey: ['adu', 'pegawai-layak', a.id, cari],
        queryFn: () => aduApi.pegawaiLayak(a.id, cari).then((r) => r.data),
        enabled: aksi.medan.some((m) => m.type === 'pegawai'),
    });
    const save = useMutation({
        mutationFn: () => {
            // Fixed values (e.g. the duplicate's parent id) that have no input field.
            const body: Record<string, unknown> = Object.fromEntries(Object.entries(aksi.nilaiAwal ?? {}).filter(([k]) => !aksi.medan.some((m) => m.key === k)));
            aksi.medan.forEach((m) => {
                const val = v[m.key];
                if (m.type === 'checkbox') body[m.key] = val === '1';
                else if (val !== undefined && val !== '') body[m.key] = m.type === 'pegawai' ? Number(val) : val;
            });
            return aduApi.tindakan(a.id, aksi.endpoint, body);
        },
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['adu'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <Modal open onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <ModalBody
                    title={`${aksi.tajuk} — ${a.no_rujukan_aduan}`}
                    footer={
                        <>
                            <Button variant="outline" onClick={onClose}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={save.isPending}>
                                {aksi.label}
                            </Button>
                        </>
                    }
                >
                    {aksi.medan.map((m) => {
                        const id = `ak-${aksi.key}-${m.key}`;
                        const val = v[m.key] ?? '';
                        const on = (x: string) => setV((s) => ({ ...s, [m.key]: x }));
                        if (m.type === 'checkbox') {
                            return (
                                <label key={m.key} className="flex items-center gap-2 text-body-sm">
                                    <input type="checkbox" checked={val === '1'} onChange={(e) => on(e.target.checked ? '1' : '')} />
                                    {m.label}
                                </label>
                            );
                        }
                        return (
                            <Field key={m.key} label={m.label} htmlFor={id} help={m.help}>
                                {m.type === 'textarea' ? (
                                    <textarea id={id} rows={4} className={inputClass} value={val} onChange={(e) => on(e.target.value)} required={m.required} />
                                ) : m.type === 'select' ? (
                                    <Select id={id} value={val} onChange={on} options={m.options ?? []} empty="— Pilih —" required={m.required} />
                                ) : m.type === 'pegawai' ? (
                                    <div className="space-y-2">
                                        <input type="search" className={inputClass} placeholder="Cari nama pegawai" value={cari} onChange={(e) => setCari(e.target.value)} aria-label="Cari pegawai" />
                                        <Select id={id} value={val} onChange={on} options={(pegawai.data ?? []).map((p) => ({ kod: String(p.id), nama: [p.nama, p.jawatan].filter(Boolean).join(' — ') }))} empty={pegawai.isLoading ? 'Memuatkan…' : '— Pilih pegawai —'} required={m.required} />
                                    </div>
                                ) : (
                                    <input id={id} type={m.type} className={inputClass} value={val} onChange={(e) => on(e.target.value)} required={m.required} />
                                )}
                            </Field>
                        );
                    })}
                </ModalBody>
            </form>
        </Modal>
    );
}

function Pengadu({ a }: { a: AduanButiran }) {
    const [buka, setBuka] = useState(false);
    const [tujuan, setTujuan] = useState('');
    const [nilai, setNilai] = useState<Record<string, string | null> | null>(null);
    const dedah = useMutation({
        mutationFn: () => aduApi.dedah(a.id, tujuan),
        onSuccess: (r) => {
            setNilai(r.data);
            setBuka(false);
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const p = a.pengadu;
    if (p.tanpa_nama) return <Badge variant="neutral">Aduan tanpa nama — tiada identiti disimpan</Badge>;
    if (!p.ada) return <p className="text-body-sm text-txt-black-500">Tiada maklumat pengadu (aduan dalaman).</p>;
    const show = nilai ?? p.bertopeng ?? {};

    return (
        <div className="space-y-3">
            <InfoGrid
                items={[
                    ['Nama', show.nama],
                    ['No. telefon', show.no_telefon],
                    ['E-mel', show.emel],
                    ['Alamat', show.alamat],
                    ['Makluman melalui', p.saluran_makluman],
                ]}
            />
            {nilai ? (
                <p className="text-body-xs text-txt-black-500">Capaian ini telah direkod dalam audit trail capaian identiti pengadu.</p>
            ) : p.boleh_dedah ? (
                <Button size="sm" variant="outline" onClick={() => setBuka(true)}>
                    Papar identiti pengadu
                </Button>
            ) : (
                <p className="text-body-xs text-txt-black-500">Identiti pengadu hanya boleh dilihat oleh pegawai ditugaskan, penyelia dan Unit Aduan.</p>
            )}
            {buka && (
                <Modal open onClose={() => setBuka(false)}>
                    <ModalBody
                        title="Papar identiti pengadu (Sulit)"
                        footer={
                            <>
                                <Button variant="outline" onClick={() => setBuka(false)}>
                                    Batal
                                </Button>
                                <Button onClick={() => dedah.mutate()} disabled={tujuan.trim().length < 5 || dedah.isPending}>
                                    Papar
                                </Button>
                            </>
                        }
                    >
                        <Callout type="warning">Setiap capaian direkod bersama identiti anda, masa, alamat IP dan tujuan.</Callout>
                        <Field label="Tujuan capaian" htmlFor="pd-t">
                            <textarea id="pd-t" rows={3} className={inputClass} value={tujuan} onChange={(e) => setTujuan(e.target.value)} />
                        </Field>
                    </ModalBody>
                </Modal>
            )}
        </div>
    );
}

function Dokumen({ a }: { a: AduanButiran }) {
    const queryClient = useQueryClient();
    const { data } = useQuery({ queryKey: ['adu', 'dokumen', a.id], queryFn: () => aduApi.dokumen(a.id).then((r) => r.data) });
    const upload = useMutation({
        mutationFn: (files: File[]) => {
            const form = new FormData();
            files.forEach((f) => form.append('fail[]', f));
            return aduApi.muatNaik(a.id, form);
        },
        onSuccess: (r) => {
            toast.success(r.message);
            r.data.ditolak.forEach((m) => toast.error(m));
            queryClient.invalidateQueries({ queryKey: ['adu'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <div className="space-y-3">
            {a.tindakan_dibenarkan.urus && (
                <input
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/tiff,application/pdf,.docx,.xlsx"
                    capture="environment"
                    aria-label="Muat naik gambar atau dokumen"
                    className="block w-full text-body-sm"
                    onChange={(e) => {
                        const files = Array.from(e.target.files ?? []);
                        if (files.length) upload.mutate(files);
                        e.target.value = '';
                    }}
                />
            )}
            {(data ?? []).length === 0 ? (
                <Kosong>Tiada dokumen.</Kosong>
            ) : (
                <ul className="divide-y divide-otl-divider">
                    {data?.map((d) => (
                        <li key={d.id} className="py-2 flex flex-wrap items-center justify-between gap-2">
                            <Link href={`/admin/dms/${d.id}`} className="text-body-sm text-txt-primary hover:underline break-all">
                                {d.name}
                            </Link>
                            <span className="text-body-xs text-txt-black-500">
                                {d.category} · {tarikhMasa(d.uploaded_at)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Butiran() {
    const { id } = useParams<{ id: string }>();
    const { data: ref } = useRujukan();
    const [aksi, setAksi] = useState<Aksi | null>(null);
    const { data: a, error } = useQuery({ queryKey: ['adu', 'aduan', id], queryFn: () => aduApi.aduan(id).then((r) => r.data) });
    const { data: kronologi } = useQuery({ queryKey: ['adu', 'kronologi', id], queryFn: () => aduApi.kronologi(id).then((r) => r.data) });

    if (error) return <PageContainer><Callout type="danger">{ralat(error)}</Callout></PageContainer>;
    if (!a) return <PageContainer><p className="text-body-sm text-txt-black-500">Memuatkan…</p></PageContainer>;

    const terbuka = ['baharu', 'ditugaskan', 'dalam_siasatan', 'dirujuk'].includes(a.status);
    const urus = a.tindakan_dibenarkan.urus;
    const senaraiAksi: Aksi[] = [];
    if (a.tindakan_dibenarkan.tugaskan && terbuka) {
        senaraiAksi.push({ key: 'tugaskan', label: 'Tugaskan', tajuk: a.pegawai ? 'Tugaskan semula' : 'Tugaskan pegawai', endpoint: 'tugaskan', variant: 'primary', medan: [{ key: 'id_pegawai', label: 'Pegawai', type: 'pegawai', required: true }, { key: 'catatan', label: 'Catatan', type: 'textarea' }] });
    }
    if (urus && ['ditugaskan', 'dirujuk'].includes(a.status)) senaraiAksi.push({ key: 'mula', label: 'Mula siasatan', tajuk: 'Mula siasatan', endpoint: 'mula-siasatan', variant: 'primary', medan: [] });
    if (urus && terbuka) {
        senaraiAksi.push(
            {
                key: 'tindakan',
                label: 'Rekod',
                tajuk: 'Rekod tindakan / catatan',
                endpoint: 'tindakan',
                nilaiAwal: { jenis: 'tindakan' },
                medan: [
                    { key: 'jenis', label: 'Jenis', type: 'select', required: true, options: [{ kod: 'tindakan', nama: 'Tindakan susulan' }, { kod: 'catatan', nama: 'Catatan' }, { kod: 'komunikasi', nama: 'Komunikasi dengan pengadu' }] },
                    { key: 'keterangan', label: 'Keterangan', type: 'textarea', required: true },
                    { key: 'maklum_pengadu', label: 'Hantar sebagai makluman kepada pengadu (komunikasi sahaja)', type: 'checkbox' },
                ],
            },
            { key: 'lawatan', label: 'Jadualkan', tajuk: 'Jadual lawatan tapak', endpoint: 'lawatan', medan: [{ key: 'tarikh_jadual', label: 'Tarikh lawatan', type: 'date', required: true }] },
            { key: 'rujuk', label: 'Rujuk', tajuk: 'Rujuk kepada bahagian / agensi', endpoint: 'rujuk', nilaiAwal: { jenis_rujukan: ref?.bahagian_kategori[a.kategori] ?? '' }, medan: [{ key: 'jenis_rujukan', label: 'Rujuk kepada', type: 'select', required: true, options: ref?.jenis_rujukan ?? [] }, { key: 'nama_agensi', label: 'Nama agensi (jika agensi luar)', type: 'text' }, { key: 'keterangan', label: 'Maklumat dipindahkan', type: 'textarea', required: true }] },
            { key: 'eskalasi', label: 'Eskalasi', tajuk: 'Eskalasi kepada penyelia', endpoint: 'eskalasi', medan: [{ key: 'sebab', label: 'Sebab eskalasi', type: 'textarea', required: true }] },
            { key: 'tutup', label: 'Mohon penutupan', tajuk: 'Rekod resolusi & mohon penutupan', endpoint: 'penutupan', medan: [{ key: 'kategori_resolusi', label: 'Kategori resolusi', type: 'select', required: true, options: ref?.kategori_resolusi ?? [] }, { key: 'keterangan_resolusi', label: 'Keterangan resolusi', type: 'textarea', required: true }, { key: 'justifikasi', label: 'Justifikasi', type: 'textarea', required: true, help: 'Penutupan memerlukan kelulusan penyelia (bukan anda) melalui Modul Workflow.' }] },
        );
    }
    if (a.tindakan_dibenarkan.tugaskan && terbuka && a.status_bukan_tanah_mais) {
        senaraiAksi.push({ key: 'bukan_mais', label: 'Sahkan & hantar jawapan', tajuk: 'Sahkan bukan tanah MAIS', endpoint: 'bukan-tanah-mais', nilaiAwal: { jawapan: a.jawapan_automatik ?? '' }, medan: [{ key: 'jawapan', label: 'Jawapan kepada pengadu (semak sebelum hantar)', type: 'textarea', required: true }] });
    }
    if (a.tindakan_dibenarkan.tugaskan && terbuka && a.pendua_dicadang) {
        senaraiAksi.push({ key: 'pendua', label: 'Tanda pendua', tajuk: `Gabung sebagai pendua ${a.pendua_dicadang.no_rujukan_aduan}`, endpoint: 'pendua', nilaiAwal: { id_aduan_induk: a.pendua_dicadang.id }, medan: [] });
    }
    if (urus && a.status === 'ditutup') senaraiAksi.push({ key: 'buka', label: 'Buka semula', tajuk: 'Buka semula aduan', endpoint: 'buka-semula', medan: [{ key: 'sebab', label: 'Sebab', type: 'textarea', required: true }] });

    const titik = a.lat !== null && a.lng !== null ? { lat: a.lat, lng: a.lng } : null;
    const sla = bakiSla(a.sla);

    return (
        <PageContainer>
            <Card
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {a.no_rujukan_aduan} <StatusAduan a={a} /> <Keutamaan a={a} />
                    </span>
                }
                actions={senaraiAksi.map((x) => (
                    <Button key={x.key} size="sm" variant={x.variant ?? 'outline'} onClick={() => setAksi(x)}>
                        {x.tajuk}
                    </Button>
                ))}
            >
                <BenderaAduan a={a} />
                <InfoGrid
                    items={[
                        ['Kategori', [a.kategori_nama, a.subkategori_nama].filter(Boolean).join(' › ')],
                        ['Saluran', a.saluran_nama],
                        ['Diterima', tarikhMasa(a.tarikh_terima)],
                        ['Lot', a.lot ? <Link href={a.lot.pautan} className="text-txt-primary hover:underline">{`${a.lot.no_lot} (${a.lot.mukim ?? ''})`}</Link> : a.status_bukan_tanah_mais ? 'Luar lot MAIS' : '—'],
                        ['Daerah / mukim', [a.daerah, a.mukim].filter(Boolean).join(' / ') || '—'],
                        ['Lokasi', a.lokasi_teks ?? (titik ? `${titik.lat}, ${titik.lng}` : '—')],
                        ['Pegawai', a.pegawai?.nama ?? 'Belum ditugaskan'],
                        ['Penyelia', a.penyelia?.nama ?? '—'],
                        ['Dibuka semula', a.bilangan_buka_semula ? `${a.bilangan_buka_semula} kali` : 'Tidak'],
                    ]}
                />
                <div>
                    <h4 className="text-body-sm font-medium text-txt-black-700 mb-1">Keterangan</h4>
                    <p className="text-body-sm text-txt-black-900 whitespace-pre-line break-words">{a.keterangan}</p>
                </div>
                {a.status === 'menunggu_kelulusan' && <Callout type="info">Resolusi telah direkod dan menunggu kelulusan penyelia dalam Modul Workflow.</Callout>}
                {a.status_bukan_tanah_mais && (
                    <Callout type="warning" title="Lokasi di luar semua lot MAIS">
                        <p>{a.jawapan_automatik}</p>
                        {a.agensi_dicadang.length > 0 && <p className="mt-1">Agensi dicadangkan: {a.agensi_dicadang.map((g) => g.nama).join('; ')}</p>}
                        <p className="mt-1">{a.jawapan_dihantar ? 'Jawapan telah dihantar kepada pengadu.' : 'Jawapan belum dihantar — perlu semakan Unit Aduan.'}</p>
                    </Callout>
                )}
                {a.pendua_dicadang && a.status !== 'pendua' && (
                    <Callout type="info">
                        Kemungkinan pendua aduan{' '}
                        <Link className="underline" href={`/admin/adu/aduan/${a.pendua_dicadang.id}`}>
                            {a.pendua_dicadang.no_rujukan_aduan}
                        </Link>{' '}
                        (skor {a.skor_pendua}).
                    </Callout>
                )}
                {a.induk && (
                    <Callout type="info">
                        Digabungkan sebagai pendua kepada{' '}
                        <Link className="underline" href={`/admin/adu/aduan/${a.induk.id}`}>
                            {a.induk.no_rujukan_aduan}
                        </Link>
                        .
                    </Callout>
                )}
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card title="SLA">
                    {a.sla ? (
                        <>
                            <BakiSla a={a} />
                            <InfoGrid
                                items={[
                                    ['Status', a.sla.status_sla_nama],
                                    ['Sasaran respons', tarikhMasa(a.sla.masa_sasaran_respons)],
                                    ['Respons sebenar', tarikhMasa(a.sla.masa_respons_sebenar)],
                                    ['Sasaran selesai', tarikhMasa(a.sla.masa_sasaran_selesai)],
                                    ['Tempoh digunakan', `${a.sla.peratus_digunakan}%`],
                                    ['Eskalasi', String(a.sla.bilangan_eskalasi)],
                                ]}
                            />
                            <p className="text-body-xs text-txt-black-500">Dikira dalam hari/jam bekerja, tidak termasuk hujung minggu dan cuti umum Selangor ({sla.teks}).</p>
                        </>
                    ) : (
                        <Kosong>Tiada SLA.</Kosong>
                    )}
                </Card>
                <Card title="Pengadu">
                    <Pengadu a={a} />
                </Card>
                <Card title="Lokasi">{titik ? <PetaAduan pin={titik} height={220} label="Lokasi aduan" /> : <Kosong>Tiada koordinat.</Kosong>}</Card>
            </div>

            <Card>
                <Tabs tabs={{ kronologi: 'Kronologi', lawatan: 'Lawatan tapak', rujukan: 'Rujukan & eskalasi', resolusi: 'Resolusi', dokumen: 'Dokumen' }}>
                    <TabPanel value="kronologi">
                        <ol className="relative border-s border-otl-gray-200 ms-3 mt-4">
                            {(kronologi ?? []).map((k) => (
                                <li key={k.id} className="mb-6 ms-6 last:mb-0">
                                    <span className={`absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full ${k.komunikasi_pengadu ? 'bg-primary-400' : 'bg-otl-gray-300'}`} aria-hidden="true" />
                                    <p className="text-body-sm font-medium text-txt-black-900">
                                        {k.jenis_nama}
                                        {k.status_selepas && k.status_sebelum !== k.status_selepas && <span className="text-txt-black-500 font-normal"> · {k.status_sebelum ?? '—'} → {k.status_selepas}</span>}
                                    </p>
                                    <p className="text-body-xs text-txt-black-500">
                                        {tarikhMasa(k.tarikh_masa)} · {k.pegawai ?? 'Sistem'}
                                    </p>
                                    {k.keterangan && <p className="mt-1 text-body-sm text-txt-black-700 whitespace-pre-line break-words">{k.keterangan}</p>}
                                </li>
                            ))}
                        </ol>
                        <p className="mt-4 text-body-xs text-txt-black-500">Kronologi tidak boleh diubah atau dipadam; pembetulan direkod sebagai catatan baharu.</p>
                    </TabPanel>
                    <TabPanel value="lawatan">
                        <LawatanTab a={a} />
                    </TabPanel>
                    <TabPanel value="rujukan">
                        <div className="mt-4 space-y-4">
                            {a.rujukan.length === 0 && a.eskalasi.length === 0 && <Kosong>Tiada rujukan atau eskalasi.</Kosong>}
                            {a.rujukan.map((r) => (
                                <div key={`r${r.id}`} className="text-body-sm">
                                    <Badge variant="warning">{r.jenis_nama}</Badge> {r.nama_agensi} — {r.keterangan} <span className="text-txt-black-500">({r.pegawai})</span>
                                </div>
                            ))}
                            {a.eskalasi.map((e) => (
                                <div key={`e${e.id}`} className="text-body-sm">
                                    <Badge variant="danger">Peringkat {e.peringkat}</Badge> {e.sebab} <span className="text-txt-black-500">({tarikhMasa(e.tarikh)}, {e.jenis})</span>
                                </div>
                            ))}
                        </div>
                    </TabPanel>
                    <TabPanel value="resolusi">
                        <div className="mt-4 space-y-4">
                            {a.resolusi.length === 0 ? (
                                <Kosong>Belum ada resolusi.</Kosong>
                            ) : (
                                a.resolusi.map((r) => (
                                    <div key={r.id} className="rounded-md border border-otl-gray-200 p-3 space-y-1 text-body-sm">
                                        <p className="font-medium">
                                            {r.kategori_nama} <Badge variant={r.status === 'lulus' ? 'success' : r.status === 'menunggu' ? 'warning' : 'danger'}>{r.status}</Badge>
                                        </p>
                                        <p>{r.keterangan}</p>
                                        <p className="text-txt-black-500">Justifikasi: {r.justifikasi}</p>
                                        <p className="text-body-xs text-txt-black-500">
                                            Oleh {r.penyelesai ?? '—'} · {tarikhMasa(r.dicipta)}
                                            {r.pelulus && ` · Diputuskan oleh ${r.pelulus}`}
                                            {r.ulasan_pelulus && ` — "${r.ulasan_pelulus}"`} · Makluman pengadu: {r.status_makluman_pengadu}
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>
                    </TabPanel>
                    <TabPanel value="dokumen">
                        <div className="mt-4">
                            <Dokumen a={a} />
                        </div>
                    </TabPanel>
                </Tabs>
            </Card>

            {aksi && <AksiModal a={a} aksi={aksi} onClose={() => setAksi(null)} />}
        </PageContainer>
    );
}

function LawatanTab({ a }: { a: AduanButiran }) {
    const queryClient = useQueryClient();
    const [rekod, setRekod] = useState<number | null>(null);
    const [penemuan, setPenemuan] = useState('');
    const save = useMutation({
        mutationFn: () => aduApi.selesaiLawatan(a.id, rekod as number, { penemuan }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['adu'] });
            setRekod(null);
            setPenemuan('');
        },
        onError: (e) => toast.error(ralat(e)),
    });
    if (a.lawatan.length === 0) return <Kosong>Tiada lawatan tapak.</Kosong>;
    return (
        <div className="overflow-x-auto mt-4">
            <Table
                head={
                    <>
                        <th>Tarikh jadual</th>
                        <th>Status</th>
                        <th className="hidden sm:table-cell">Penemuan</th>
                        <th />
                    </>
                }
            >
                {a.lawatan.map((v) => (
                    <tr key={v.id}>
                        <td>
                            {tarikh(v.tarikh_jadual)}
                            <p className="text-body-xs text-txt-black-500">{v.pegawai}</p>
                        </td>
                        <td>
                            <Badge variant={v.status === 'selesai' ? 'success' : 'info'}>{v.status}</Badge>
                        </td>
                        <td className="hidden sm:table-cell max-w-sm break-words">{v.penemuan ?? '—'}</td>
                        <td className="text-right">
                            {a.tindakan_dibenarkan.urus && v.status === 'dijadualkan' && (
                                <Button size="sm" onClick={() => setRekod(v.id)}>
                                    Rekod penemuan
                                </Button>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
            {rekod !== null && (
                <Modal open onClose={() => setRekod(null)}>
                    <ModalBody
                        title="Rekod penemuan lawatan tapak"
                        footer={
                            <>
                                <Button variant="outline" onClick={() => setRekod(null)}>
                                    Batal
                                </Button>
                                <Button onClick={() => save.mutate()} disabled={penemuan.trim().length < 3 || save.isPending}>
                                    Simpan
                                </Button>
                            </>
                        }
                    >
                        <Field label="Penemuan" htmlFor="lw-p">
                            <textarea id="lw-p" rows={4} className={inputClass} value={penemuan} onChange={(e) => setPenemuan(e.target.value)} />
                        </Field>
                    </ModalBody>
                </Modal>
            )}
        </div>
    );
}

export default function AduanButiranPage() {
    return (
        <Suspense>
            <Butiran />
        </Suspense>
    );
}
