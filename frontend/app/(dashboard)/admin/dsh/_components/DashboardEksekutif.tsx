'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter, useSearchParams } from 'next/navigation';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { AnchorButton, Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Spinner } from '@/components/ui/Spinner';
import {
    ARAS_LABEL,
    VARIAN_LABEL,
    dshApi,
    errorMessage,
    fmtMasa,
    storedPenapis,
    usePenapis,
    type Aras,
    type Dashboard,
    type Penapis,
    type Widget,
} from '@/lib/dsh';
import { AiPanel } from './AiPanel';
import { InsightWidget, PetaWidget, TugasanWidget } from './PanelWidgets';
import { PenapisBar, inputClass } from './PenapisBar';
import { SusunAturModal } from './SusunAturModal';
import { KpiWidget, LampuWidget } from './Widgets';

const SPAN: Record<number, string> = { 1: '', 2: 'sm:col-span-2', 3: 'sm:col-span-2 xl:col-span-3', 4: 'sm:col-span-2 xl:col-span-4' };

function WidgetView({ w, d, p }: { w: Widget; d: Dashboard; p: Penapis }) {
    if (w.kod === 'sistem:peta') return <PetaWidget p={p} />;
    if (w.kod === 'sistem:lampu') return <LampuWidget kpis={d.kpi} p={p} />;
    if (w.kod === 'sistem:insight') return <InsightWidget aras={d.aras} p={p} />;
    if (w.kod === 'sistem:tugasan') return <TugasanWidget />;
    const k = w.kpi ? d.kpi.find((x) => x.key === w.kpi) : undefined;
    if (!k) return null;
    return <KpiWidget k={k} visual={w.jenis_visual} p={p} title={w.tajuk} />;
}

/**
 * The executive dashboard (MOD-DSH): level chosen automatically from the
 * user's role (SRS-DSH-01), shared filters (DSH-08), widgets in the user's
 * own layout (DSH-16), map (DSH-03/04), traffic lights (DSH-09/10),
 * drill-down (DSH-11), data timestamps (DSH-12), export / saved views /
 * digest (DSH-14) and the local AI prompt (DSH-15..18).
 */
export function DashboardEksekutif() {
    const params = useSearchParams();
    const router = useRouter();
    const qc = useQueryClient();
    const [p, setP] = usePenapis();
    const arasParam = (params.get('aras') as Aras | null) ?? undefined;
    const [segar, setSegar] = useState(false);
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [subscribing, setSubscribing] = useState(false);
    const restored = useRef(false);

    // First visit without filters in the URL: bring back the last-used filters.
    useEffect(() => {
        if (restored.current) return;
        restored.current = true;
        const hasUrl = Array.from(params.keys()).some((k) => k !== 'aras');
        const stored = storedPenapis();
        if (!hasUrl && stored) setP(stored);
    }, [params, setP]);

    const q = useQuery({
        queryKey: ['dsh', 'dashboard', arasParam ?? 'lalai', p, segar],
        queryFn: () => dshApi.dashboard(arasParam, p, segar),
        staleTime: 30_000,
        placeholderData: (prev) => prev,
    });
    const d = q.data;

    const views = useQuery({ queryKey: ['dsh', 'kegemaran'], queryFn: dshApi.kegemaran, staleTime: 60_000 });
    const snapshot = useMutation({
        mutationFn: () => dshApi.janaSnapshot(d!.aras, p),
        onSuccess: (r) => {
            toast.success(r.message);
            router.push(`/admin/dsh/snapshot/${r.data.id}`);
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    function setAras(a: Aras) {
        const s = new URLSearchParams(params.toString());
        s.set('aras', a);
        router.replace(`?${s.toString()}`, { scroll: false });
    }

    if (q.isLoading && !d) {
        return (
            <PageContainer>
                <div className="flex justify-center py-16">
                    <Spinner size="lg" />
                </div>
            </PageContainer>
        );
    }
    if (q.isError || !d) {
        return (
            <PageContainer>
                <Alert type="error">{errorMessage(q.error)}</Alert>
            </PageContainer>
        );
    }

    const visible = d.widget.filter((w) => !w.tersembunyi);
    const skopLabel = d.skop.daerah ? d.skop.daerah.map((x) => x.nama).join(', ') : d.skop.bahagian ? d.skop.bahagian.map((x) => x.nama).join(', ') : null;

    return (
        <>
            {d.aras_tersedia.length > 1 && (
                <div className="border-b border-otl-divider bg-bg-white">
                    <nav data-panduan="dsh-aras" aria-label="Aras dashboard" className="-mb-px flex flex-nowrap gap-6 overflow-x-auto px-4 sm:px-6 lg:px-8">
                        {d.aras_tersedia.map((a) => (
                            <button
                                key={a}
                                type="button"
                                onClick={() => setAras(a)}
                                aria-current={a === d.aras ? 'page' : undefined}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    a === d.aras ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:border-otl-gray-300 hover:text-txt-black-900'
                                }`}
                            >
                                {ARAS_LABEL[a]}
                                {a === d.aras_lalai ? ' (lalai)' : ''}
                            </button>
                        ))}
                    </nav>
                </div>
            )}

            <PageContainer>
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="font-heading text-heading-xs font-semibold text-txt-black-900">Dashboard {ARAS_LABEL[d.aras]}</h1>
                        <p className="mt-1 flex flex-wrap items-center gap-2 text-body-xs text-txt-black-500">
                            <Badge variant="info">{VARIAN_LABEL[d.varian]}</Badge>
                            {skopLabel && <span>Skop: {skopLabel}</span>}
                            <span>
                                Data dikira pada {fmtMasa(d.dikira_pada)}
                                {d.data_terakhir ? ` · rekod terkini ${fmtMasa(d.data_terakhir)}` : ''} · dikemas kini sekurang-kurangnya setiap {Math.max(1, Math.round(d.ttl_saat / 60))} minit
                            </span>
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2" data-panduan="dsh-tindakan">
                        <Button variant="secondary" size="sm" onClick={() => { setSegar(true); qc.invalidateQueries({ queryKey: ['dsh'] }); setTimeout(() => setSegar(false), 500); }}>
                            Muat semula
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
                            Ubah susun atur
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => setSaving(true)}>
                            Simpan paparan
                        </Button>
                        {d.kebenaran.langganan && (
                            <Button variant="secondary" size="sm" onClick={() => setSubscribing(true)}>
                                Langgan ringkasan
                            </Button>
                        )}
                        {d.kebenaran.eksport && (
                            <>
                                <AnchorButton variant="secondary" size="sm" href={dshApi.eksportUrl('pdf', d.aras, p)}>
                                    Eksport PDF
                                </AnchorButton>
                                <AnchorButton variant="secondary" size="sm" href={dshApi.eksportUrl('xlsx', d.aras, p)}>
                                    Eksport Excel
                                </AnchorButton>
                                <Button variant="secondary" size="sm" onClick={() => snapshot.mutate()} disabled={snapshot.isPending}>
                                    Jana syot kilat
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {(views.data?.length ?? 0) > 0 && (
                    <div className="flex flex-wrap items-center gap-2 text-body-xs">
                        <span className="text-txt-black-500">Paparan kegemaran:</span>
                        {views.data!.map((v) => (
                            <button
                                key={v.id}
                                type="button"
                                className="rounded-full border border-otl-gray-300 px-3 py-1 text-txt-black-700 hover:bg-bg-washed"
                                onClick={() => {
                                    const f: Penapis = {};
                                    Object.entries(v.parameter_penapis).forEach(([k, val]) => ((f as Record<string, string>)[k] = String(val)));
                                    setP(f);
                                    if (v.aras && d.aras_tersedia.includes(v.aras) && v.aras !== d.aras) setAras(v.aras);
                                }}
                            >
                                {v.nama_paparan}
                                {v.lalai ? ' ★' : ''}
                            </button>
                        ))}
                    </div>
                )}

                <div data-panduan="dsh-penapis">
                    <PenapisBar value={p} onChange={setP} />
                </div>
                {d.terhad && <Alert type="warning">Daerah yang dipilih berada di luar skop capaian anda; tiada data dipaparkan untuknya.</Alert>}

                {d.kebenaran.ai && <AiPanel aras={d.aras} p={p} />}

                {visible.length === 0 ? (
                    <Alert type="info">Tiada widget untuk dipaparkan. Klik “Ubah susun atur” untuk memaparkan semula widget.</Alert>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-panduan="dsh-widget">
                        {visible.map((w) => (
                            <div key={w.kod} className={`min-w-0 ${SPAN[Math.max(1, Math.min(4, w.saiz))] ?? ''}`}>
                                <WidgetView w={w} d={d} p={p} />
                            </div>
                        ))}
                    </div>
                )}

                <p className="text-body-xs text-txt-black-500">
                    Klik nilai indikator untuk melihat senarai rekod. <Link href="/admin/dsh/bantuan?laluan=/admin" className="text-txt-primary hover:underline">Bantuan dashboard</Link>
                </p>
            </PageContainer>

            {editing && <SusunAturModal open onClose={() => setEditing(false)} aras={d.aras} widgets={d.widget} />}
            {saving && <SimpanPaparanModal onClose={() => setSaving(false)} aras={d.aras} p={p} />}
            {subscribing && <LanggananModal onClose={() => setSubscribing(false)} aras={d.aras} p={p} />}
        </>
    );
}

function SimpanPaparanModal({ onClose, aras, p }: { onClose: () => void; aras: Aras; p: Penapis }) {
    const [nama, setNama] = useState('');
    const [lalai, setLalai] = useState(false);
    const qc = useQueryClient();
    const save = useMutation({
        mutationFn: () => dshApi.simpanKegemaran({ nama_paparan: nama.trim(), aras, parameter_penapis: p, lalai }),
        onSuccess: (r) => {
            toast.success(r.message);
            qc.invalidateQueries({ queryKey: ['dsh', 'kegemaran'] });
            onClose();
        },
        onError: (e) => toast.error(errorMessage(e)),
    });
    return (
        <Modal open onClose={onClose}>
            <form
                className="space-y-3 p-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (nama.trim()) save.mutate();
                }}
            >
                <h2 className="font-heading text-heading-2xs font-semibold text-txt-black-900">Simpan paparan kegemaran</h2>
                <p className="text-body-xs text-txt-black-500">Penapis semasa dan aras {ARAS_LABEL[aras]} akan disimpan untuk akaun anda.</p>
                <label className="block text-body-sm text-txt-black-900">
                    Nama paparan
                    <input className={`${inputClass} mt-1`} value={nama} maxLength={120} onChange={(e) => setNama(e.target.value)} required />
                </label>
                <label className="flex items-center gap-2 text-body-sm text-txt-black-700">
                    <input type="checkbox" className="rounded border-otl-gray-300" checked={lalai} onChange={(e) => setLalai(e.target.checked)} />
                    Jadikan paparan lalai
                </label>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={save.isPending || !nama.trim()}>
                        Simpan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function LanggananModal({ onClose, aras, p }: { onClose: () => void; aras: Aras; p: Penapis }) {
    const qc = useQueryClient();
    const subs = useQuery({ queryKey: ['dsh', 'langganan'], queryFn: dshApi.langganan });
    const current = subs.data?.find((s) => s.aras === aras);
    const [pilihan, setKekerapan] = useState<string | null>(null);
    const kekerapan = pilihan ?? current?.kekerapan ?? 'mingguan';
    const save = useMutation({
        mutationFn: () => dshApi.simpanLangganan({ aras, kekerapan, penapis: p, aktif: true }),
        onSuccess: (r) => {
            toast.success(r.message);
            qc.invalidateQueries({ queryKey: ['dsh', 'langganan'] });
            onClose();
        },
        onError: (e) => toast.error(errorMessage(e)),
    });
    const remove = useMutation({
        mutationFn: () => dshApi.padamLangganan(current!.id),
        onSuccess: (r) => {
            toast.success(r.message);
            qc.invalidateQueries({ queryKey: ['dsh', 'langganan'] });
            onClose();
        },
    });
    return (
        <Modal open onClose={onClose}>
            <div className="space-y-3 p-5">
                <h2 className="font-heading text-heading-2xs font-semibold text-txt-black-900">Langgan ringkasan dashboard</h2>
                <p className="text-body-xs text-txt-black-500">
                    Syot kilat KPI aras {ARAS_LABEL[aras]} dengan penapis semasa dijana mengikut jadual dan ringkasannya dihantar melalui notifikasi/e-mel. Angka berklasifikasi Sulit/Rahsia tidak dimasukkan dalam e-mel.
                </p>
                {current && <Alert type="info">Langganan aktif: {current.kekerapan}, terakhir dihantar {fmtMasa(current.terakhir_dihantar)}.</Alert>}
                <label className="block text-body-sm text-txt-black-900">
                    Kekerapan
                    <select className={`${inputClass} mt-1`} value={kekerapan} onChange={(e) => setKekerapan(e.target.value)}>
                        <option value="harian">Harian</option>
                        <option value="mingguan">Mingguan</option>
                        <option value="bulanan">Bulanan</option>
                    </select>
                </label>
                <div className="flex flex-wrap justify-end gap-2">
                    {current && (
                        <Button variant="danger" onClick={() => remove.mutate()} disabled={remove.isPending}>
                            Batalkan langganan
                        </Button>
                    )}
                    <Button variant="secondary" onClick={onClose}>
                        Tutup
                    </Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        Simpan langganan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
