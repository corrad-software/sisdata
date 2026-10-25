'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { ASAL_LABEL, JENIS_DOKUMEN, jenisLabel, kbaApi, KLASIFIKASI_LABEL, STATUS_INDEKS_LABEL, tarikhMasa, useCan, type Klasifikasi, type Pemetaan, type Sumber } from '@/lib/kba';
import { Card, Field, inputClass, KlasifikasiBadge, Kosong, Memuat, ralat, RalatAkses, Select } from '../_components/common';

const KLAS_OPTIONS = (Object.keys(KLASIFIKASI_LABEL) as Klasifikasi[]).map((k) => ({ value: k, label: KLASIFIKASI_LABEL[k] }));

function statusVariant(s: string): 'success' | 'danger' | 'warning' | 'neutral' {
    return s === 'selesai' ? 'success' : s === 'gagal' ? 'danger' : s === 'tiada_teks' ? 'warning' : 'neutral';
}

function MuatNaik({ onDone }: { onDone: (m: string) => void }) {
    const [fail, setFail] = useState<File | null>(null);
    const [tajuk, setTajuk] = useState('');
    const [jenis, setJenis] = useState('manual_pengguna');
    const [klas, setKlas] = useState<Klasifikasi>('dalaman');
    const [versi, setVersi] = useState('');
    const [modul, setModul] = useState('');
    const [peranan, setPeranan] = useState('');
    const [err, setErr] = useState<string | null>(null);
    const m = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            if (fail) fd.append('fail', fail);
            fd.append('tajuk', tajuk);
            fd.append('jenis_dokumen', jenis);
            fd.append('klasifikasi', klas);
            if (versi) fd.append('versi', versi);
            if (modul) fd.append('modul', modul);
            peranan
                .split(',')
                .map((p) => p.trim())
                .filter(Boolean)
                .forEach((p) => fd.append('senarai_peranan_dibenarkan[]', p));
            return kbaApi.muatNaik(fd);
        },
        onSuccess: (r) => {
            setErr(null);
            setFail(null);
            setTajuk('');
            onDone(r.message);
        },
        onError: (e) => setErr(ralat(e)),
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        m.mutate();
    };
    return (
        <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field label="Fail (Markdown, teks, PDF, DOCX)" htmlFor="s-fail" help="PDF/DOCX asal disimpan dalam DMS (antivirus, penyulitan).">
                <input id="s-fail" type="file" accept=".md,.markdown,.txt,.pdf,.docx" className="block w-full text-body-sm" onChange={(e) => setFail(e.target.files?.[0] ?? null)} required />
            </Field>
            <Field label="Tajuk" htmlFor="s-tajuk">
                <input id="s-tajuk" className={inputClass} value={tajuk} onChange={(e) => setTajuk(e.target.value)} required maxLength={250} />
            </Field>
            <Field label="Jenis dokumen" htmlFor="s-jenis">
                <Select id="s-jenis" value={jenis} onChange={setJenis} options={JENIS_DOKUMEN} />
            </Field>
            <Field label="Klasifikasi" htmlFor="s-klas">
                <Select id="s-klas" value={klas} onChange={(v) => setKlas(v as Klasifikasi)} options={KLAS_OPTIONS} />
            </Field>
            <Field label="Versi (pilihan)" htmlFor="s-versi">
                <input id="s-versi" className={inputClass} value={versi} onChange={(e) => setVersi(e.target.value)} maxLength={40} placeholder="cth. 1.0" />
            </Field>
            <Field label="Modul (pilihan)" htmlFor="s-modul">
                <input id="s-modul" className={inputClass} value={modul} onChange={(e) => setModul(e.target.value)} maxLength={3} placeholder="cth. dms" />
            </Field>
            <Field label="Peranan dibenarkan (pilihan)" htmlFor="s-peranan" help="Kod peranan dipisah koma. Kosong = semua peranan yang layak mengikut klasifikasi." className="sm:col-span-2">
                <input id="s-peranan" className={inputClass} value={peranan} onChange={(e) => setPeranan(e.target.value)} placeholder="cth. pentadbir_sistem, pentadbir_aplikasi" />
            </Field>
            <div className="flex items-end">
                <Button type="submit" disabled={m.isPending || !fail}>
                    {m.isPending ? 'Memuat naik…' : 'Daftar & indeks'}
                </Button>
            </div>
            {err && (
                <div className="sm:col-span-2 lg:col-span-3">
                    <Alert type="error">{err}</Alert>
                </div>
            )}
        </form>
    );
}

function DaftarDms({ onDone }: { onDone: (m: string) => void }) {
    const [id, setId] = useState('');
    const [jenis, setJenis] = useState('dokumen_dms');
    const [err, setErr] = useState<string | null>(null);
    const m = useMutation({
        mutationFn: () => kbaApi.daftarDms({ id_dokumen_dms: id.trim(), jenis_dokumen: jenis }),
        onSuccess: (r) => {
            setId('');
            setErr(null);
            onDone(r.message);
        },
        onError: (e) => setErr(ralat(e)),
    });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                m.mutate();
            }}
            className="grid grid-cols-1 gap-4 sm:grid-cols-3"
        >
            <Field label="ID dokumen DMS" htmlFor="d-id" help="Teks diambil daripada pengekstrakan DMS sendiri; klasifikasi & capaian ikut DMS." className="sm:col-span-2">
                <input id="d-id" className={inputClass} value={id} onChange={(e) => setId(e.target.value)} required placeholder="UUID dokumen" />
            </Field>
            <Field label="Jenis dokumen" htmlFor="d-jenis">
                <Select id="d-jenis" value={jenis} onChange={setJenis} options={JENIS_DOKUMEN} />
            </Field>
            <div>
                <Button type="submit" disabled={m.isPending || id.trim().length < 36}>
                    Daftar dokumen DMS
                </Button>
            </div>
            {err && (
                <div className="sm:col-span-3">
                    <Alert type="error">{err}</Alert>
                </div>
            )}
        </form>
    );
}

function BarisSumber({ s, boleh, onMsg }: { s: Sumber; boleh: boolean; onMsg: (m: string) => void }) {
    const qc = useQueryClient();
    const [edit, setEdit] = useState(false);
    const [klas, setKlas] = useState<Klasifikasi>(s.klasifikasi);
    const [peranan, setPeranan] = useState(s.senarai_peranan_dibenarkan.join(', '));
    const refresh = () => qc.invalidateQueries({ queryKey: ['kba', 'sumber'] });
    const reindex = useMutation({ mutationFn: () => kbaApi.indeksSemula(s.id), onSuccess: (r) => (onMsg(r.message), refresh()), onError: (e) => onMsg(ralat(e)) });
    const simpan = useMutation({
        mutationFn: (body: Parameters<typeof kbaApi.kemasKiniSumber>[1]) => kbaApi.kemasKiniSumber(s.id, body),
        onSuccess: (r) => {
            onMsg(r.message);
            setEdit(false);
            refresh();
        },
        onError: (e) => onMsg(ralat(e)),
    });
    return (
        <tr>
            <td className="min-w-[14rem]">
                <Link href={s.pautan} className="font-medium text-txt-primary hover:underline break-words">
                    {s.tajuk}
                </Link>
                <div className="text-body-xs text-txt-black-500">
                    {ASAL_LABEL[s.asal]} · {jenisLabel(s.jenis_dokumen)}
                    {s.modul ? ` · ${s.modul.toUpperCase()}` : ''}
                    {s.laluan_fail ? ` · ${s.laluan_fail}` : ''}
                </div>
                {s.mesej_indeks && <div className="text-body-xs text-txt-warning">{s.mesej_indeks}</div>}
            </td>
            <td>{s.versi}</td>
            <td>
                {edit ? (
                    <div className="space-y-2 min-w-[12rem]">
                        <Select id={`k-${s.id}`} value={klas} onChange={(v) => setKlas(v as Klasifikasi)} options={KLAS_OPTIONS} />
                        <input aria-label="Peranan dibenarkan" className={inputClass} value={peranan} onChange={(e) => setPeranan(e.target.value)} placeholder="peranan, dipisah koma" />
                    </div>
                ) : (
                    <div className="space-y-1">
                        <KlasifikasiBadge k={s.klasifikasi} />
                        {s.senarai_peranan_dibenarkan.length > 0 && <div className="text-body-xs text-txt-black-500">{s.senarai_peranan_dibenarkan.join(', ')}</div>}
                    </div>
                )}
            </td>
            <td>
                <Badge variant={statusVariant(s.status_indeks)}>{STATUS_INDEKS_LABEL[s.status_indeks] ?? s.status_indeks}</Badge>
                {!s.aktif && <div className="text-body-xs text-txt-black-500">Tidak aktif</div>}
            </td>
            <td className="text-right">{s.bilangan_segmen}</td>
            <td className="whitespace-nowrap text-body-xs">{tarikhMasa(s.tarikh_indeks_akhir)}</td>
            <td className="whitespace-nowrap">
                {boleh && s.asal !== 'artikel' && (
                    <div className="flex flex-wrap gap-1">
                        {edit ? (
                            <>
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        simpan.mutate({
                                            ...(s.asal !== 'dms' ? { klasifikasi: klas } : {}),
                                            senarai_peranan_dibenarkan: peranan
                                                .split(',')
                                                .map((p) => p.trim())
                                                .filter(Boolean),
                                        })
                                    }
                                >
                                    Simpan
                                </Button>
                                <Button size="sm" variant="secondary" onClick={() => setEdit(false)}>
                                    Batal
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button size="sm" variant="secondary" onClick={() => setEdit(true)}>
                                    Ubah capaian
                                </Button>
                                <Button size="sm" variant="secondary" onClick={() => reindex.mutate()} disabled={reindex.isPending}>
                                    Indeks semula
                                </Button>
                                <Button size="sm" variant="secondary" onClick={() => simpan.mutate({ aktif: !s.aktif })}>
                                    {s.aktif ? 'Nyahaktif' : 'Aktifkan'}
                                </Button>
                            </>
                        )}
                    </div>
                )}
            </td>
        </tr>
    );
}

function PemetaanEditor({ boleh }: { boleh: boolean }) {
    const qc = useQueryClient();
    const { data } = useQuery({ queryKey: ['kba', 'pemetaan'], queryFn: () => kbaApi.pemetaan().then((r) => r.data) });
    const [rows, setRows] = useState<Pemetaan[] | null>(null);
    const [msg, setMsg] = useState<string | null>(null);
    const list = rows ?? data ?? [];
    const simpan = useMutation({
        mutationFn: () => kbaApi.simpanPemetaan(list),
        onSuccess: () => {
            setRows(null);
            setMsg('Pemetaan disimpan.');
            qc.invalidateQueries({ queryKey: ['kba', 'pemetaan'] });
        },
        onError: (e) => setMsg(ralat(e)),
    });
    const set = (i: number, patch: Partial<Pemetaan>) => setRows(list.map((r, j) => (j === i ? { ...r, ...patch } : r)));
    return (
        <div className="space-y-3">
            <p className="text-body-sm text-txt-black-700">
                Peranan yang dipetakan kepada klasifikasi <strong>Sulit</strong> atau <strong>Rahsia</strong> (capaian &quot;baca&quot;) boleh mencapai segmen berklasifikasi itu mulai tarikh berkuat
                kuasa — selain pengguna yang memegang kebenaran kba.sulit.lihat / kba.rahsia.lihat. Tapisan dikenakan semasa capaian, sebelum konteks dihantar kepada model.
            </p>
            <Table
                head={
                    <>
                        <th>Klasifikasi</th>
                        <th>Kod peranan</th>
                        <th>Capaian</th>
                        <th>Berkuat kuasa</th>
                        <th />
                    </>
                }
            >
                {list.map((r, i) => (
                    <tr key={i}>
                        <td>
                            <Select id={`p-k-${i}`} value={r.klasifikasi} onChange={(v) => set(i, { klasifikasi: v as Pemetaan['klasifikasi'] })} options={KLAS_OPTIONS.filter((o) => o.value !== 'awam')} />
                        </td>
                        <td>
                            <input aria-label="Kod peranan" className={inputClass} value={r.peranan} onChange={(e) => set(i, { peranan: e.target.value })} disabled={!boleh} />
                        </td>
                        <td>
                            <Select
                                id={`p-c-${i}`}
                                value={r.jenis_capaian}
                                onChange={(v) => set(i, { jenis_capaian: v as Pemetaan['jenis_capaian'] })}
                                options={[
                                    { value: 'baca', label: 'Baca' },
                                    { value: 'semak', label: 'Semak' },
                                    { value: 'terbit', label: 'Terbit' },
                                ]}
                            />
                        </td>
                        <td>
                            <input aria-label="Tarikh berkuat kuasa" type="date" className={inputClass} value={r.tarikh_berkuat_kuasa.slice(0, 10)} onChange={(e) => set(i, { tarikh_berkuat_kuasa: e.target.value })} disabled={!boleh} />
                        </td>
                        <td>
                            {boleh && (
                                <Button size="sm" variant="secondary" onClick={() => setRows(list.filter((_, j) => j !== i))}>
                                    Buang
                                </Button>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
            {boleh && (
                <div className="flex flex-wrap gap-2">
                    <Button variant="secondary" onClick={() => setRows([...list, { klasifikasi: 'sulit', peranan: '', jenis_capaian: 'baca', tarikh_berkuat_kuasa: new Date().toISOString().slice(0, 10) }])}>
                        Tambah baris
                    </Button>
                    <Button onClick={() => simpan.mutate()} disabled={simpan.isPending || rows === null}>
                        Simpan pemetaan
                    </Button>
                </div>
            )}
            {msg && <p className="text-body-sm text-txt-black-700">{msg}</p>}
        </div>
    );
}

/** SRS-KBA-01/02/06/08: sources, index coverage & status, upload, re-index, classification–role mapping. */
export default function SumberPage() {
    const can = useCan();
    const qc = useQueryClient();
    const boleh = can('kba.sumber.kemas_kini');
    const [msg, setMsg] = useState<string | null>(null);
    const [asal, setAsal] = useState('');
    const { data, error, isLoading } = useQuery({ queryKey: ['kba', 'sumber', asal], queryFn: () => kbaApi.sumber({ asal }).then((r) => r.data) });
    const { data: st } = useQuery({ queryKey: ['kba', 'sumber', 'status'], queryFn: () => kbaApi.statusIndeks().then((r) => r.data) });
    const semua = useMutation({ mutationFn: () => kbaApi.indeksSemuaSemula(), onSuccess: (r) => (setMsg(r.message), qc.invalidateQueries({ queryKey: ['kba', 'sumber'] })), onError: (e) => setMsg(ralat(e)) });
    const done = (m: string) => {
        setMsg(m);
        qc.invalidateQueries({ queryKey: ['kba', 'sumber'] });
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
            {st && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Sumber aktif" value={String(st.sumber_aktif)} change={`${st.sumber.gagal ?? 0} gagal`} />
                    <StatCard label="Segmen aktif" value={String(st.segmen_aktif)} change={`${st.segmen_lapuk} lapuk`} />
                    <StatCard label={`Berembedding (${st.model_embedding})`} value={String(st.segmen_berembedding)} change={`${st.segmen_mengandungi_arahan} berarahan terbenam`} />
                    <StatCard label="Mod jawapan" value={st.mod_jawapan === 'generatif' ? 'Generatif' : 'Ekstraktif'} change={st.model_llm ? `${st.model_llm}${st.llm_tersedia ? '' : ' (tiada)'}` : 'tiada model'} />
                </div>
            )}
            {msg && (
                <Alert type="info" dismissible>
                    {msg}
                </Alert>
            )}
            <Card
                title="Sumber diindeks (liputan pengindeksan)"
                actions={
                    <>
                        <div className="w-44">
                            <Select id="f-asal" value={asal} onChange={setAsal} empty="Semua asal" options={Object.entries(ASAL_LABEL).map(([value, label]) => ({ value, label }))} />
                        </div>
                        {boleh && (
                            <Button variant="secondary" onClick={() => semua.mutate()} disabled={semua.isPending}>
                                Indeks semula semua
                            </Button>
                        )}
                    </>
                }
            >
                {isLoading || !data ? (
                    <Memuat />
                ) : data.length === 0 ? (
                    <Kosong>Belum ada sumber. Jalankan `php artisan kba:index-baseline` atau muat naik manual.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>Sumber</th>
                                <th>Versi</th>
                                <th>Klasifikasi / peranan</th>
                                <th>Status indeks</th>
                                <th className="text-right">Segmen</th>
                                <th>Diindeks</th>
                                <th />
                            </>
                        }
                    >
                        {data.map((s) => (
                            <BarisSumber key={s.id} s={s} boleh={boleh} onMsg={setMsg} />
                        ))}
                    </Table>
                )}
            </Card>
            {boleh && (
                <>
                    <Card title="Muat naik manual / SOP / nota keluaran">
                        <MuatNaik onDone={done} />
                    </Card>
                    <Card title="Daftar dokumen daripada DMS">
                        <DaftarDms onDone={done} />
                    </Card>
                </>
            )}
            <Card title="Pemetaan klasifikasi – peranan">
                <PemetaanEditor boleh={can('kba.pemetaan.kemas_kini')} />
            </Card>
        </PageContainer>
    );
}
