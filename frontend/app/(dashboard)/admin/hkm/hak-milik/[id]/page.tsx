'use client';

import { Suspense, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useParams, useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Timeline } from '@/components/ui/Timeline';
import { Modal } from '@/components/ui/Modal';
import { DocumentAttachments } from '@/components/dms/DocumentAttachments';
import { hkmApi, keluasan, LABEL_MEDAN, ralat, tarikh, tarikhMasa, useCan, useRujukan, type HakMilikPenuh } from '@/lib/hkm';
import { BahagianChip, BenderaList, Card, Field, InfoGrid, inputClass, JenisChip, KategoriChip, Kosong, ModalBody, Select, StatusDokumen } from '../../_components/common';
import { PerubahanModal, type PerubahanAwal } from '../../_components/PerubahanModal';

const TABS = {
    butiran: 'Butiran',
    pemilik: 'Pemilik & waris',
    sekatan: 'Sekatan',
    kronologi: 'Status & kronologi',
    dokumen: 'Dokumen',
    urusan: 'Urusan PT',
};

function nilai(v: unknown): string {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'boolean') return v ? 'Ya' : 'Tidak';
    if (Array.isArray(v)) return `${v.length} rekod`;
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}

function Butiran({ h }: { h: HakMilikPenuh }) {
    return (
        <div className="space-y-4">
            <InfoGrid
                items={[
                    ['No. hak milik', h.no_hak_milik],
                    ['Jenis hak milik', <span key="j" className="inline-flex items-center gap-2"><JenisChip kod={h.jenis_hak_milik} nama={h.jenis_hak_milik_nama} /> {h.jenis_hak_milik_nama}</span>],
                    ['No. lot', h.no_lot ? <Link key="l" href={h.pautan_lot} className="text-txt-primary hover:underline">{h.no_lot}</Link> : '—'],
                    ['Mukim', `${h.mukim} (${h.kod_mukim})`],
                    ['Daerah', `${h.daerah} (${h.kod_daerah})`],
                    ['Jenis tanah', <BahagianChip key="b" kod={h.bahagian_pemilik} />],
                    ['Kategori tanah', <KategoriChip key="k" kod={h.kategori_tanah} nama={h.kategori_tanah_nama} />],
                    ['Keluasan', keluasan(h)],
                    ['Pemilik berdaftar', h.pemilik_berdaftar],
                    ['Status semasa', h.status_semasa_nama],
                    ['Tarikh pendaftaran', tarikh(h.tarikh_pendaftaran)],
                    ['Tarikh tamat tempoh', tarikh(h.tarikh_tamat_tempoh)],
                    ['Tempoh pajakan', h.tempoh_pajakan_tahun ? `${h.tempoh_pajakan_tahun} tahun` : '—'],
                    ['Pejabat pendaftaran', h.pejabat_pendaftaran],
                    ['No. / tarikh perserahan', h.no_perserahan ? `${h.no_perserahan} · ${tarikh(h.tarikh_perserahan)}` : '—'],
                    ['Pengesahan', h.disahkan ? `Disahkan ${tarikhMasa(h.disahkan_pada)}` : 'Belum disahkan'],
                    ['Klasifikasi', <Badge key="c" variant="danger">{h.klasifikasi.toUpperCase()}</Badge>],
                    ['Versi rekod', String(h.versi_rekod)],
                ]}
            />
            <div>
                <p className="text-body-xs font-medium text-txt-black-500">Syarat nyata</p>
                <p className="mt-0.5 text-body-sm whitespace-pre-line">{h.syarat_nyata ?? '—'}</p>
            </div>
            {(h.digantikan || h.pengganti.length > 0) && (
                <Alert type="info">
                    {h.digantikan && (
                        <>
                            Menggantikan hak milik sementara <Link className="underline" href={`/admin/hkm/hak-milik/${h.digantikan.id}`}>{h.digantikan.no_hak_milik}</Link>.{' '}
                        </>
                    )}
                    {h.pengganti.map((p) => (
                        <span key={p.id}>
                            Diganti oleh <Link className="underline" href={`/admin/hkm/hak-milik/${p.id}`}>{p.no_hak_milik}</Link>.{' '}
                        </span>
                    ))}
                </Alert>
            )}
        </div>
    );
}

function Sejarah({ id }: { id: string }) {
    const { data, isLoading } = useQuery({ queryKey: ['hkm', 'sejarah', id], queryFn: () => hkmApi.sejarah(id) });
    if (isLoading || !data) return <Kosong>Memuatkan…</Kosong>;

    return (
        <div className="space-y-6">
            <section>
                <h4 className="font-semibold text-body-md mb-3">Kronologi pemilikan</h4>
                {data.kronologi.length === 0 ? (
                    <Kosong>Tiada rekod.</Kosong>
                ) : (
                    <Timeline
                        items={data.kronologi.map((k) => ({
                            title: `${k.susunan}. ${k.pemilik_sebelum ? `${k.pemilik_sebelum} → ` : ''}${k.pemilik_selepas}`,
                            time: k.tarikh_pertukaran ? tarikh(k.tarikh_pertukaran) : 'Tarikh tidak direkodkan',
                            description: `${k.sebab_pertukaran}${k.id_pemerolehan ? ` · ${k.id_pemerolehan}` : ''}${k.pegawai ? ` · ${k.pegawai}` : ''}`,
                            color: k.pemilik_sebelum ? 'primary' : 'success',
                        }))}
                    />
                )}
            </section>

            <section>
                <h4 className="font-semibold text-body-md mb-3">Sejarah status tanah</h4>
                <p className="text-body-xs text-txt-black-500 mb-2">Rekod hanya-tambah — tidak boleh dipinda atau dipadam.</p>
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Berkuat kuasa</th>
                                <th>Sebelum → selepas</th>
                                <th className="hidden md:table-cell">Sebab</th>
                                <th className="hidden lg:table-cell">Pegawai / pelulus</th>
                            </>
                        }
                    >
                        {data.status.map((s) => (
                            <tr key={s.id}>
                                <td className="whitespace-nowrap">{tarikh(s.tarikh_berkuat_kuasa)}</td>
                                <td>
                                    {s.status_sebelum_nama ?? '—'} → <strong>{s.status_selepas_nama}</strong>
                                    <p className="md:hidden text-body-xs text-txt-black-500">{s.sebab_perubahan}</p>
                                </td>
                                <td className="hidden md:table-cell">
                                    {s.sebab_perubahan}
                                    {s.no_rujukan_dokumen && <p className="text-body-xs text-txt-black-500">Ruj: {s.no_rujukan_dokumen}</p>}
                                </td>
                                <td className="hidden lg:table-cell">
                                    {s.pegawai ?? '—'}
                                    {s.pelulus && <p className="text-body-xs text-txt-black-500">Lulus: {s.pelulus}</p>}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </div>
            </section>

            <section>
                <h4 className="font-semibold text-body-md mb-3">Versi rekod (sebelum / selepas)</h4>
                <div className="space-y-3">
                    {data.versi.map((v) => (
                        <details key={v.versi} className="rounded-md border border-otl-gray-200 p-3">
                            <summary className="cursor-pointer text-body-sm">
                                <strong>Versi {v.versi}</strong> · {v.jenis_perubahan_nama} · {tarikhMasa(v.tarikh)}
                                {v.pengguna ? ` · ${v.pengguna}` : ''}
                                {v.pelulus ? ` · lulus: ${v.pelulus}` : ''}
                            </summary>
                            {v.justifikasi && <p className="mt-2 text-body-sm">Justifikasi: {v.justifikasi}</p>}
                            {v.data_sebelum && (
                                <div className="mt-2 overflow-x-auto">
                                    <Table head={<><th>Medan</th><th>Sebelum</th><th>Selepas</th></>}>
                                        {v.medan_berubah.map((m) => (
                                            <tr key={m}>
                                                <td>{LABEL_MEDAN[m] ?? m}</td>
                                                <td className="break-words max-w-[12rem]">{nilai(v.data_sebelum?.[m])}</td>
                                                <td className="break-words max-w-[12rem]">{nilai(v.data_selepas[m])}</td>
                                            </tr>
                                        ))}
                                    </Table>
                                </div>
                            )}
                        </details>
                    ))}
                </div>
            </section>
        </div>
    );
}

function UrusanForm({ id, onDone }: { id: string; onDone: () => void }) {
    const { data: ref } = useRujukan();
    const [v, setV] = useState<Record<string, string>>({ tarikh_perserahan: new Date().toISOString().slice(0, 10) });
    const save = useMutation({
        mutationFn: () => hkmApi.rekodUrusan(id, v),
        onSuccess: (r) => {
            toast.success(r.message);
            onDone();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <form
            className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end"
            onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
            }}
        >
            <Field label="Jenis urusan" htmlFor="u-jenis">
                <Select id="u-jenis" value={v.jenis_urusan ?? ''} onChange={(x) => setV((p) => ({ ...p, jenis_urusan: x }))} options={ref?.jenis_urusan ?? []} empty="Pilih…" required />
            </Field>
            <Field label="Pejabat pendaftaran" htmlFor="u-pej">
                <input id="u-pej" className={inputClass} value={v.pejabat_pendaftaran ?? ''} onChange={(e) => setV((p) => ({ ...p, pejabat_pendaftaran: e.target.value }))} required />
            </Field>
            <Field label="No. perserahan" htmlFor="u-no">
                <input id="u-no" className={inputClass} value={v.no_perserahan ?? ''} onChange={(e) => setV((p) => ({ ...p, no_perserahan: e.target.value }))} required />
            </Field>
            <Field label="Tarikh perserahan" htmlFor="u-tarikh">
                <input id="u-tarikh" type="date" className={inputClass} value={v.tarikh_perserahan ?? ''} onChange={(e) => setV((p) => ({ ...p, tarikh_perserahan: e.target.value }))} required />
            </Field>
            <div className="sm:col-span-2 lg:col-span-4 flex justify-end">
                <Button type="submit" size="sm" disabled={save.isPending}>
                    Rekod urusan
                </Button>
            </div>
        </form>
    );
}

function SimpanDokumen({ id, onDone }: { id: string; onDone: () => void }) {
    const { data: ref } = useRujukan();
    const [v, setV] = useState<Record<string, string>>({ jenis_dokumen_asal: 'dokumen_hak_milik_daftar' });
    const save = useMutation({
        mutationFn: () => hkmApi.simpan(id, { ...v, peti_besi_id: Number(v.peti_besi_id) }),
        onSuccess: (r) => {
            toast.success(r.message);
            onDone();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const set = (k: string) => (x: string) => setV((p) => ({ ...p, [k]: x }));
    return (
        <form
            className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 items-end rounded-md border border-otl-gray-200 p-3"
            onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
            }}
        >
            <Field label="Peti besi" htmlFor="sd-pb">
                <Select id="sd-pb" value={v.peti_besi_id ?? ''} onChange={set('peti_besi_id')} options={(ref?.peti_besi ?? []).map((p) => ({ kod: String(p.id), nama: `${p.kod_peti_besi} — ${p.nama}` }))} empty="Pilih…" required />
            </Field>
            <Field label="Jenis dokumen asal" htmlFor="sd-j">
                <Select id="sd-j" value={v.jenis_dokumen_asal} onChange={set('jenis_dokumen_asal')} options={ref?.jenis_dokumen_asal ?? []} />
            </Field>
            {(
                [
                    ['lokasi_rak', 'Lokasi rak'],
                    ['no_fail', 'No. fail'],
                    ['no_kotak_pengikat', 'No. kotak / pengikat'],
                    ['catatan_lokasi', 'Catatan lokasi'],
                ] as const
            ).map(([k, label]) => (
                <Field key={k} label={label} htmlFor={`sd-${k}`}>
                    <input id={`sd-${k}`} className={inputClass} value={v[k] ?? ''} onChange={(e) => set(k)(e.target.value)} />
                </Field>
            ))}
            <div className="flex justify-end">
                <Button type="submit" size="sm" disabled={save.isPending}>
                    Terima ke dalam simpanan
                </Button>
            </div>
        </form>
    );
}

function HmkModal({ h, open, onClose }: { h: HakMilikPenuh; open: boolean; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [v, setV] = useState({ tarikh_permohonan: new Date().toISOString().slice(0, 10), pejabat_tanah: h.pejabat_pendaftaran ?? '', no_permohonan: '' });
    const save = useMutation({
        mutationFn: () => hkmApi.mohonHmk(h.id, { ...v, no_permohonan: v.no_permohonan || undefined }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['hkm'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <Modal open={open} onClose={onClose}>
            <ModalBody
                title="Daftar permohonan hak milik kekal"
                footer={
                    <>
                        <Button variant="outline" onClick={onClose}>
                            Batal
                        </Button>
                        <Button onClick={() => save.mutate()} disabled={save.isPending}>
                            Daftar
                        </Button>
                    </>
                }
            >
                <Field label="Tarikh permohonan" htmlFor="h-t">
                    <input id="h-t" type="date" className={inputClass} value={v.tarikh_permohonan} onChange={(e) => setV({ ...v, tarikh_permohonan: e.target.value })} />
                </Field>
                <Field label="Pejabat tanah" htmlFor="h-p">
                    <input id="h-p" className={inputClass} value={v.pejabat_tanah} onChange={(e) => setV({ ...v, pejabat_tanah: e.target.value })} />
                </Field>
                <Field label="No. permohonan (kosongkan untuk dijana)" htmlFor="h-n">
                    <input id="h-n" className={inputClass} value={v.no_permohonan} onChange={(e) => setV({ ...v, no_permohonan: e.target.value })} />
                </Field>
            </ModalBody>
        </Modal>
    );
}

function Butiran360() {
    const { id } = useParams<{ id: string }>();
    const params = useSearchParams();
    const can = useCan();
    const queryClient = useQueryClient();
    const [ubah, setUbah] = useState<PerubahanAwal | null>(null);
    const [hmk, setHmk] = useState(false);
    const [urusanOpen, setUrusanOpen] = useState(false);
    const { data: h, isLoading, error } = useQuery({ queryKey: ['hkm', 'hak-milik', id], queryFn: () => hkmApi.hakMilik(id) });

    const sahkan = useMutation({
        mutationFn: () => hkmApi.sahkan(id),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['hkm'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });

    if (isLoading) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    if (error || !h) return <PageContainer><Alert type="error">{error ? ralat(error) : 'Hak milik tidak ditemui.'}</Alert></PageContainer>;

    const boleh = can('hkm.hak_milik.kemas_kini') && !h.perubahan_menunggu;
    const tabAwal = params.get('tab') ?? undefined;

    return (
        <PageContainer>
            <Card
                title={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <JenisChip kod={h.jenis_hak_milik} nama={h.jenis_hak_milik_nama} /> {h.no_hak_milik}
                        {h.sintetik && <Badge variant="neutral">Data sintetik</Badge>}
                    </span>
                }
                actions={
                    <>
                        {can('hkm.pengesahan.lulus') && !h.disahkan && (
                            <Button size="sm" variant="secondary" onClick={() => sahkan.mutate()} disabled={sahkan.isPending}>
                                Sahkan hak milik
                            </Button>
                        )}
                        {boleh && (
                            <Button size="sm" onClick={() => setUbah({ jenis: 'butiran' })}>
                                Mohon pindaan
                            </Button>
                        )}
                        {can('hkm.permohonan.cipta') && h.sementara && h.status_semasa !== 'diganti' && (
                            <Button size="sm" variant="outline" onClick={() => setHmk(true)}>
                                Mohon hak milik kekal
                            </Button>
                        )}
                    </>
                }
            >
                <BenderaList bendera={h.bendera} />
                {h.perubahan_menunggu && (
                    <Alert type="warning">
                        Terdapat permohonan perubahan yang sedang menunggu kelulusan.{' '}
                        <Link className="underline" href={`/admin/hkm/perubahan/${h.perubahan_menunggu}`}>
                            Lihat permohonan
                        </Link>
                    </Alert>
                )}

                <Tabs tabs={TABS} defaultTab={tabAwal && tabAwal in TABS ? tabAwal : undefined}>
                    <TabPanel value="butiran">
                        <Butiran h={h} />
                        {boleh && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" onClick={() => setUbah({ jenis: 'status' })}>
                                    Mohon perubahan status tanah
                                </Button>
                            </div>
                        )}
                    </TabPanel>

                    <TabPanel value="pemilik">
                        <div className="space-y-4">
                            <InfoGrid items={[['Pemilik berdaftar', h.pemilik_berdaftar], ['Pemilikan', h.pemilikan_belum_selesai ? 'Belum selesai' : 'Selesai']]} />
                            {boleh && (
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" variant="outline" onClick={() => setUbah({ jenis: 'pemilik' })}>
                                        Mohon pindah milik
                                    </Button>
                                    <Button size="sm" variant="outline" onClick={() => setUbah({ jenis: 'waris', data: { tindakan: 'tambah' } })}>
                                        Tambah waris / pemilik bersama
                                    </Button>
                                </div>
                            )}
                            {h.waris.length === 0 ? (
                                <Kosong>Tiada waris atau pemilik bersama direkodkan.</Kosong>
                            ) : (
                                <div className="overflow-x-auto">
                                    <Table
                                        head={
                                            <>
                                                <th>Nama</th>
                                                <th className="hidden sm:table-cell">Hubungan</th>
                                                <th>Bahagian</th>
                                                <th className="hidden md:table-cell">Pengesahan</th>
                                                <th />
                                            </>
                                        }
                                    >
                                        {h.waris.map((w) => (
                                            <tr key={w.id}>
                                                <td>
                                                    {w.nama}
                                                    {w.no_pengenalan && <p className="text-body-xs text-txt-black-500">{w.no_pengenalan}</p>}
                                                    <p className="sm:hidden text-body-xs text-txt-black-500">{w.hubungan_nama}</p>
                                                </td>
                                                <td className="hidden sm:table-cell">{w.hubungan_nama}</td>
                                                <td>{w.pecahan ?? '—'}</td>
                                                <td className="hidden md:table-cell">
                                                    <Badge variant={w.status_pengesahan === 'disahkan' ? 'success' : 'warning'}>{w.status_pengesahan_nama}</Badge>
                                                    <p className="text-body-xs text-txt-black-500">
                                                        {w.sumber_pengesahan_nama ?? ''}
                                                        {w.no_kes_faraid ? ` · ${w.no_kes_faraid}` : ''}
                                                    </p>
                                                </td>
                                                <td className="text-right">
                                                    {boleh && (
                                                        <Button size="sm" variant="outline" onClick={() => setUbah({ jenis: 'waris', data: { tindakan: 'kemas_kini', id_waris: String(w.id) } })}>
                                                            Ubah
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </Table>
                                </div>
                            )}
                        </div>
                    </TabPanel>

                    <TabPanel value="sekatan">
                        <div className="space-y-4">
                            {boleh && (
                                <Button size="sm" variant="outline" onClick={() => setUbah({ jenis: 'sekatan', data: { tindakan: 'tambah' } })}>
                                    Rekod sekatan / bebanan
                                </Button>
                            )}
                            {h.sekatan.length === 0 ? (
                                <Kosong>Tiada sekatan kepentingan atau bebanan.</Kosong>
                            ) : (
                                <div className="overflow-x-auto">
                                    <Table
                                        head={
                                            <>
                                                <th>Jenis</th>
                                                <th className="hidden sm:table-cell">Rujukan / pihak</th>
                                                <th className="hidden md:table-cell">Tarikh</th>
                                                <th>Status</th>
                                                <th />
                                            </>
                                        }
                                    >
                                        {h.sekatan.map((s) => (
                                            <tr key={s.id}>
                                                <td>
                                                    {s.jenis_sekatan_nama}
                                                    {s.keterangan && <p className="text-body-xs text-txt-black-500 max-w-xs break-words">{s.keterangan}</p>}
                                                </td>
                                                <td className="hidden sm:table-cell">
                                                    {s.no_rujukan_sekatan ?? '—'}
                                                    <p className="text-body-xs text-txt-black-500">{s.pihak_terlibat}</p>
                                                </td>
                                                <td className="hidden md:table-cell whitespace-nowrap">
                                                    {tarikh(s.tarikh_masuk)}
                                                    {s.tarikh_lupus && <p className="text-body-xs text-txt-black-500">Lupus: {tarikh(s.tarikh_lupus)}</p>}
                                                </td>
                                                <td>
                                                    <Badge variant={s.status_sekatan === 'aktif' ? 'warning' : 'neutral'}>{s.status_sekatan_nama}</Badge>
                                                </td>
                                                <td className="text-right">
                                                    {boleh && s.status_sekatan === 'aktif' && (
                                                        <Button size="sm" variant="outline" onClick={() => setUbah({ jenis: 'sekatan', data: { tindakan: 'lupus', id_sekatan: String(s.id) } })}>
                                                            Lupus
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </Table>
                                </div>
                            )}
                        </div>
                    </TabPanel>

                    <TabPanel value="kronologi">
                        <Sejarah id={h.id} />
                    </TabPanel>

                    <TabPanel value="dokumen">
                        <div className="space-y-4">
                            <section className="space-y-2">
                                <h4 className="font-semibold text-body-md">Dokumen asal (peti besi)</h4>
                                {h.dokumen_asal === null ? (
                                    <p className="text-body-sm text-txt-black-500">Anda tiada kebenaran melihat lokasi dokumen asal.</p>
                                ) : h.dokumen_asal.length === 0 ? (
                                    <Kosong>Dokumen asal belum didaftarkan dalam peti besi.</Kosong>
                                ) : (
                                    <InfoGrid
                                        items={h.dokumen_asal.flatMap((d) => [
                                            [`${d.jenis_dokumen_asal_nama} — peti besi`, `${d.kod_peti_besi} (${d.lokasi_peti_besi})`],
                                            ['Rak / fail / kotak', [d.lokasi_rak, d.no_fail, d.no_kotak_pengikat].filter(Boolean).join(' · ') || '—'],
                                            ['Status dokumen asal', <StatusDokumen key={d.id} kod={d.status_dokumen_asal} nama={d.status_dokumen_asal_nama} />],
                                        ] as Array<[string, ReactNode]>)}
                                    />
                                )}
                                {can('hkm.peti_besi.cipta') && (
                                    <SimpanDokumen id={h.id} onDone={() => queryClient.invalidateQueries({ queryKey: ['hkm', 'hak-milik', id] })} />
                                )}
                                {h.dokumen_asal && can('hkm.peti_besi.lihat') && (
                                    <LinkButton size="sm" variant="outline" href={`/admin/hkm/peti-besi?q=${encodeURIComponent(h.no_hak_milik)}`}>
                                        Buka daftar peti besi
                                    </LinkButton>
                                )}
                            </section>
                            <DocumentAttachments ownerType="hak_milik" ownerId={h.id} sourceModule="hkm" defaultCategory="geran" ownerLink={`/admin/hkm/hak-milik/${h.id}`} title="Dokumen sokongan (DMS)" />
                        </div>
                    </TabPanel>

                    <TabPanel value="urusan">
                        <div className="space-y-4">
                            {can('hkm.hak_milik.kemas_kini') && (
                                <div className="space-y-3">
                                    <Button size="sm" variant="outline" onClick={() => setUrusanOpen((o) => !o)} aria-expanded={urusanOpen}>
                                        Rekod urusan Pejabat Tanah
                                    </Button>
                                    {urusanOpen && (
                                        <UrusanForm
                                            id={h.id}
                                            onDone={() => {
                                                setUrusanOpen(false);
                                                queryClient.invalidateQueries({ queryKey: ['hkm', 'hak-milik', id] });
                                            }}
                                        />
                                    )}
                                </div>
                            )}
                            {h.urusan.length === 0 ? (
                                <Kosong>Tiada urusan Pejabat Tanah direkodkan.</Kosong>
                            ) : (
                                <div className="overflow-x-auto">
                                    <Table head={<><th>Urusan</th><th className="hidden sm:table-cell">Perserahan</th><th>Status</th><th /></>}>
                                        {h.urusan.map((u) => (
                                            <tr key={u.id}>
                                                <td>
                                                    {u.jenis_urusan_nama}
                                                    <p className="text-body-xs text-txt-black-500">{u.pejabat_pendaftaran}</p>
                                                </td>
                                                <td className="hidden sm:table-cell">
                                                    {u.no_perserahan}
                                                    <p className="text-body-xs text-txt-black-500">
                                                        {tarikh(u.tarikh_perserahan)} · {u.tempoh_hari} hari
                                                    </p>
                                                </td>
                                                <td>
                                                    <Badge variant={u.status_urusan === 'didaftarkan' ? 'success' : u.status_urusan === 'ditolak' ? 'danger' : 'warning'}>{u.status_urusan_nama}</Badge>
                                                </td>
                                                <td className="text-right">
                                                    {can('hkm.hak_milik.kemas_kini') && !u.tarikh_selesai && (
                                                        <UrusanStatus id={u.id} onDone={() => queryClient.invalidateQueries({ queryKey: ['hkm', 'hak-milik', id] })} />
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </Table>
                                </div>
                            )}
                            {h.permohonan_hmk.length > 0 && (
                                <section className="space-y-2">
                                    <h4 className="font-semibold text-body-md">Permohonan hak milik kekal</h4>
                                    {h.permohonan_hmk.map((p) => (
                                        <p key={p.id} className="text-body-sm">
                                            <Link className="text-txt-primary hover:underline" href={`/admin/hkm/permohonan/${p.id}`}>
                                                {p.no_permohonan}
                                            </Link>{' '}
                                            · {p.peringkat_semasa_nama} · {p.status_kelulusan_nama}
                                        </p>
                                    ))}
                                </section>
                            )}
                        </div>
                    </TabPanel>
                </Tabs>
            </Card>

            {ubah && <PerubahanModal key={`${ubah.jenis}-${JSON.stringify(ubah.data ?? {})}`} title={h} awal={ubah} onClose={() => setUbah(null)} />}
            {hmk && <HmkModal h={h} open={hmk} onClose={() => setHmk(false)} />}
        </PageContainer>
    );
}

function UrusanStatus({ id, onDone }: { id: number; onDone: () => void }) {
    const { data: ref } = useRujukan();
    const save = useMutation({
        mutationFn: (status: string) => hkmApi.kemasKiniUrusan(id, { status_urusan: status }),
        onSuccess: (r) => {
            toast.success(r.message);
            onDone();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <>
            <label htmlFor={`us-${id}`} className="sr-only">
                Kemas kini status urusan
            </label>
            <select id={`us-${id}`} className={`${inputClass} w-auto`} defaultValue="" onChange={(e) => e.target.value && save.mutate(e.target.value)}>
                <option value="">Kemas kini…</option>
                {(ref?.status_urusan ?? []).map((s) => (
                    <option key={s.kod} value={s.kod}>
                        {s.nama}
                    </option>
                ))}
            </select>
        </>
    );
}

export default function HakMilikDetailPage() {
    return (
        <Suspense>
            <Butiran360 />
        </Suspense>
    );
}
