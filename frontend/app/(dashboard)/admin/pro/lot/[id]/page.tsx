'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter, useSearchParams } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Breadcrumb } from '@/components/Breadcrumb';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { DocumentAttachments } from '@/components/dms/DocumentAttachments';
import { Card, ExportMenu, KlasifikasiBadge, Loading, ReasonModal, SpatialBadge } from '@/components/pro/common';
import { Flags, ProfilSeksyen } from '@/components/pro/ProfilSeksyen';
import { KewanganPanel, RuangPanel, VersiPanel } from '@/components/pro/ProfilPanels';
import { STATUS_LOT_LABEL, errorMessage, fmtTarikh, pautanUi, proApi, toneStatusLot, useCan, type PeristiwaGarisMasa, type Profil, type Seksyen } from '@/lib/pro';

type Dialog = 'arkib' | 'pulih' | 'padam' | null;

const TIMELINE_COLOUR: Record<string, 'success' | 'danger' | 'warning' | 'primary'> = {
    pengesahan_geometri: 'success',
    geometri: 'primary',
    dokumen: 'primary',
    kritikal_lulus: 'success',
    kritikal_tolak: 'danger',
    duplikasi: 'warning',
};

const DOT: Record<string, string> = {
    success: 'bg-bg-success-50 text-txt-success',
    danger: 'bg-bg-danger-50 text-txt-danger',
    warning: 'bg-bg-warning-50 text-txt-warning',
    primary: 'bg-bg-primary-50 text-txt-primary',
};

/** components/ui/Timeline markup, with a link per event (SRS-PRO-27). */
function GarisMasa({ items }: { items: PeristiwaGarisMasa[] }) {
    const [all, setAll] = useState(false);
    const shown = all ? items : items.slice(0, 8);
    if (items.length === 0) return <p className="text-body-sm text-txt-black-500">Tiada peristiwa direkodkan.</p>;

    return (
        <div className="space-y-3">
            <ol className="relative border-s border-otl-gray-200 ms-3">
                {shown.map((e, i) => {
                    const href = pautanUi(e.link);
                    return (
                        <li key={`${e.date}-${e.type}-${i}`} className="mb-6 ms-6 last:mb-0">
                            <span
                                className={`absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-bg-white ${DOT[TIMELINE_COLOUR[e.type] ?? 'primary']}`}
                            >
                                <svg className="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M10 2a8 8 0 100 16 8 8 0 000-16z" />
                                </svg>
                            </span>
                            <h4 className="text-body-sm font-medium text-txt-black-900 break-words">
                                {href ? (
                                    <Link href={href} className="hover:underline hover:text-txt-primary">
                                        {e.title}
                                    </Link>
                                ) : (
                                    e.title
                                )}
                            </h4>
                            <time dateTime={e.date} className="block text-body-xs text-txt-black-500">
                                {fmtTarikh(e.date, true)} · {e.modul_label}
                            </time>
                        </li>
                    );
                })}
            </ol>
            {items.length > 8 && (
                <button type="button" className="text-body-sm font-medium text-primary-600 hover:underline print:hidden" onClick={() => setAll((v) => !v)}>
                    {all ? 'Papar ringkas' : `Papar semua (${items.length})`}
                </button>
            )}
        </div>
    );
}

function Sections({ list, skip = [] }: { list: Seksyen[]; skip?: string[] }) {
    const shown = list.filter((s) => !skip.includes(s.key));
    // Real sections first, "belum tersedia" placeholders last.
    const sorted = [...shown.filter((s) => s.status !== 'belum_tersedia'), ...shown.filter((s) => s.status === 'belum_tersedia')];
    return (
        <>
            {sorted.map((s) => (
                <ProfilSeksyen key={s.key} seksyen={s} />
            ))}
        </>
    );
}

function TabContent({ profil, tab }: { profil: Profil; tab: string }) {
    const t = profil.tab.find((x) => x.kod === tab) ?? profil.tab[0];
    const lot = profil.lot;
    if (!t) return null;

    if (t.kod === 'kewangan') {
        return (
            <>
                <KewanganPanel kewangan={profil.kewangan} />
                <Sections list={t.seksyen} />
            </>
        );
    }
    if (t.kod === 'ruang') {
        return (
            <>
                <Sections list={t.seksyen.filter((s) => s.key === 'spatial')} />
                <RuangPanel lot={lot} geojson={profil.geojson} />
                <Sections list={t.seksyen} skip={['spatial']} />
            </>
        );
    }
    if (t.kod === 'dokumen') {
        const by = (k: string) => t.seksyen.filter((s) => s.key === k);
        return (
            <>
                <Sections list={by('dokumen')} />
                <DocumentAttachments
                    ownerType="lot_tanah"
                    ownerId={lot.id}
                    sourceModule="pro"
                    ownerLink={`/admin/pro/lot/${lot.id}?tab=dokumen`}
                    title="Lampiran dokumen"
                    readOnly={lot.status_lot === 'arkib'}
                />
                <VersiPanel lotId={lot.id} />
                <Sections list={by('jejak_audit')} />
                <Sections list={by('log_akses')} />
                <Sections list={t.seksyen} skip={['dokumen', 'jejak_audit', 'log_akses']} />
            </>
        );
    }
    return <Sections list={t.seksyen} />;
}

function ProfilPage() {
    const { id } = useParams<{ id: string }>();
    const router = useRouter();
    const params = useSearchParams();
    const can = useCan();
    const queryClient = useQueryClient();
    const [dialog, setDialog] = useState<Dialog>(null);
    const [isu, setIsu] = useState<{ isu: Array<Record<string, unknown>>; tugasan_baharu: number } | null>(null);
    const [busy, setBusy] = useState(false);

    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'lot', id, 'profil'], queryFn: () => proApi.profil(id).then((r) => r.data) });
    const tabs = data?.tab ?? [];
    const active = tabs.find((t) => t.kod === params.get('tab'))?.kod ?? tabs[0]?.kod ?? 'identiti';
    const lot = data?.lot;
    const archived = lot?.status_lot === 'arkib';

    const refresh = () => queryClient.invalidateQueries({ queryKey: ['pro'] });

    async function cetak() {
        try {
            await proApi.cetak(id);
        } catch (e) {
            toast.error(errorMessage(e, 'Cetakan tidak dapat direkodkan.'));
            return;
        }
        window.print();
    }

    async function semak() {
        setBusy(true);
        try {
            const res = await proApi.semakKualiti(id);
            setIsu(res.data);
            refresh();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }

    const title = lot?.no_lot || 'Profil Hartanah 360°';

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Profil Hartanah', href: '/admin/pro/lot' }, { label: 'Lot Tanah', href: '/admin/pro/lot' }, { label: lot?.no_lot ?? 'Profil' }]} />
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2 min-w-0">
                        <p className="text-body-xs font-medium uppercase tracking-wide text-txt-black-500">Profil Hartanah 360°</p>
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight break-words">{title}</h2>
                        {lot && (
                            <>
                                <p className="text-body-sm text-txt-black-700">
                                    {lot.nama_mukim ?? lot.kod_mukim ?? '—'}, {lot.nama_daerah ?? lot.kod_daerah ?? '—'}
                                </p>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant={toneStatusLot(lot.status_lot)}>{STATUS_LOT_LABEL[lot.status_lot] ?? lot.status_lot}</Badge>
                                    <KlasifikasiBadge value={lot.klasifikasi} />
                                    <SpatialBadge spatial={lot.spatial} />
                                </div>
                                {data && <Flags flags={data.bendera.filter((b) => b.seksyen !== 'spatial')} />}
                            </>
                        )}
                    </div>
                    {lot && (
                        <div className="flex flex-wrap gap-2 print:hidden">
                            {can('pro.lot.kemas_kini') && !archived && <LinkButton href={`/admin/pro/lot/${id}/kemas-kini`}>Kemas kini</LinkButton>}
                            <Button variant="secondary" onClick={cetak}>
                                Cetak
                            </Button>
                            {can('pro.lot.eksport') && <ExportMenu onExport={(f) => proApi.eksportProfil(id, f)} />}
                            {can('pro.kualiti.kemas_kini', 'pro.lot.kemas_kini') && !archived && (
                                <Button variant="secondary" onClick={semak} disabled={busy}>
                                    {busy ? 'Menyemak…' : 'Semak kualiti'}
                                </Button>
                            )}
                            {can('pro.arkib.cipta') && !archived && (
                                <Button variant="outline" onClick={() => setDialog('arkib')}>
                                    Arkib
                                </Button>
                            )}
                            {can('pro.arkib.kemas_kini') && archived && (
                                <Button variant="outline" onClick={() => setDialog('pulih')}>
                                    Pulih
                                </Button>
                            )}
                            {can('pro.lot.padam') && lot.status_lot === 'draf' && (
                                <Button variant="danger" onClick={() => setDialog('padam')}>
                                    Padam
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {tabs.length > 0 && (
                <div className="bg-bg-white border-b border-otl-divider print:hidden">
                    <nav aria-label="Tab profil hartanah" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                        {tabs.map((t) => (
                            <Link
                                key={t.kod}
                                href={`/admin/pro/lot/${id}?tab=${t.kod}`}
                                replace
                                scroll={false}
                                aria-current={t.kod === active ? 'page' : undefined}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    t.kod === active
                                        ? 'border-primary-600 text-primary-600'
                                        : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                                }`}
                            >
                                {t.label}
                            </Link>
                        ))}
                    </nav>
                </div>
            )}

            <PageContainer>
                {isLoading ? (
                    <Loading />
                ) : error ? (
                    <Alert type="error">{errorMessage(error)}</Alert>
                ) : data ? (
                    <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <div className="xl:col-span-2 space-y-6 min-w-0">
                            {archived && <Alert type="warning">Lot ini telah diarkib pada {fmtTarikh(lot?.diarkib_pada)}. Rekod dipaparkan untuk rujukan sahaja.</Alert>}
                            {lot && lot.medan_disamarkan.length > 0 && (
                                <Alert type="info">Sebahagian medan diklasifikasikan Sulit/Rahsia dan dipaparkan sebagai &quot;Terhad&quot; mengikut tahap capaian anda.</Alert>
                            )}
                            <TabContent profil={data} tab={active} />
                        </div>
                        <div className="space-y-6 min-w-0">
                            <Card title="Garis masa">
                                <GarisMasa items={data.garis_masa} />
                            </Card>
                        </div>
                    </div>
                ) : null}
            </PageContainer>

            <ReasonModal
                open={dialog === 'arkib'}
                title="Arkib lot"
                description="Cadangan arkib akan dihantar untuk kelulusan mengikut jadual retensi. Lot kekal aktif sehingga diluluskan."
                confirmLabel="Hantar cadangan arkib"
                onClose={() => setDialog(null)}
                onConfirm={async (sebab) => {
                    const res = await proApi.arkibLot(id, sebab);
                    toast.success(res.message);
                    setDialog(null);
                    refresh();
                }}
            />
            <ReasonModal
                open={dialog === 'pulih'}
                title="Pulih lot daripada arkib"
                confirmLabel="Pulih"
                onClose={() => setDialog(null)}
                onConfirm={async (sebab) => {
                    const res = await proApi.pulihLot(id, sebab);
                    toast.success(res.message);
                    setDialog(null);
                    refresh();
                }}
            />
            <ReasonModal
                open={dialog === 'padam'}
                title="Padam lot draf"
                description="Hanya lot berstatus draf boleh dipadam. Tindakan ini direkodkan dalam jejak audit."
                confirmLabel="Padam"
                danger
                onClose={() => setDialog(null)}
                onConfirm={async (sebab) => {
                    const res = await proApi.padamLot(id, sebab);
                    toast.success(res.message);
                    setDialog(null);
                    refresh();
                    router.push('/admin/pro/lot');
                }}
            />

            <Modal open={!!isu} onClose={() => setIsu(null)}>
                <div className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Keputusan semakan kualiti</h2>
                    {isu && isu.isu.length === 0 ? (
                        <Alert type="success">Tiada isu kualiti ditemui bagi lot ini.</Alert>
                    ) : (
                        <ul className="space-y-2">
                            {isu?.isu.map((i, n) => (
                                <li key={n} className="flex items-start gap-2 text-body-sm">
                                    <Badge variant={i.tahap === 'ralat' ? 'danger' : 'warning'}>{String(i.tahap ?? 'isu')}</Badge>
                                    <span>
                                        <strong>{String(i.label ?? i.medan ?? '')}</strong>: {String(i.mesej ?? '')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {isu && isu.tugasan_baharu > 0 && <p className="text-body-sm text-txt-black-700">{isu.tugasan_baharu} tugasan baharu dihantar kepada data steward.</p>}
                    <div className="flex justify-end gap-2">
                        {isu && isu.tugasan_baharu > 0 && (
                            <LinkButton variant="secondary" href="/admin/pro/kualiti?tab=tugasan">
                                Lihat tugasan
                            </LinkButton>
                        )}
                        <Button onClick={() => setIsu(null)}>Tutup</Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}

export default function Page() {
    return (
        <Suspense>
            <ProfilPage />
        </Suspense>
    );
}
