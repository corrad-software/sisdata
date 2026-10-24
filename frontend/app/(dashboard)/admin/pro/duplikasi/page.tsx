'use client';

import { Suspense, useState } from 'react';
import type { FormEvent } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Textarea } from '@/components/ui/Textarea';
import { Card, InfoList, Loading, Masked, Pager, SpatialBadge } from '@/components/pro/common';
import {
    BAHAGIAN_LABEL,
    KEPUTUSAN_DUPLIKASI_LABEL,
    STATUS_LOT_LABEL,
    errorMessage,
    fmtNombor,
    fmtTarikh,
    proApi,
    useCan,
    type CadanganDuplikasi,
    type LotRingkas,
} from '@/lib/pro';

const BERAT_LABEL: Record<string, string> = {
    berat_no_lot: 'No. lot',
    berat_mukim: 'Mukim',
    berat_keluasan: 'Keluasan',
    berat_geometri: 'Geometri',
};

const KRITERIA_LABEL: Record<string, string> = {
    berat_no_lot: 'Berat padanan no. lot',
    berat_mukim: 'Berat mukim sama',
    berat_keluasan: 'Berat keluasan',
    berat_geometri: 'Berat pertindihan geometri',
    ambang_skor: 'Ambang skor cadangan',
    ambang_nama: 'Ambang keserupaan no. lot',
    toleransi_keluasan_peratus: 'Toleransi beza keluasan (%)',
    ambang_pertindihan_peratus: 'Ambang pertindihan geometri (%)',
};

function skorTone(s: number): 'danger' | 'warning' | 'info' {
    return s >= 85 ? 'danger' : s >= 70 ? 'warning' : 'info';
}

function LotSide({ lot, label, other }: { lot: LotRingkas | null; label: string; other: LotRingkas | null }) {
    if (!lot) return <p className="text-body-sm text-txt-black-500">Lot {label} tidak dapat dipaparkan.</p>;
    const diff = (a: unknown, b: unknown) => other !== null && String(a ?? '') !== String(b ?? '');
    const hl = (d: boolean) => (d ? 'rounded bg-bg-warning-50 px-1' : '');
    return (
        <div className="space-y-2 min-w-0">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="neutral" shape="tag">
                    Lot {label}
                </Badge>
                <Link href={`/admin/pro/lot/${lot.id}`} className="font-semibold text-txt-primary hover:underline break-words">
                    {lot.no_lot ?? '—'}
                </Link>
            </div>
            <InfoList
                items={[
                    { label: 'Mukim', value: <span className={hl(diff(lot.kod_mukim, other?.kod_mukim))}>{lot.nama_mukim ?? lot.kod_mukim ?? '—'}</span> },
                    { label: 'Daerah', value: lot.nama_daerah ?? lot.kod_daerah ?? '—' },
                    {
                        label: 'Keluasan',
                        value: (
                            <Masked field="keluasan" masked={lot.medan_disamarkan}>
                                <span className={hl(diff(lot.keluasan, other?.keluasan))}>{lot.keluasan !== null ? `${fmtNombor(lot.keluasan, 4)} ${lot.unit_keluasan ?? ''}` : '—'}</span>
                            </Masked>
                        ),
                    },
                    {
                        label: 'Bahagian',
                        value: (
                            <Masked field="bahagian_pemilik" masked={lot.medan_disamarkan}>
                                {lot.bahagian_pemilik ? (BAHAGIAN_LABEL[lot.bahagian_pemilik] ?? lot.bahagian_pemilik) : '—'}
                            </Masked>
                        ),
                    },
                    { label: 'Status lot', value: STATUS_LOT_LABEL[lot.status_lot] ?? lot.status_lot },
                    { label: 'Geometri', value: <SpatialBadge spatial={lot.spatial} /> },
                    { label: 'Kategori', value: lot.nama_kategori_guna_tanah ?? lot.kategori_guna_tanah ?? '—' },
                    { label: 'Dikemas kini', value: fmtTarikh(lot.dikemas_kini_pada) },
                ]}
            />
        </div>
    );
}

function Breakdown({ c }: { c: CadanganDuplikasi }) {
    const k = c.kriteria_padanan ?? {};
    const berat = k.berat ?? {};
    const comp: Array<{ key: string; label: string; nilai: string; skor: number }> = [
        { key: 'berat_no_lot', label: 'Padanan no. lot', nilai: `${fmtNombor((k.padanan_no_lot ?? 0) * 100, 0)}%`, skor: k.padanan_no_lot ?? 0 },
        { key: 'berat_mukim', label: 'Mukim sama', nilai: k.sama_mukim ? 'Ya' : 'Tidak', skor: k.sama_mukim ? 1 : 0 },
        {
            key: 'berat_keluasan',
            label: 'Beza keluasan',
            nilai: k.beza_keluasan_peratus === null || k.beza_keluasan_peratus === undefined ? 'Tiada data' : `${fmtNombor(k.beza_keluasan_peratus)}%`,
            skor: -1,
        },
        { key: 'berat_geometri', label: 'Pertindihan geometri', nilai: `${fmtNombor(k.pertindihan_geometri_peratus ?? 0)}%`, skor: -1 },
    ];
    return (
        <div className="space-y-2">
            <div className="flex items-center gap-3">
                <span className="text-body-sm font-medium text-txt-black-700">Skor padanan</span>
                <Badge variant={skorTone(c.skor_padanan)}>{fmtNombor(c.skor_padanan, 1)} / 100</Badge>
            </div>
            <div className="h-2 w-full rounded-full bg-bg-washed overflow-hidden" aria-hidden="true">
                <div className="h-full bg-primary-600" style={{ width: `${Math.min(100, Math.max(0, c.skor_padanan))}%` }} />
            </div>
            <ul className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-body-sm">
                {comp.map((x) => (
                    <li key={x.key} className="rounded-md border border-otl-gray-200 px-3 py-2">
                        <span className="block text-body-xs text-txt-black-500">
                            {x.label} · berat {berat[x.key] ?? '—'} ({BERAT_LABEL[x.key]})
                        </span>
                        <span className="font-medium text-txt-black-900">{x.nilai}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function DecisionModal({ c, onClose }: { c: CadanganDuplikasi | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [keputusan, setKeputusan] = useState<'sah' | 'gabung' | 'tolak'>('tolak');
    const [kekal, setKekal] = useState('');
    const [catatan, setCatatan] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    function close() {
        setKeputusan('tolak');
        setKekal('');
        setCatatan('');
        setError(null);
        onClose();
    }

    async function submit(e: FormEvent) {
        e.preventDefault();
        if (!c) return;
        if (keputusan === 'gabung' && !kekal) {
            setError('Pilih rekod induk yang dikekalkan.');
            return;
        }
        setBusy(true);
        setError(null);
        try {
            const res = await proApi.keputusanDuplikasi(c.id, { keputusan, catatan: catatan.trim() || undefined, id_rekod_kekal: keputusan === 'gabung' ? kekal : undefined });
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro'] });
            close();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    const opts: Array<{ v: 'sah' | 'gabung' | 'tolak'; t: string; d: string }> = [
        { v: 'tolak', t: 'Bukan duplikasi', d: 'Tolak cadangan; kedua-dua lot kekal.' },
        { v: 'sah', t: 'Sahkan duplikasi', d: 'Tandakan sebagai duplikasi tanpa menggabungkan.' },
        { v: 'gabung', t: 'Gabung', d: 'Satu lot dikekalkan sebagai rekod induk; satu lagi berstatus digabungkan.' },
    ];

    return (
        <Modal open={!!c} onClose={close}>
            {c && (
                <form onSubmit={submit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        Keputusan: {c.lot_a?.no_lot} ↔ {c.lot_b?.no_lot}
                    </h2>
                    <fieldset className="space-y-2">
                        <legend className="text-body-sm font-medium text-txt-black-900 mb-1">Keputusan</legend>
                        {opts.map((o) => (
                            <label key={o.v} className="flex items-start gap-2 rounded-md border border-otl-gray-200 p-3 text-body-sm cursor-pointer">
                                <input type="radio" name="keputusan" checked={keputusan === o.v} onChange={() => setKeputusan(o.v)} className="mt-0.5 text-primary-600 focus:ring-fr-primary" />
                                <span>
                                    <span className="font-medium text-txt-black-900">{o.t}</span>
                                    <span className="block text-body-xs text-txt-black-500">{o.d}</span>
                                </span>
                            </label>
                        ))}
                    </fieldset>
                    {keputusan === 'gabung' && (
                        <fieldset className="space-y-2">
                            <legend className="text-body-sm font-medium text-txt-black-900 mb-1">
                                Rekod induk yang dikekalkan <span className="text-danger-600">*</span>
                            </legend>
                            {[c.lot_a, c.lot_b].map((l, i) =>
                                l ? (
                                    <label key={l.id} className="flex items-center gap-2 text-body-sm">
                                        <input type="radio" name="kekal" checked={kekal === l.id} onChange={() => setKekal(l.id)} className="text-primary-600 focus:ring-fr-primary" />
                                        Lot {i === 0 ? 'A' : 'B'}: {l.no_lot} ({l.nama_mukim ?? l.kod_mukim})
                                    </label>
                                ) : null,
                            )}
                        </fieldset>
                    )}
                    <Textarea label="Catatan" name="catatan" value={catatan} onChange={setCatatan} maxLength={1000} rows={3} />
                    {error && <Alert type="error">{error}</Alert>}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={close}>
                            Batal
                        </Button>
                        <Button type="submit" variant={keputusan === 'gabung' ? 'danger' : 'primary'} disabled={busy}>
                            {busy ? 'Menyimpan…' : 'Rekod keputusan'}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}

function DuplikasiPage() {
    const can = useCan();
    const router = useRouter();
    const params = useSearchParams();
    const queryClient = useQueryClient();
    const focusId = params.get('id');
    const [keputusan, setKeputusan] = useState('menunggu');
    const [page, setPage] = useState(1);
    const [decide, setDecide] = useState<CadanganDuplikasi | null>(null);
    const [busy, setBusy] = useState(false);
    const [showKriteria, setShowKriteria] = useState(false);

    const f = focusId ? { id: focusId } : { keputusan, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'duplikasi', f], queryFn: () => proApi.duplikasi(f) });
    const kriteria = useQuery({ queryKey: ['pro', 'duplikasi', 'kriteria'], queryFn: () => proApi.kriteriaDuplikasi().then((r) => r.data), enabled: showKriteria });

    async function detect() {
        setBusy(true);
        try {
            const res = await proApi.kesanDuplikasi();
            toast.success(`${res.data.diperiksa} pasangan diperiksa, ${res.data.cadangan_baharu} cadangan baharu.`);
            queryClient.invalidateQueries({ queryKey: ['pro', 'duplikasi'] });
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }

    const rows = data?.data ?? [];

    return (
        <PageContainer>
            <Card>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {focusId ? (
                        <div className="flex flex-wrap items-center gap-3 text-body-sm">
                            <span>Memaparkan satu cadangan.</span>
                            <Button variant="outline" size="sm" onClick={() => router.replace('/admin/pro/duplikasi')}>
                                Papar semua
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-wrap gap-2" role="group" aria-label="Tapis keputusan">
                            {Object.entries(KEPUTUSAN_DUPLIKASI_LABEL).map(([k, l]) => (
                                <button
                                    key={k}
                                    type="button"
                                    aria-pressed={keputusan === k}
                                    onClick={() => {
                                        setKeputusan(k);
                                        setPage(1);
                                    }}
                                    className={`rounded-full border px-3 py-1 text-body-xs font-medium ${
                                        keputusan === k ? 'border-primary-600 bg-primary-600 text-white' : 'border-otl-gray-300 bg-bg-white text-txt-black-700 hover:bg-bg-washed'
                                    }`}
                                >
                                    {l}
                                </button>
                            ))}
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => setShowKriteria((v) => !v)} aria-expanded={showKriteria}>
                            Kriteria padanan
                        </Button>
                        {can('pro.duplikasi.kemas_kini') && (
                            <Button onClick={detect} disabled={busy}>
                                {busy ? 'Mengesan…' : 'Kesan duplikasi'}
                            </Button>
                        )}
                    </div>
                </div>
                {showKriteria && (
                    <div className="space-y-2">
                        {kriteria.isLoading ? (
                            <Loading />
                        ) : kriteria.data ? (
                            <InfoList items={Object.entries(kriteria.data).map(([k, v]) => ({ label: KRITERIA_LABEL[k] ?? k, value: fmtNombor(v) }))} />
                        ) : null}
                        <p className="text-body-xs text-txt-black-500">Kriteria dikonfigurasi melalui parameter sistem &quot;pro.kriteria_duplikasi&quot; (Pentadbiran → Parameter).</p>
                    </div>
                )}
            </Card>

            {error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : isLoading ? (
                <Loading />
            ) : rows.length === 0 ? (
                <Card>
                    <p className="text-body-sm text-txt-black-500 text-center py-4">Tiada cadangan duplikasi.</p>
                </Card>
            ) : (
                rows.map((c) => (
                    <Card
                        key={c.id}
                        title={
                            <span className="inline-flex flex-wrap items-center gap-2">
                                Cadangan #{c.id}
                                <Badge variant={c.keputusan === 'menunggu' ? 'warning' : 'neutral'}>{KEPUTUSAN_DUPLIKASI_LABEL[c.keputusan] ?? c.keputusan}</Badge>
                            </span>
                        }
                        actions={
                            c.keputusan === 'menunggu' && can('pro.duplikasi.kemas_kini') ? <Button onClick={() => setDecide(c)}>Buat keputusan</Button> : undefined
                        }
                    >
                        <Breakdown c={c} />
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <LotSide lot={c.lot_a} other={c.lot_b} label="A" />
                            <LotSide lot={c.lot_b} other={c.lot_a} label="B" />
                        </div>
                        {c.keputusan !== 'menunggu' && (
                            <p className="text-body-sm text-txt-black-700">
                                Diputuskan pada {fmtTarikh(c.tarikh_keputusan, true)}
                                {c.catatan_keputusan ? ` — “${c.catatan_keputusan}”` : ''}
                            </p>
                        )}
                    </Card>
                ))
            )}

            {!focusId && <Pager meta={data?.meta} page={page} onPage={setPage} />}
            <DecisionModal c={decide} onClose={() => setDecide(null)} />
        </PageContainer>
    );
}

export default function Page() {
    return (
        <Suspense>
            <DuplikasiPage />
        </Suspense>
    );
}
