'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useParams, useSearchParams } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { kesApi, muatTurunFail, ralat, rm, tarikh, tarikhMasa, useRujukan, type Pihak } from '@/lib/kes';
import { BorangModal, type Medan } from '../../_components/BorangModal';
import { Card, Field, InfoGrid, inputClass, Kosong, RalatAkses, Select, StatusKes } from '../../_components/common';

const TABS = {
    ringkasan: 'Ringkasan',
    pihak: 'Pihak & Peguam',
    prosiding: 'Prosiding & Kronologi',
    dokumen: 'Dokumen',
    kos: 'Kos Guaman',
    keputusan: 'Keputusan & Susulan',
    sejarah: 'Sejarah',
};

type Borang = { title: string; fields: Medan[]; submit: (v: Record<string, string | boolean>) => Promise<{ message: string }>; label?: string } | null;

function Butiran() {
    const { id } = useParams<{ id: string }>();
    const search = useSearchParams();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const { data: k, error, isLoading } = useQuery({ queryKey: ['kes', 'butiran', id], queryFn: () => kesApi.kes(id).then((r) => r.data) });
    const [borang, setBorang] = useState<Borang>(null);
    const [didedah, setDidedah] = useState<Record<number, Pihak>>({});
    const [kategori, setKategori] = useState('pliding');
    const [fail, setFail] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }
    if (isLoading || !k) {
        return (
            <PageContainer>
                <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
            </PageContainer>
        );
    }

    const segar = () => qc.invalidateQueries({ queryKey: ['kes', 'butiran', id] });
    const buka = (b: NonNullable<Borang>) => setBorang(b);
    const urus = k.boleh.kemas_kini;
    const run = async (fn: () => Promise<{ message: string }>) => {
        try {
            const r = await fn();
            toast.success(r.message);
            segar();
        } catch (e) {
            toast.error(ralat(e));
        }
    };
    const opsi = <T extends { kod: string; nama: string }>(rows: T[] | undefined) => rows ?? [];

    const prosidingBorang = (): NonNullable<Borang> => ({
        title: 'Rekod prosiding',
        fields: [
            { name: 'tarikh_prosiding', label: 'Tarikh & masa prosiding', type: 'datetime-local', required: true },
            { name: 'jenis_prosiding', label: 'Jenis prosiding', type: 'select', options: opsi(ref?.jenis_prosiding), required: true },
            { name: 'status', label: 'Status', type: 'select', options: opsi(ref?.status_prosiding), initial: 'dijadualkan' },
            { name: 'hasil', label: 'Hasil', type: 'textarea' },
            { name: 'arahan_mahkamah', label: 'Arahan mahkamah', type: 'textarea' },
            { name: 'tarikh_berikutnya', label: 'Tarikh prosiding berikutnya', type: 'date', help: 'Dijadualkan automatik ke kalendar dan peringatan 14/7/1 hari' },
            { name: 'jenis_berikutnya', label: 'Jenis prosiding berikutnya', type: 'select', options: opsi(ref?.jenis_prosiding) },
            { name: 'ikut_jenis', label: 'Kemas kini status kes mengikut jenis prosiding', type: 'checkbox' },
        ],
        submit: (v) => kesApi.tindakan(k.id, 'prosiding', v),
    });

    async function muatNaik() {
        if (!fail) return;
        setBusy(true);
        try {
            const form = new FormData();
            form.append('fail', fail);
            form.append('kategori_dokumen', kategori);
            const r = await kesApi.muatNaik(k!.id, form);
            toast.success(r.message);
            setFail(null);
            segar();
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    async function dedah(p: Pihak) {
        try {
            const r = await kesApi.dedahPihak(k!.id, p.id);
            setDidedah((x) => ({ ...x, [p.id]: r.data }));
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function muatTurun(dok: number) {
        try {
            const r = await kesApi.muatTurun(k!.id, dok);
            window.open(r.data.url, '_blank', 'noopener');
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    const pegawaiOpsyen = (ref?.pegawai ?? []).map((u) => ({ kod: String(u.id), nama: u.nama }));
    const dokumenOpsyen = k.dokumen.map((d) => ({ kod: d.id_dokumen_dms, nama: `${d.nama_fail ?? d.id_dokumen_dms} (${d.kategori_nama})` }));

    return (
        <PageContainer>
            <Card
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {k.no_rujukan}
                        <StatusKes k={k} />
                        {k.sintetik && <Badge variant="neutral">Sintetik</Badge>}
                    </span>
                }
                actions={
                    <>
                        <Button size="sm" variant="secondary" onClick={() => muatTurunFail(`/kes/kes/${k.id}/cetak`, {}, 'kes.pdf').catch((e) => toast.error(ralat(e)))}>
                            Cetak ringkasan
                        </Button>
                        {urus && (
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() =>
                                    buka({
                                        title: 'Tukar status kes',
                                        fields: [
                                            { name: 'status', label: 'Status baharu', type: 'select', options: (ref?.status ?? []).filter((s) => s.aktif), required: true },
                                            { name: 'catatan', label: 'Catatan', type: 'textarea' },
                                        ],
                                        submit: (v) => kesApi.tindakan(k.id, 'status', v),
                                    })
                                }
                            >
                                Tukar status
                            </Button>
                        )}
                        {urus && k.status === 'didaftar' && k.peranan_mais && ['plaintif', 'pemohon'].includes(k.peranan_mais) && (
                            <Button size="sm" onClick={() => run(() => kesApi.tindakan(k.id, 'pemfailan'))}>
                                Mohon kelulusan pemfailan
                            </Button>
                        )}
                        {k.boleh.lulus && !k.status_arkib && (
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() =>
                                    buka({
                                        title: 'Tugaskan pegawai kes',
                                        fields: [
                                            { name: 'id_pegawai', label: 'Pegawai', type: 'select', options: pegawaiOpsyen, required: true },
                                            { name: 'justifikasi', label: 'Justifikasi', type: 'textarea', required: true },
                                        ],
                                        submit: (v) => kesApi.tindakan(k.id, 'tugaskan', v),
                                    })
                                }
                            >
                                Tugaskan pegawai
                            </Button>
                        )}
                        {k.boleh.lulus && !k.aktif && !k.status_arkib && (
                            <Button size="sm" variant="danger" onClick={() => buka({ title: 'Arkib kes', fields: [{ name: 'sebab', label: 'Sebab pengarkiban', type: 'textarea', required: true }], submit: (v) => kesApi.tindakan(k.id, 'arkib', v), label: 'Arkib' })}>
                                Arkib
                            </Button>
                        )}
                    </>
                }
            >
                <p className="font-medium text-txt-black-900 break-words">{k.tajuk}</p>
                {k.aliran_pemfailan && (
                    <p className="text-body-sm text-txt-black-500">
                        Aliran kelulusan pemfailan: <Badge variant={k.aliran_pemfailan.status === 'lulus' ? 'success' : 'warning'}>{k.aliran_pemfailan.status}</Badge>
                    </p>
                )}
                {k.status_arkib && <p className="text-body-sm text-txt-black-500">Diarkib {tarikhMasa(k.tarikh_arkib)} — {k.sebab_arkib}</p>}
            </Card>

            <Card>
                <Tabs tabs={TABS} defaultTab={search.get('tab') && search.get('tab')! in TABS ? search.get('tab')! : 'ringkasan'}>
                    <TabPanel value="ringkasan">
                        <div className="space-y-6">
                            <InfoGrid
                                items={[
                                    ['No. kes mahkamah', k.no_kes],
                                    ['Jenis kes', k.jenis_nama],
                                    ['Mahkamah', k.mahkamah],
                                    ['Peringkat mahkamah', k.peringkat_nama],
                                    ['Tarikh pemfailan', tarikh(k.tarikh_pemfailan)],
                                    ['Peranan MAIS', k.peranan_mais_nama],
                                    ['Nilai tuntutan', rm(k.nilai_tuntutan)],
                                    ['Kos guaman terkumpul', rm(k.kos_guaman)],
                                    ['Pegawai bertanggungjawab', k.pegawai?.nama ?? 'Belum ditugaskan'],
                                    ['Prosiding berikutnya', tarikh(k.tarikh_prosiding_berikutnya)],
                                    ['Daerah / mukim', [k.daerah, k.mukim].filter(Boolean).join(' / ') || '—'],
                                    ['Klasifikasi', <Badge key="k" variant="danger">Rahsia</Badge>],
                                ]}
                            />
                            <div>
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Ringkasan tuntutan</h4>
                                <p className="mt-1 whitespace-pre-line text-body-sm text-txt-black-700">{k.ringkasan_tuntutan ?? '—'}</p>
                            </div>
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h4 className="text-body-sm font-semibold text-txt-black-900">Lot berkaitan ({k.lot.length})</h4>
                                    {urus && (
                                        <Button size="sm" variant="secondary" onClick={() => run(() => kesApi.tindakan(k.id, 'segerak-mahkamah'))}>
                                            Segerak tarikh sistem mahkamah
                                        </Button>
                                    )}
                                </div>
                                <div className="overflow-x-auto">
                                    <Table
                                        head={
                                            <>
                                                <th>Lot</th>
                                                <th className="hidden sm:table-cell">Mukim / daerah</th>
                                                <th className="hidden md:table-cell">Kategori</th>
                                                <th>Status perundangan</th>
                                                {urus && <th className="sr-only">Tindakan</th>}
                                            </>
                                        }
                                    >
                                        {k.lot.map((l) => (
                                            <tr key={l.lot_id}>
                                                <td>
                                                    {l.pautan ? (
                                                        <Link href={l.pautan} className="text-txt-primary hover:underline">
                                                            {l.no_lot}
                                                        </Link>
                                                    ) : (
                                                        l.no_lot
                                                    )}
                                                    <p className="text-body-xs text-txt-black-500">{l.peranan_nama}</p>
                                                </td>
                                                <td className="hidden sm:table-cell">{[l.mukim, l.daerah].filter(Boolean).join(', ')}</td>
                                                <td className="hidden md:table-cell">{l.kategori_guna_tanah ?? '—'}</td>
                                                <td>{l.dalam_tindakan ? <Badge variant="danger">Dalam tindakan undang-undang</Badge> : <Badge variant="neutral">Tiada kes aktif</Badge>}</td>
                                                {urus && (
                                                    <td className="text-right">
                                                        <Button size="sm" variant="secondary" onClick={() => run(() => kesApi.nyahpautLot(k.id, l.lot_id))}>
                                                            Buang
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </Table>
                                </div>
                                {k.lot.length === 0 && <Kosong>Tiada lot dipaut.</Kosong>}
                                {urus && <PautLot kesId={k.id} onDone={segar} />}
                            </div>
                        </div>
                    </TabPanel>

                    <TabPanel value="pihak">
                        <div className="space-y-6">
                            <div className="flex flex-wrap justify-between gap-2">
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Pihak terlibat</h4>
                                {urus && (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            buka({
                                                title: 'Tambah pihak',
                                                fields: [
                                                    { name: 'nama_pihak', label: 'Nama', required: true },
                                                    { name: 'jenis_pihak', label: 'Jenis', type: 'select', options: opsi(ref?.jenis_pihak), required: true },
                                                    { name: 'peranan', label: 'Peranan', type: 'select', options: opsi(ref?.peranan), required: true },
                                                    { name: 'no_pengenalan', label: 'No. pengenalan / pendaftaran' },
                                                    { name: 'maklumat_perhubungan', label: 'Maklumat perhubungan' },
                                                    { name: 'status_wakilan', label: 'Status wakilan', type: 'select', options: opsi(ref?.status_wakilan) },
                                                ],
                                                submit: (v) => kesApi.tindakan(k.id, 'pihak', v),
                                            })
                                        }
                                    >
                                        Tambah pihak
                                    </Button>
                                )}
                            </div>
                            <div className="overflow-x-auto">
                                <Table
                                    head={
                                        <>
                                            <th>Peranan</th>
                                            <th>Nama</th>
                                            <th className="hidden md:table-cell">No. pengenalan</th>
                                            <th className="hidden md:table-cell">Perhubungan</th>
                                            <th className="hidden sm:table-cell">Wakilan</th>
                                        </>
                                    }
                                >
                                    {k.pihak.map((p0) => {
                                        const p = didedah[p0.id] ?? p0;
                                        return (
                                            <tr key={p.id}>
                                                <td>
                                                    {p.peranan_nama}
                                                    <p className="text-body-xs text-txt-black-500">{p.jenis_pihak_nama}</p>
                                                </td>
                                                <td className="break-words">
                                                    {p.nama_pihak}
                                                    {p0.bertopeng && !didedah[p0.id] && k.boleh.dedah_pihak && (
                                                        <button type="button" onClick={() => dedah(p0)} className="ms-2 text-body-xs font-medium text-primary-600 hover:underline">
                                                            Dedah (dilog)
                                                        </button>
                                                    )}
                                                </td>
                                                <td className="hidden md:table-cell font-mono text-body-xs">{p.no_pengenalan ?? '—'}</td>
                                                <td className="hidden md:table-cell">{p.maklumat_perhubungan ?? '—'}</td>
                                                <td className="hidden sm:table-cell">{p.status_wakilan_nama}</td>
                                            </tr>
                                        );
                                    })}
                                </Table>
                            </div>
                            <div className="flex flex-wrap justify-between gap-2">
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Peguam</h4>
                                {urus && (
                                    <span className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                buka({
                                                    title: 'Tambah peguam pihak',
                                                    fields: [
                                                        { name: 'nama_peguam', label: 'Nama peguam', required: true },
                                                        { name: 'nama_firma', label: 'Firma' },
                                                        { name: 'pegawai_penghubung', label: 'Pegawai penghubung' },
                                                        { name: 'no_telefon', label: 'Telefon' },
                                                        { name: 'emel', label: 'E-mel' },
                                                        { name: 'kes_pihak_id', label: 'Mewakili', type: 'select', options: k.pihak.map((p) => ({ kod: String(p.id), nama: `${p.nama_pihak} (${p.peranan_nama})` })) },
                                                    ],
                                                    submit: (v) => kesApi.tindakan(k.id, 'peguam', v),
                                                })
                                            }
                                        >
                                            Tambah peguam
                                        </Button>
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                buka({
                                                    title: 'Lantik panel peguam MAIS',
                                                    fields: [
                                                        { name: 'kes_panel_peguam_id', label: 'Panel peguam', type: 'select', options: (ref?.panel ?? []).map((p) => ({ kod: String(p.id), nama: p.nama_firma })), required: true },
                                                        { name: 'nama_peguam', label: 'Peguam bertugas' },
                                                    ],
                                                    submit: (v) => kesApi.tindakan(k.id, 'peguam/panel', v),
                                                    label: 'Hantar untuk kelulusan',
                                                })
                                            }
                                        >
                                            Lantik panel
                                        </Button>
                                    </span>
                                )}
                            </div>
                            <div className="overflow-x-auto">
                                <Table
                                    head={
                                        <>
                                            <th>Peguam</th>
                                            <th className="hidden sm:table-cell">Firma</th>
                                            <th className="hidden md:table-cell">Perhubungan</th>
                                            <th>Status</th>
                                        </>
                                    }
                                >
                                    {k.peguam.map((g) => (
                                        <tr key={g.id}>
                                            <td>
                                                {g.nama_peguam}
                                                {g.indikator_panel_mais && (
                                                    <span className="ms-2">
                                                        <Badge variant="info">Panel MAIS</Badge>
                                                    </span>
                                                )}
                                                <p className="text-body-xs text-txt-black-500">Mewakili: {k.pihak.find((p) => p.id === g.kes_pihak_id)?.peranan_nama ?? '—'}</p>
                                            </td>
                                            <td className="hidden sm:table-cell">{g.nama_firma ?? '—'}</td>
                                            <td className="hidden md:table-cell text-body-xs">{[g.pegawai_penghubung, g.no_telefon, g.emel].filter(Boolean).join(' · ') || '—'}</td>
                                            <td>
                                                <Badge variant={g.status_lantikan === 'aktif' ? 'success' : g.status_lantikan === 'menunggu_kelulusan' ? 'warning' : 'neutral'}>{g.status_lantikan_nama}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                        </div>
                    </TabPanel>

                    <TabPanel value="prosiding">
                        <div className="space-y-6">
                            {urus && (
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" onClick={() => buka(prosidingBorang())}>
                                        Rekod prosiding
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            buka({
                                                title: 'Tambah tarikh penting',
                                                fields: [
                                                    { name: 'jenis', label: 'Jenis', type: 'select', options: opsi(ref?.jenis_tarikh), required: true },
                                                    { name: 'tarikh', label: 'Tarikh', type: 'date', required: true },
                                                    { name: 'keterangan', label: 'Keterangan' },
                                                ],
                                                submit: (v) => kesApi.tindakan(k.id, 'tarikh', v),
                                            })
                                        }
                                    >
                                        Tarikh penting
                                    </Button>
                                </div>
                            )}
                            <div className="overflow-x-auto">
                                <Table
                                    head={
                                        <>
                                            <th>Tarikh</th>
                                            <th>Prosiding</th>
                                            <th className="hidden md:table-cell">Hasil / arahan</th>
                                            <th className="hidden sm:table-cell">Berikutnya</th>
                                        </>
                                    }
                                >
                                    {k.prosiding.map((p) => (
                                        <tr key={p.id}>
                                            <td className="whitespace-nowrap">{tarikhMasa(p.tarikh_prosiding)}</td>
                                            <td>
                                                {p.jenis_nama}
                                                <p>
                                                    <Badge variant={p.status === 'dijadualkan' ? 'info' : p.status === 'selesai' ? 'success' : 'neutral'}>{p.status_nama}</Badge>
                                                    {p.sumber === 'efiling' && (
                                                        <span className="ms-1">
                                                            <Badge variant="neutral">Sistem mahkamah</Badge>
                                                        </span>
                                                    )}
                                                </p>
                                            </td>
                                            <td className="hidden md:table-cell max-w-md text-body-xs">
                                                {p.hasil}
                                                {p.arahan_mahkamah && <p className="text-txt-black-500">Arahan: {p.arahan_mahkamah}</p>}
                                            </td>
                                            <td className="hidden sm:table-cell whitespace-nowrap">{tarikh(p.tarikh_berikutnya)}</td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                            {k.prosiding.length === 0 && <Kosong>Tiada prosiding direkodkan.</Kosong>}
                            {k.tarikh_penting.length > 0 && (
                                <div>
                                    <h4 className="text-body-sm font-semibold text-txt-black-900">Tarikh penting</h4>
                                    <ul className="mt-2 space-y-1 text-body-sm">
                                        {k.tarikh_penting.map((t) => (
                                            <li key={t.id}>
                                                {tarikh(t.tarikh)} — {t.jenis_nama}
                                                {t.keterangan ? `: ${t.keterangan}` : ''} <Badge variant={t.status === 'belum' ? 'warning' : 'neutral'}>{t.status}</Badge>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <div>
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Kronologi kes</h4>
                                <ol className="mt-3 relative border-s border-otl-gray-200 ms-2 space-y-4">
                                    {k.kronologi.map((e, i) => (
                                        <li key={i} className="ms-4">
                                            <span className="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-primary-600" aria-hidden />
                                            <p className="text-body-xs text-txt-black-500">{tarikhMasa(e.tarikh)}</p>
                                            <p className="text-body-sm text-txt-black-900">{e.tajuk}</p>
                                            {e.butiran && <p className="text-body-xs text-txt-black-500">{e.butiran}</p>}
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </div>
                    </TabPanel>

                    <TabPanel value="dokumen">
                        <div className="space-y-4">
                            <p className="text-body-xs text-txt-black-500">Dokumen perundangan disimpan dalam Modul Dokumen Digital (berversi, disulitkan, tanda air pada paparan dan cetakan). Setiap muat turun dilog.</p>
                            {urus && (
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                    <Field label="Kategori dokumen" htmlFor="d-kat">
                                        <Select id="d-kat" value={kategori} onChange={setKategori} options={opsi(ref?.kategori_dokumen)} />
                                    </Field>
                                    <Field label="Fail" htmlFor="d-fail">
                                        <input id="d-fail" type="file" className={inputClass} onChange={(e) => setFail(e.target.files?.[0] ?? null)} />
                                    </Field>
                                    <Button disabled={!fail || busy} onClick={muatNaik}>
                                        {busy ? 'Memuat naik…' : 'Muat naik'}
                                    </Button>
                                </div>
                            )}
                            <div className="overflow-x-auto">
                                <Table
                                    head={
                                        <>
                                            <th>Dokumen</th>
                                            <th className="hidden sm:table-cell">Kategori</th>
                                            <th className="hidden md:table-cell">Versi</th>
                                            <th className="hidden md:table-cell">Tarikh</th>
                                            <th className="sr-only">Tindakan</th>
                                        </>
                                    }
                                >
                                    {k.dokumen.map((d) => (
                                        <tr key={d.id}>
                                            <td className="break-words max-w-xs">{d.nama_fail ?? d.id_dokumen_dms}</td>
                                            <td className="hidden sm:table-cell">{d.kategori_nama}</td>
                                            <td className="hidden md:table-cell">v{d.versi}</td>
                                            <td className="hidden md:table-cell whitespace-nowrap">{tarikhMasa(d.tarikh_muat_naik)}</td>
                                            <td className="text-right">
                                                <Button size="sm" variant="secondary" onClick={() => muatTurun(d.id)}>
                                                    Muat turun
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                            {k.dokumen.length === 0 && <Kosong>Tiada dokumen.</Kosong>}
                        </div>
                    </TabPanel>

                    <TabPanel value="kos">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                {k.kos_mengikut_kategori.map((c) => (
                                    <div key={c.kod} className="rounded-md border border-otl-gray-200 p-3">
                                        <p className="text-body-xs text-txt-black-500">{c.nama}</p>
                                        <p className="font-semibold text-txt-black-900">{rm(c.jumlah)}</p>
                                    </div>
                                ))}
                            </div>
                            {urus && (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        buka({
                                            title: 'Rekod kos guaman',
                                            fields: [
                                                { name: 'kategori_kos', label: 'Kategori', type: 'select', options: opsi(ref?.kategori_kos), required: true },
                                                { name: 'amaun', label: 'Amaun (RM)', type: 'decimal', required: true },
                                                { name: 'tarikh', label: 'Tarikh', type: 'date', required: true },
                                                { name: 'no_resit', label: 'No. resit / invois' },
                                                { name: 'keterangan', label: 'Keterangan' },
                                                { name: 'kes_panel_peguam_id', label: 'Panel peguam (fi)', type: 'select', options: (ref?.panel ?? []).map((p) => ({ kod: String(p.id), nama: p.nama_firma })) },
                                            ],
                                            submit: (v) => kesApi.tindakan(k.id, 'kos', v),
                                        })
                                    }
                                >
                                    Rekod kos
                                </Button>
                            )}
                            <div className="overflow-x-auto">
                                <Table
                                    head={
                                        <>
                                            <th>Tarikh</th>
                                            <th>Kategori</th>
                                            <th className="text-right">Amaun</th>
                                            <th className="hidden sm:table-cell">Status bayaran</th>
                                            <th className="sr-only">Tindakan</th>
                                        </>
                                    }
                                >
                                    {k.kos.map((c) => (
                                        <tr key={c.id}>
                                            <td className="whitespace-nowrap">{tarikh(c.tarikh)}</td>
                                            <td>
                                                {c.kategori_nama}
                                                <p className="text-body-xs text-txt-black-500">{[c.keterangan, c.no_resit].filter(Boolean).join(' · ')}</p>
                                            </td>
                                            <td className="text-right whitespace-nowrap">{rm(c.amaun)}</td>
                                            <td className="hidden sm:table-cell">
                                                <Badge variant={c.status_bayaran === 'dibayar' ? 'success' : c.status_bayaran === 'ditolak' ? 'danger' : 'warning'}>{c.status_bayaran_nama}</Badge>
                                                {c.rujukan_grp && <p className="text-body-xs text-txt-black-500">GRP {c.rujukan_grp}</p>}
                                            </td>
                                            <td className="text-right whitespace-nowrap">
                                                {urus && ['belum_dibayar', 'ditolak'].includes(c.status_bayaran) && (
                                                    <Button size="sm" variant="secondary" onClick={() => run(() => kesApi.tindakan(k.id, `kos/${c.id}/mohon-bayaran`))}>
                                                        Mohon bayaran
                                                    </Button>
                                                )}
                                                {urus && ['diluluskan', 'dihantar_grp'].includes(c.status_bayaran) && (
                                                    <Button size="sm" variant="secondary" onClick={() => run(() => kesApi.tindakan(k.id, `kos/${c.id}/semak-grp`))}>
                                                        Semak GRP
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                            {k.kos.length === 0 && <Kosong>Tiada kos direkodkan.</Kosong>}
                        </div>
                    </TabPanel>

                    <TabPanel value="keputusan">
                        <div className="space-y-6">
                            {urus && (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            buka({
                                                title: 'Rekod keputusan mahkamah',
                                                fields: [
                                                    { name: 'jenis_keputusan', label: 'Keputusan', type: 'select', options: opsi(ref?.jenis_keputusan), required: true },
                                                    { name: 'tarikh_keputusan', label: 'Tarikh keputusan', type: 'date', required: true },
                                                    { name: 'ringkasan_perintah', label: 'Ringkasan perintah', type: 'textarea', required: true },
                                                    { name: 'implikasi', label: 'Implikasi', type: 'textarea' },
                                                    { name: 'id_dokumen_penghakiman', label: 'Dokumen penghakiman', type: 'select', options: dokumenOpsyen },
                                                ],
                                                submit: (v) => kesApi.tindakan(k.id, 'keputusan', v),
                                                label: 'Rekod & cetuskan tindakan susulan',
                                            })
                                        }
                                    >
                                        Rekod keputusan
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            buka({
                                                title: 'Rayuan',
                                                fields: [
                                                    { name: 'pihak_merayu', label: 'Pihak merayu', type: 'select', options: [{ kod: 'mais', nama: 'MAIS (perlu kelulusan)' }, { kod: 'pihak_lawan', nama: 'Pihak lawan' }], required: true },
                                                    { name: 'mahkamah_rayuan', label: 'Mahkamah rayuan' },
                                                    { name: 'peringkat_mahkamah', label: 'Peringkat', type: 'select', options: opsi(ref?.peringkat_mahkamah) },
                                                    { name: 'tarikh_had_failan', label: 'Had pemfailan', type: 'date' },
                                                    { name: 'alasan', label: 'Alasan rayuan', type: 'textarea', required: true },
                                                ],
                                                submit: (v) => kesApi.tindakan(k.id, 'rayuan', v),
                                            })
                                        }
                                    >
                                        Rayuan
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            buka({
                                                title: 'Cadangan penyelesaian luar mahkamah',
                                                fields: [
                                                    { name: 'terma', label: 'Terma penyelesaian', type: 'textarea', required: true },
                                                    { name: 'nilai_penyelesaian', label: 'Nilai penyelesaian (RM)', type: 'decimal' },
                                                ],
                                                submit: (v) => kesApi.tindakan(k.id, 'penyelesaian', v),
                                                label: 'Hantar untuk kelulusan',
                                            })
                                        }
                                    >
                                        Penyelesaian
                                    </Button>
                                </div>
                            )}
                            {k.keputusan.map((d) => (
                                <div key={d.id} className="rounded-md border border-otl-gray-200 p-4 space-y-1">
                                    <p className="font-medium text-txt-black-900">
                                        {d.jenis_nama} · {tarikh(d.tarikh_keputusan)}
                                    </p>
                                    <p className="text-body-sm text-txt-black-700 whitespace-pre-line">{d.ringkasan_perintah}</p>
                                    {d.implikasi && <p className="text-body-xs text-txt-black-500">Implikasi: {d.implikasi}</p>}
                                </div>
                            ))}
                            <div>
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Tindakan susulan (tugasan Modul Workflow)</h4>
                                <ul className="mt-2 divide-y divide-otl-divider">
                                    {k.tindakan.map((t) => (
                                        <li key={t.id} className="py-2 flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="text-body-sm text-txt-black-900">{t.jenis_nama}</p>
                                                <p className="text-body-xs text-txt-black-500">
                                                    {t.keterangan} · sasaran {tarikh(t.tarikh_sasaran)} · {t.pegawai ?? '—'}
                                                </p>
                                            </div>
                                            <span className="flex flex-wrap gap-2 items-center">
                                                <Badge variant={t.status === 'selesai' ? 'success' : t.status === 'dibatalkan' ? 'neutral' : 'warning'}>{t.status_nama}</Badge>
                                                {urus && t.jenis_tindakan === 'pengemaskinian_hak_milik' && t.status !== 'dibatalkan' && (
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() =>
                                                            buka({
                                                                title: 'Cadang pengemaskinian rekod hak milik (MOD-HKM)',
                                                                fields: [
                                                                    { name: 'no_hak_milik', label: 'No. hak milik', required: true },
                                                                    { name: 'kod_mukim', label: 'Kod mukim' },
                                                                    { name: 'jenis_perubahan', label: 'Jenis perubahan', type: 'select', options: [{ kod: 'status', nama: 'Status tanah' }, { kod: 'pemilik', nama: 'Pemilik' }, { kod: 'sekatan', nama: 'Sekatan / kaveat' }], required: true },
                                                                    { name: 'status_selepas', label: 'Status baharu (bagi perubahan status)' },
                                                                    { name: 'id_dokumen_perintah', label: 'Dokumen perintah mahkamah', type: 'select', options: dokumenOpsyen, required: true },
                                                                    { name: 'ringkasan', label: 'Ringkasan perubahan', type: 'textarea' },
                                                                ],
                                                                submit: ({ status_selepas, ...v }) =>
                                                                    kesApi.tindakan(k.id, `tindakan/${t.id}/kemas-kini-rekod`, {
                                                                        ...v,
                                                                        data: v.jenis_perubahan === 'status' ? { status_selepas, tarikh_berkuat_kuasa: new Date().toISOString().slice(0, 10) } : {},
                                                                    }),
                                                                label: 'Hantar ke Modul Hak Milik',
                                                            })
                                                        }
                                                    >
                                                        Kemas kini hak milik
                                                    </Button>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                {k.tindakan.length === 0 && <Kosong>Tiada tindakan susulan.</Kosong>}
                            </div>
                            {(k.rayuan.length > 0 || k.penyelesaian.length > 0) && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <h4 className="text-body-sm font-semibold text-txt-black-900">Rayuan</h4>
                                        <ul className="mt-2 space-y-2 text-body-sm">
                                            {k.rayuan.map((r) => (
                                                <li key={r.id}>
                                                    {r.pihak_merayu === 'mais' ? 'MAIS' : 'Pihak lawan'} · {r.mahkamah_rayuan ?? '—'} <Badge variant="info">{r.status_nama}</Badge>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                    <div>
                                        <h4 className="text-body-sm font-semibold text-txt-black-900">Penyelesaian</h4>
                                        <ul className="mt-2 space-y-2 text-body-sm">
                                            {k.penyelesaian.map((p) => (
                                                <li key={p.id} className="flex flex-wrap items-center gap-2">
                                                    {rm(p.nilai_penyelesaian)} <Badge variant="info">{p.status_nama}</Badge>
                                                    {urus && p.status === 'diluluskan' && (
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            onClick={() =>
                                                                buka({
                                                                    title: 'Meterai penyelesaian',
                                                                    fields: [
                                                                        { name: 'id_dokumen_perjanjian', label: 'Perjanjian penyelesaian', type: 'select', options: dokumenOpsyen, required: true },
                                                                        { name: 'tarikh_persetujuan', label: 'Tarikh persetujuan', type: 'date', required: true },
                                                                    ],
                                                                    submit: (v) => kesApi.tindakan(k.id, `penyelesaian/${p.id}/meterai`, v),
                                                                })
                                                            }
                                                        >
                                                            Meterai
                                                        </Button>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            )}
                        </div>
                    </TabPanel>

                    <TabPanel value="sejarah">
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Sejarah status</h4>
                                <ul className="mt-2 space-y-2 text-body-sm">
                                    {k.sejarah_status.map((s, i) => (
                                        <li key={i}>
                                            <span className="text-txt-black-500">{tarikhMasa(s.tarikh)}</span> — {s.status_lama ?? '—'} → <strong>{s.status_baharu}</strong> ({s.pengguna ?? 'sistem'}){s.catatan ? `: ${s.catatan}` : ''}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div>
                                <h4 className="text-body-sm font-semibold text-txt-black-900">Sejarah pegawai bertanggungjawab</h4>
                                <ul className="mt-2 space-y-2 text-body-sm">
                                    {k.sejarah_pegawai.map((s, i) => (
                                        <li key={i}>
                                            <span className="text-txt-black-500">{tarikhMasa(s.tarikh)}</span> — {s.lama ?? '—'} → <strong>{s.baharu}</strong>: {s.justifikasi}
                                        </li>
                                    ))}
                                    {k.sejarah_pegawai.length === 0 && <li className="text-txt-black-500">Belum ditugaskan.</li>}
                                </ul>
                            </div>
                        </div>
                    </TabPanel>
                </Tabs>
            </Card>

            {borang && (
                <BorangModal
                    open
                    title={borang.title}
                    fields={borang.fields}
                    submitLabel={borang.label}
                    onClose={() => setBorang(null)}
                    onSubmit={async (v) => {
                        const r = await borang.submit(v);
                        toast.success(r.message);
                        segar();
                    }}
                />
            )}
        </PageContainer>
    );
}

function PautLot({ kesId, onDone }: { kesId: string; onDone: () => void }) {
    const [cari, setCari] = useState('');
    const { data } = useQuery({ queryKey: ['kes', 'cari-lot', cari], queryFn: () => kesApi.cariLot(cari).then((r) => r.data), enabled: cari.trim().length >= 2 });
    return (
        <div className="space-y-2">
            <Field label="Paut lot lain" htmlFor="pl-cari">
                <input id="pl-cari" type="search" className={inputClass} placeholder="No. lot" value={cari} onChange={(e) => setCari(e.target.value)} />
            </Field>
            {(data ?? []).map((l) => (
                <div key={l.id} className="flex items-center justify-between gap-2 text-body-sm">
                    <span>
                        {l.no_lot} <span className="text-txt-black-500">· {[l.mukim, l.daerah].filter(Boolean).join(', ')}</span>
                    </span>
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                            kesApi
                                .tindakan(kesId, 'lot', { lot_ids: [l.id] })
                                .then((r) => {
                                    toast.success(r.message);
                                    setCari('');
                                    onDone();
                                })
                                .catch((e) => toast.error(ralat(e)))
                        }
                    >
                        Paut
                    </Button>
                </div>
            ))}
        </div>
    );
}

export default function KesButiranPage() {
    return (
        <Suspense>
            <Butiran />
        </Suspense>
    );
}
