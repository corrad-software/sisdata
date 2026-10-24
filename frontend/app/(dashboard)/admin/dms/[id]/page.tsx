'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Table } from '@/components/ui/Table';
import { InputLabel } from '@/components/ui/InputLabel';
import { Textarea } from '@/components/ui/Textarea';
import { DocumentPreview } from '@/components/dms/DocumentPreview';
import { ExtraField } from '@/components/dms/UploadDialog';
import { ApiError } from '@/lib/api';
import {
    dmsApi,
    KLASIFIKASI_LABEL,
    muatNaikFail,
    saizMesra,
    STATUS_LABEL,
    tarikhMesra,
    type DokumenPenuh,
    type Klasifikasi,
    type PerbandinganVersi,
} from '@/lib/dms';

const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-1 py-2 border-b border-otl-divider last:border-0">
            <dt className="text-body-sm text-txt-black-500">{label}</dt>
            <dd className="sm:col-span-2 text-body-sm text-txt-black-900 break-words">{children}</dd>
        </div>
    );
}

export default function DokumenDetailPage() {
    const { id } = useParams<{ id: string }>();
    const router = useRouter();
    const queryClient = useQueryClient();
    const { data, isLoading, error } = useQuery({ queryKey: ['dms', 'dokumen', id], queryFn: () => dmsApi.dokumen(id) });
    const doc = data?.data;

    const [editOpen, setEditOpen] = useState(false);
    const [lupusOpen, setLupusOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const refresh = () => {
        queryClient.invalidateQueries({ queryKey: ['dms', 'dokumen', id] });
        queryClient.invalidateQueries({ queryKey: ['dms', 'versi', id] });
    };

    async function openLink(jenis: 'muat_turun' | 'cetak', versi?: number) {
        try {
            const { data: link } = await dmsApi.pautan(id, jenis, versi);
            if (jenis === 'muat_turun') window.location.assign(link.url);
            else window.open(link.url, '_blank', 'noopener');
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Tindakan gagal.');
        }
    }

    const archive = useMutation({
        mutationFn: () => dmsApi.arkib(id),
        onSuccess: (r) => {
            toast.success(r.message);
            refresh();
        },
        onError: (e: Error) => toast.error(e.message),
    });

    const remove = useMutation({
        mutationFn: () => dmsApi.padam(id),
        onSuccess: (r) => {
            toast.success(r.message);
            router.push('/admin/dms');
        },
        onError: (e: Error) => toast.error(e.message),
    });

    if (isLoading) {
        return (
            <PageContainer>
                <Spinner />
            </PageContainer>
        );
    }
    if (error || !doc) {
        return (
            <>
                <PageHeader title="Dokumen" breadcrumbs={[{ label: 'Dokumen Digital', href: '/admin/dms' }, { label: 'Tidak ditemui' }]} />
                <PageContainer>
                    <Alert type="error">{error instanceof ApiError && error.status === 404 ? 'Dokumen tidak ditemui atau anda tiada akses.' : error?.message}</Alert>
                </PageContainer>
            </>
        );
    }

    const tabs: Record<string, string> = { pratonton: 'Pratonton', metadata: 'Metadata', versi: 'Versi' };
    if (doc.boleh.urus_kebenaran) tabs.kebenaran = 'Kebenaran';
    tabs.audit = 'Jejak audit';

    return (
        <>
            <PageHeader title={doc.tajuk} breadcrumbs={[{ label: 'Dokumen Digital', href: '/admin/dms' }, { label: doc.no_rujukan }]} />

            <PageContainer>
                <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                    <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div className="space-y-2 min-w-0">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant={doc.klasifikasi === 'rahsia' ? 'danger' : doc.klasifikasi === 'sulit' ? 'warning' : 'info'}>
                                    {KLASIFIKASI_LABEL[doc.klasifikasi]}
                                </Badge>
                                <Badge variant={doc.status === 'aktif' ? 'success' : 'neutral'}>{STATUS_LABEL[doc.status]}</Badge>
                                <Badge variant="neutral">Versi {doc.versi_semasa}</Badge>
                            </div>
                            <p className="text-body-sm text-txt-black-500 break-all">
                                {doc.no_rujukan} · {doc.nama_fail} · {saizMesra(doc.saiz_bait)}
                            </p>
                            {doc.induk && (
                                <p className="text-body-sm">
                                    Rekod induk:{' '}
                                    {doc.induk.pautan ? (
                                        <Link href={doc.induk.pautan} className="text-txt-primary hover:underline">
                                            {doc.induk.label} {doc.induk.id}
                                        </Link>
                                    ) : (
                                        <span>
                                            {doc.induk.label} {doc.induk.id}
                                        </span>
                                    )}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {doc.boleh.muat_turun && (
                                <>
                                    <Button size="sm" onClick={() => openLink('muat_turun')}>
                                        Muat turun
                                    </Button>
                                    {(doc.pratonton === 'pdf' || doc.pratonton === 'imej') && (
                                        <Button size="sm" variant="secondary" onClick={() => openLink('cetak')}>
                                            Cetak
                                        </Button>
                                    )}
                                </>
                            )}
                            {doc.boleh.kemas_kini && (
                                <Button size="sm" variant="secondary" onClick={() => setEditOpen(true)}>
                                    Pinda metadata
                                </Button>
                            )}
                            {doc.boleh.arkib && (
                                <Button size="sm" variant="secondary" disabled={archive.isPending} onClick={() => archive.mutate()}>
                                    Arkibkan
                                </Button>
                            )}
                            {doc.boleh.mohon_lupus && (
                                <Button size="sm" variant="danger" onClick={() => setLupusOpen(true)}>
                                    Mohon pelupusan
                                </Button>
                            )}
                            {doc.boleh.padam && (
                                <Button size="sm" variant="danger" onClick={() => setDeleteOpen(true)}>
                                    Padam
                                </Button>
                            )}
                        </div>
                    </div>

                    <Tabs tabs={tabs}>
                        <TabPanel value="pratonton">
                            <DocumentPreview doc={doc} />
                        </TabPanel>
                        <TabPanel value="metadata">
                            <MetadataView doc={doc} />
                        </TabPanel>
                        <TabPanel value="versi">
                            <VersionsView doc={doc} onChanged={refresh} onDownload={(v) => openLink('muat_turun', v)} />
                        </TabPanel>
                        <TabPanel value="kebenaran">
                            <GrantsView doc={doc} />
                        </TabPanel>
                        <TabPanel value="audit">
                            <p className="text-body-sm text-txt-black-700">
                                Semua muat naik, muat turun, pratonton, cetakan, pindaan metadata, versi, pengarkiban dan pelupusan dokumen ini direkod dalam audit trail pusat.
                            </p>
                            <Link href={`/admin/aud/kronologi?nama_entiti=dokumen&id_rekod=${doc.id}`} className="mt-3 inline-block text-body-sm text-txt-primary hover:underline">
                                Lihat jejak audit dokumen ini →
                            </Link>
                        </TabPanel>
                    </Tabs>
                </div>
            </PageContainer>

            {editOpen && <EditMetadataModal doc={doc} onClose={() => setEditOpen(false)} onSaved={refresh} />}

            <DisposalModal open={lupusOpen} docId={doc.id} onClose={() => setLupusOpen(false)} onDone={refresh} />

            <Modal open={deleteOpen} onClose={() => setDeleteOpen(false)}>
                <div className="p-6">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Padam dokumen ini?</h2>
                    <p className="mt-1 text-body-sm text-txt-black-500">Pemadaman adalah logik — rekod dan fail disimpan untuk audit, tetapi tidak lagi dipaparkan.</p>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setDeleteOpen(false)}>
                            Batal
                        </Button>
                        <Button variant="danger" disabled={remove.isPending} onClick={() => remove.mutate()}>
                            Padam
                        </Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}

function MetadataView({ doc }: { doc: DokumenPenuh }) {
    const { data: kategori } = useQuery({ queryKey: ['dms', 'kategori'], queryFn: () => dmsApi.kategori() });
    const fields = kategori?.data.find((k) => k.id === doc.kategori.id)?.medan ?? [];

    return (
        <dl>
            <Row label="Kategori">{doc.kategori.nama}</Row>
            <Row label="Modul sumber">{doc.modul_sumber.toUpperCase()}</Row>
            <Row label="Daerah / Mukim">
                {doc.kod_daerah ?? '—'} / {doc.kod_mukim ?? '—'}
            </Row>
            <Row label="Tahun">{doc.tahun ?? '—'}</Row>
            <Row label="Tarikh dokumen">{tarikhMesra(doc.tarikh_dokumen)}</Row>
            <Row label="Tarikh luput">{tarikhMesra(doc.tarikh_luput)}</Row>
            <Row label="Pemilik proses">{doc.pemilik_proses?.nama ?? '—'}</Row>
            <Row label="Dimuat naik oleh">
                {doc.pencipta?.nama ?? '—'} · {tarikhMesra(doc.dicipta_pada, true)}
            </Row>
            <Row label="Kata kunci">{doc.kata_kunci.length ? doc.kata_kunci.join(', ') : '—'}</Row>
            {Object.entries(doc.metadata).map(([key, value]) => (
                <Row key={key} label={fields.find((f) => f.kod === key)?.label ?? key}>
                    {value === true ? 'Ya' : value === false ? 'Tidak' : String(value ?? '—')}
                </Row>
            ))}
            <Row label="Cincangan SHA-256">
                <code className="text-body-xs break-all">{doc.cincangan_fail}</code>
            </Row>
            <Row label="Indeks teks">
                {doc.status_indeks === 'selesai' ? `Selesai (${doc.kaedah_indeks === 'ocr' ? 'OCR' : 'teks'})` : doc.status_indeks}
            </Row>
            {doc.tarikh_arkib && <Row label="Diarkib pada">{tarikhMesra(doc.tarikh_arkib, true)}</Row>}
            {doc.tarikh_lupus && <Row label="Dilupus pada">{tarikhMesra(doc.tarikh_lupus, true)}</Row>}
        </dl>
    );
}

function VersionsView({ doc, onChanged, onDownload }: { doc: DokumenPenuh; onChanged: () => void; onDownload: (v: number) => void }) {
    const { data } = useQuery({ queryKey: ['dms', 'versi', doc.id], queryFn: () => dmsApi.versi(doc.id) });
    const versions = data?.data ?? [];
    const [selected, setSelected] = useState<number[]>([]);
    const [diff, setDiff] = useState<PerbandinganVersi | null>(null);
    const [progress, setProgress] = useState<number | null>(null);
    const [note, setNote] = useState('');
    const input = useRef<HTMLInputElement>(null);

    async function compare() {
        const [a, b] = [...selected].sort((x, y) => x - y);
        const r = await dmsApi.banding(doc.id, a, b);
        setDiff(r.data);
    }

    async function upload(file: File) {
        setProgress(0);
        try {
            const result = await muatNaikFail(`/dms/dokumen/${doc.id}/versi`, file, { catatan: note }, setProgress, "fail");
            if (result.status === 'ditolak') toast.error(result.mesej ?? 'Ditolak.');
            else toast.success('Versi baharu disimpan.');
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Muat naik gagal.');
        } finally {
            setProgress(null);
            setNote('');
            onChanged();
        }
    }

    return (
        <div className="space-y-4">
            {doc.boleh.versi_baharu && (
                <div className="flex flex-col sm:flex-row gap-2 sm:items-end">
                    <div className="flex-1">
                        <InputLabel htmlFor="versi-catatan" className="mb-1.5">
                            Catatan versi baharu
                        </InputLabel>
                        <input id="versi-catatan" className={inputClass} value={note} onChange={(e) => setNote(e.target.value)} />
                    </div>
                    <input ref={input} type="file" className="sr-only" onChange={(e) => e.target.files?.[0] && upload(e.target.files[0])} />
                    <Button disabled={progress !== null} onClick={() => input.current?.click()}>
                        {progress !== null ? `Memuat naik ${Math.round(progress * 100)}%` : 'Muat naik versi baharu'}
                    </Button>
                </div>
            )}

            <div className="overflow-x-auto">
                <Table
                    head={
                        <>
                            <th className="w-10">
                                <span className="sr-only">Pilih untuk banding</span>
                            </th>
                            <th>Versi</th>
                            <th>Fail</th>
                            <th className="hidden sm:table-cell">Dimuat naik</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {versions.map((v) => (
                        <tr key={v.id}>
                            <td>
                                <input
                                    type="checkbox"
                                    aria-label={`Pilih versi ${v.no_versi}`}
                                    checked={selected.includes(v.no_versi)}
                                    onChange={(e) =>
                                        setSelected((s) => (e.target.checked ? [...s, v.no_versi].slice(-2) : s.filter((n) => n !== v.no_versi)))
                                    }
                                    className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                />
                            </td>
                            <td className="font-medium">v{v.no_versi}</td>
                            <td className="max-w-[12rem] break-words">
                                {v.nama_fail}
                                <p className="text-body-xs text-txt-black-500">
                                    {saizMesra(v.saiz_bait)}
                                    {v.catatan ? ` · ${v.catatan}` : ''}
                                </p>
                            </td>
                            <td className="hidden sm:table-cell whitespace-nowrap">
                                {v.dimuat_naik_oleh?.nama ?? '—'}
                                <p className="text-body-xs text-txt-black-500">{tarikhMesra(v.dimuat_naik_pada, true)}</p>
                            </td>
                            <td className="text-right">
                                {v.tersedia && (
                                    <button type="button" className="text-body-sm text-txt-primary hover:underline" onClick={() => onDownload(v.no_versi)}>
                                        Muat turun
                                    </button>
                                )}
                            </td>
                        </tr>
                    ))}
                </Table>
            </div>

            <Button variant="secondary" size="sm" disabled={selected.length !== 2} onClick={compare}>
                Banding 2 versi dipilih
            </Button>

            {diff && (
                <div className="space-y-2">
                    <p className="text-body-sm text-txt-black-700">
                        v{diff.dari.no_versi} ↔ v{diff.ke.no_versi}: {diff.kandungan_sama ? 'kandungan fail sama' : 'kandungan fail berbeza'}
                    </p>
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>Medan</th>
                                    <th>v{diff.dari.no_versi}</th>
                                    <th>v{diff.ke.no_versi}</th>
                                </>
                            }
                        >
                            {diff.medan.map((m) => (
                                <tr key={m.medan} className={m.berubah ? 'bg-bg-warning-50' : undefined}>
                                    <td className="font-medium">{m.medan}</td>
                                    <td className="break-all">{m.dari ?? '—'}</td>
                                    <td className="break-all">{m.ke ?? '—'}</td>
                                </tr>
                            ))}
                        </Table>
                    </div>
                </div>
            )}
        </div>
    );
}

function GrantsView({ doc }: { doc: DokumenPenuh }) {
    const queryClient = useQueryClient();
    const key = ['dms', 'kebenaran', doc.id];
    const { data } = useQuery({ queryKey: key, queryFn: () => dmsApi.kebenaran(doc.id) });
    const [jenis, setJenis] = useState<'pengguna' | 'peranan'>('pengguna');
    const [penerima, setPenerima] = useState('');
    const [tamat, setTamat] = useState('');

    const add = useMutation({
        mutationFn: () => dmsApi.tambahKebenaran(doc.id, { jenis_penerima: jenis, penerima, tamat_pada: tamat || undefined }),
        onSuccess: () => {
            setPenerima('');
            queryClient.invalidateQueries({ queryKey: key });
        },
        onError: (e: Error) => toast.error(e instanceof ApiError && e.errors ? Object.values(e.errors).flat().join(' ') : e.message),
    });
    const remove = useMutation({
        mutationFn: (grant: number) => dmsApi.buangKebenaran(doc.id, grant),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: key }),
    });

    return (
        <div className="space-y-4">
            <p className="text-body-sm text-txt-black-700">
                Dokumen {KLASIFIKASI_LABEL[doc.klasifikasi]} hanya boleh dicapai oleh pemegang kebenaran klasifikasi atau penerima kebenaran eksplisit di bawah.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-2 sm:items-end">
                <div>
                    <InputLabel htmlFor="g-jenis" className="mb-1.5">
                        Jenis
                    </InputLabel>
                    <select id="g-jenis" className={inputClass} value={jenis} onChange={(e) => setJenis(e.target.value as 'pengguna' | 'peranan')}>
                        <option value="pengguna">Pengguna (ID)</option>
                        <option value="peranan">Peranan (kod)</option>
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="g-penerima" className="mb-1.5">
                        {jenis === 'pengguna' ? 'ID pengguna' : 'Kod peranan'}
                    </InputLabel>
                    <input id="g-penerima" className={inputClass} value={penerima} onChange={(e) => setPenerima(e.target.value)} />
                </div>
                <div>
                    <InputLabel htmlFor="g-tamat" className="mb-1.5">
                        Tamat (pilihan)
                    </InputLabel>
                    <input id="g-tamat" type="date" className={inputClass} value={tamat} onChange={(e) => setTamat(e.target.value)} />
                </div>
                <Button disabled={!penerima || add.isPending} onClick={() => add.mutate()}>
                    Tambah
                </Button>
            </div>
            <ul className="divide-y divide-otl-divider">
                {(data?.data ?? []).map((g) => (
                    <li key={g.id} className="py-2 flex items-center justify-between gap-2">
                        <span className="text-body-sm">
                            {g.jenis_penerima === 'pengguna' ? 'Pengguna' : 'Peranan'}: <strong>{g.label}</strong>
                            {g.tamat_pada && <span className="text-txt-black-500"> · tamat {tarikhMesra(g.tamat_pada)}</span>}
                        </span>
                        <button type="button" className="text-body-sm text-danger-600 hover:underline" onClick={() => remove.mutate(g.id)}>
                            Buang
                        </button>
                    </li>
                ))}
                {data?.data.length === 0 && <li className="py-2 text-body-sm text-txt-black-500">Tiada kebenaran eksplisit.</li>}
            </ul>
        </div>
    );
}

function EditMetadataModal({ doc, onClose, onSaved }: { doc: DokumenPenuh; onClose: () => void; onSaved: () => void }) {
    const { data: kategori } = useQuery({ queryKey: ['dms', 'kategori'], queryFn: () => dmsApi.kategori() });
    const fields = kategori?.data.find((k) => k.id === doc.kategori.id)?.medan.filter((m) => m.aktif) ?? [];
    const [form, setForm] = useState({
        tajuk: doc.tajuk,
        klasifikasi: doc.klasifikasi,
        tarikh_dokumen: doc.tarikh_dokumen ?? '',
        tarikh_luput: doc.tarikh_luput ?? '',
        kod_daerah: doc.kod_daerah ?? '',
        kod_mukim: doc.kod_mukim ?? '',
        kata_kunci: doc.kata_kunci.join(', '),
    });
    const [extra, setExtra] = useState<Record<string, string>>(
        Object.fromEntries(Object.entries(doc.metadata).map(([k, v]) => [k, v === true ? '1' : v === false ? '0' : String(v ?? '')])),
    );
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const save = useMutation({
        mutationFn: () =>
            dmsApi.kemasKini(doc.id, {
                ...form,
                tarikh_dokumen: form.tarikh_dokumen || null,
                tarikh_luput: form.tarikh_luput || null,
                kod_daerah: form.kod_daerah || null,
                kod_mukim: form.kod_mukim || null,
                metadata: extra,
            }),
        onSuccess: () => {
            toast.success('Metadata dikemas kini.');
            onSaved();
            onClose();
        },
        onError: (e: Error) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(e.message)),
    });

    const text = (key: keyof typeof form, label: string, type = 'text') => (
        <div>
            <InputLabel htmlFor={`e-${key}`} className="mb-1.5">
                {label}
            </InputLabel>
            <input id={`e-${key}`} type={type} className={inputClass} value={form[key]} onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))} />
            {errors[key] && <p className="mt-1 text-body-xs text-txt-danger">{errors[key][0]}</p>}
        </div>
    );

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-3 max-h-[85vh] overflow-y-auto">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Pinda metadata</h2>
                {text('tajuk', 'Tajuk')}
                <div>
                    <InputLabel htmlFor="e-klasifikasi" className="mb-1.5">
                        Klasifikasi
                    </InputLabel>
                    <select id="e-klasifikasi" className={inputClass} value={form.klasifikasi} onChange={(e) => setForm((f) => ({ ...f, klasifikasi: e.target.value as Klasifikasi }))}>
                        {(Object.keys(KLASIFIKASI_LABEL) as Klasifikasi[]).map((k) => (
                            <option key={k} value={k}>
                                {KLASIFIKASI_LABEL[k]}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {text('tarikh_dokumen', 'Tarikh dokumen', 'date')}
                    {text('tarikh_luput', 'Tarikh luput', 'date')}
                    {text('kod_daerah', 'Kod daerah')}
                    {text('kod_mukim', 'Kod mukim')}
                </div>
                {text('kata_kunci', 'Kata kunci (pisah dengan koma)')}
                {fields.map((f) => (
                    <div key={f.id}>
                        <ExtraField field={f} value={extra[f.kod] ?? ''} onChange={(v) => setExtra((x) => ({ ...x, [f.kod]: v }))} />
                        {errors[`metadata.${f.kod}`] && <p className="mt-1 text-body-xs text-txt-danger">{errors[`metadata.${f.kod}`][0]}</p>}
                    </div>
                ))}
                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button disabled={save.isPending} onClick={() => save.mutate()}>
                        Simpan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function DisposalModal({ open, docId, onClose, onDone }: { open: boolean; docId: string; onClose: () => void; onDone: () => void }) {
    const [justifikasi, setJustifikasi] = useState('');
    const [error, setError] = useState<string | undefined>();
    const submit = useMutation({
        mutationFn: () => dmsApi.mohonLupus(docId, justifikasi),
        onSuccess: (r) => {
            toast.success(r.message);
            onDone();
            onClose();
        },
        onError: (e: Error) => setError(e instanceof ApiError && e.errors ? e.errors.justifikasi?.[0] : e.message),
    });

    return (
        <Modal open={open} onClose={onClose}>
            <div className="p-6 space-y-4">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Mohon pelupusan dokumen</h2>
                <p className="text-body-sm text-txt-black-500">
                    Pelupusan memerlukan kelulusan dua peringkat melalui aliran kerja. Kandungan fail dimusnahkan selepas diluluskan; metadata dikekalkan.
                </p>
                <Textarea label="Justifikasi" name="justifikasi" value={justifikasi} onChange={setJustifikasi} required rows={4} error={error} />
                <div className="flex justify-end gap-3">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button variant="danger" disabled={submit.isPending || justifikasi.length < 10} onClick={() => submit.mutate()}>
                        Hantar untuk kelulusan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
