'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { Map as MapLibreMap } from 'maplibre-gl';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, LinkButton } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import {
    formatJarak,
    formatLuas,
    gisApi,
    LABEL_ANALISIS,
    LABEL_KLASIFIKASI,
    muatTurun,
    useBoleh,
    useKatalog,
    useSaya,
    type HasilAnalisis,
    type HasilCarian,
    type JenisAnalisis,
    type KoleksiCiri,
    type Lapisan,
} from '@/lib/gis';
import { PetaSisdata, type KlikCiri } from './PetaSisdata';
import { EntriList, Legenda } from './Legenda';
import { boundsOf, luasPoligon, mapBbox, panjangGaris } from './gaya';
import { geometriLukisan, lukis, pasangLukisan, type ModLukis } from './Lukisan';
import { gubahPng, imejPeta, skalaTeks, UNJURAN } from './cetak';

type Tab = 'lapisan' | 'carian' | 'atribut' | 'ukur' | 'analisis' | 'paparan';

const TABS: Array<{ key: Tab; label: string }> = [
    { key: 'lapisan', label: 'Lapisan' },
    { key: 'carian', label: 'Carian' },
    { key: 'atribut', label: 'Atribut' },
    { key: 'ukur', label: 'Ukur' },
    { key: 'analisis', label: 'Analisis' },
    { key: 'paparan', label: 'Paparan & Cetak' },
];

const inputCls = 'block w-full rounded-md border border-otl-gray-300 bg-bg-white px-3 py-2 text-body-sm text-txt-black-900 shadow-button focus:border-otl-primary-300 focus:outline-none focus:ring focus:ring-fr-primary';

/** Map workspace (SRS-GIS-08..21, 33): layers, search, identify, measure, analysis, saved views, print. */
export function RuangKerjaPeta() {
    const { data: katalog, isLoading } = useKatalog();
    const can = useBoleh();
    const { data: me } = useSaya();
    const [tab, setTab] = useState<Tab>('lapisan');
    const [shown, setShown] = useState<string[] | null>(null);
    const [opacity, setOpacityState] = useState<Record<string, number>>({});
    const [order, setOrder] = useState<string[] | null>(null);
    const [basemap, setBasemap] = useState('osm');
    const [selected, setSelected] = useState<KlikCiri | null>(null);
    const [overlay, setOverlay] = useState<KoleksiCiri | null>(null);
    const [panelOpen, setPanelOpen] = useState(true);
    const mapRef = useRef<MapLibreMap | null>(null);

    // Drawing state shared by Ukur and Analisis.
    const [mode, setMode] = useState<ModLukis>(null);
    const [points, setPoints] = useState<Array<[number, number]>>([]);
    const modeRef = useRef<ModLukis>(null);
    useEffect(() => {
        modeRef.current = mode;
    }, [mode]);

    const layers = useMemo(() => [...(katalog?.lapisan ?? [])].sort((a, b) => a.urutan - b.urutan), [katalog]);
    const visible = shown ?? layers.filter((l) => l.papar_lalai).map((l) => l.kod);
    const stack = order ?? layers.map((l) => l.kod);

    const onReady = useCallback((map: MapLibreMap) => {
        mapRef.current = map;
        pasangLukisan(map);
        map.on('click', (e) => {
            if (!modeRef.current) return;
            const p: [number, number] = [e.lngLat.lng, e.lngLat.lat];
            setPoints((prev) => (modeRef.current === 'titik' ? [p] : [...prev, p]));
        });
        map.on('dblclick', (e) => {
            if (modeRef.current) {
                e.preventDefault();
                setMode(null);
            }
        });
    }, []);

    useEffect(() => {
        if (mapRef.current) lukis(mapRef.current, mode ?? (points.length >= 3 ? 'poligon' : 'jarak'), points);
    }, [points, mode]);

    const onFeatureClick = useCallback((hit: KlikCiri) => {
        if (modeRef.current) return;
        setSelected(hit);
        setTab('atribut');
        setPanelOpen(true);
    }, []);

    const toggle = (kod: string, on: boolean) => setShown(on ? [...visible, kod] : visible.filter((k) => k !== kod));
    const move = (kod: string, dir: -1 | 1) => {
        const s = [...stack];
        const i = s.indexOf(kod);
        const j = i + dir;
        if (i < 0 || j < 0 || j >= s.length) return;
        [s[i], s[j]] = [s[j], s[i]];
        setOrder(s);
    };

    const fit = (bbox: [number, number, number, number] | null | undefined, maxZoom = 17) => {
        if (!bbox || !mapRef.current) return;
        mapRef.current.fitBounds(
            [
                [bbox[0], bbox[1]],
                [bbox[2], bbox[3]],
            ],
            { padding: 60, maxZoom, duration: 600 },
        );
    };

    if (isLoading || !katalog) {
        return (
            <div className="flex items-center gap-2 p-8 text-body-sm text-txt-black-500">
                <Spinner size="sm" /> Memuatkan katalog lapisan…
            </div>
        );
    }

    return (
        <div className="flex flex-col lg:flex-row lg:h-[calc(100vh-11.5rem)] min-h-0">
            <aside data-panduan="gis-panel" aria-label="Panel peta GIS" className={`order-2 lg:order-1 w-full lg:w-[400px] lg:flex-shrink-0 border-t lg:border-t-0 lg:border-r border-otl-divider bg-bg-white flex flex-col min-h-0 ${panelOpen ? '' : 'lg:w-0 lg:overflow-hidden'}`}>
                <nav aria-label="Panel peta" className="flex gap-4 overflow-x-auto border-b border-otl-divider px-4 -mb-px">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            data-panduan={`gis-tab-${t.key}`}
                            onClick={() => setTab(t.key)}
                            aria-current={tab === t.key ? 'page' : undefined}
                            className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                tab === t.key ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </nav>
                <div className="flex-1 overflow-y-auto p-4 space-y-4">
                    {tab === 'lapisan' && (
                        <PanelLapisan
                            layers={layers}
                            visible={visible}
                            stack={stack}
                            opacity={opacity}
                            basemap={basemap}
                            onBasemap={setBasemap}
                            onToggle={toggle}
                            onMove={move}
                            onOpacity={(k, v) => setOpacityState((o) => ({ ...o, [k]: v }))}
                        />
                    )}
                    {tab === 'carian' && (
                        <PanelCarian
                            onPick={(r) => {
                                if (r.bbox) fit(r.bbox, r.jenis === 'lot' ? 18 : 14);
                                else mapRef.current?.flyTo({ center: [r.lng, r.lat], zoom: 16 });
                                setOverlay({ type: 'FeatureCollection', features: [{ type: 'Feature', geometry: { type: 'Point', coordinates: [r.lng, r.lat] }, properties: {} }] });
                            }}
                        />
                    )}
                    {tab === 'atribut' && <PanelAtribut hit={selected} canAnalyse={can('gis.analisis.cipta')} onAnalyse={() => setTab('analisis')} />}
                    {tab === 'ukur' && <PanelUkur mode={mode} points={points} setMode={setMode} setPoints={setPoints} canAnalyse={can('gis.analisis.cipta')} />}
                    {tab === 'analisis' && (
                        <PanelAnalisis
                            layers={layers}
                            selectedLot={selected?.lapisan.kod.startsWith('gis.') && selected.lapisan.jenis_geometri === 'polygon' && selected.lapisan.kumpulan !== 'rujukan' ? String(selected.properties.id) : null}
                            drawn={geometriLukisan('poligon', points)}
                            startDraw={() => {
                                setPoints([]);
                                setMode('poligon');
                            }}
                            canExport={can('gis.analisis.eksport')}
                            map={mapRef}
                            onResult={(r) => {
                                setOverlay(r.geometri);
                                const b = boundsOf(r.geometri.features.map((f) => f.geometry));
                                if (b) fit(b, 16);
                            }}
                            onShowLayers={(keys) => setShown(Array.from(new Set([...visible, ...keys])))}
                        />
                    )}
                    {tab === 'paparan' && (
                        <PanelPaparan
                            map={mapRef}
                            visible={visible}
                            basemap={basemap}
                            layers={layers}
                            user={me?.name ?? ''}
                            canPrint={can('gis.peta.eksport')}
                            selected={selected}
                            onOpen={(v) => {
                                setShown(v.lapisan);
                                setBasemap(v.peta_asas);
                                mapRef.current?.jumpTo({ center: v.pusat, zoom: Number(v.zum) });
                            }}
                        />
                    )}
                </div>
            </aside>

            <section data-panduan="gis-peta" className="order-1 lg:order-2 relative flex-none lg:flex-1 min-w-0 h-[60vh] lg:h-auto lg:min-h-0">
                <button
                    type="button"
                    onClick={() => setPanelOpen((o) => !o)}
                    className="hidden lg:block absolute left-2 top-2 z-10 rounded-md border border-otl-gray-200 bg-bg-white px-2 py-1 text-body-xs shadow-card"
                    aria-expanded={panelOpen}
                >
                    {panelOpen ? '‹ Sembunyi panel' : '› Panel'}
                </button>
                {mode && (
                    <div className="absolute inset-x-0 top-2 z-10 flex justify-center pointer-events-none">
                        <span className="pointer-events-auto rounded-md bg-bg-white/95 px-3 py-1.5 text-body-xs shadow-card">
                            {mode === 'titik' ? 'Klik untuk pilih titik.' : 'Klik pada peta untuk menambah bucu; klik dua kali untuk selesai.'}
                            <button type="button" className="ml-2 font-semibold text-txt-primary" onClick={() => setMode(null)}>
                                Selesai
                            </button>
                        </span>
                    </div>
                )}
                <PetaSisdata
                    layers={visible}
                    opacity={opacity}
                    order={stack}
                    basemap={basemap}
                    overlay={overlay}
                    height="100%"
                    className="h-full [&>div:first-child]:h-full [&>div:first-child]:rounded-none [&>div:first-child]:border-0"
                    showLegend={false}
                    onReady={onReady}
                    onFeatureClick={onFeatureClick}
                    label="Peta GIS SISDATA"
                />
            </section>
        </div>
    );
}

// ---- Layers ---------------------------------------------------------------------------

function PanelLapisan({
    layers,
    visible,
    stack,
    opacity,
    basemap,
    onBasemap,
    onToggle,
    onMove,
    onOpacity,
}: {
    layers: Lapisan[];
    visible: string[];
    stack: string[];
    opacity: Record<string, number>;
    basemap: string;
    onBasemap: (k: string) => void;
    onToggle: (kod: string, on: boolean) => void;
    onMove: (kod: string, dir: -1 | 1) => void;
    onOpacity: (kod: string, v: number) => void;
}) {
    const { data: katalog } = useKatalog();
    const groups = katalog?.kumpulan ?? [];
    const ordered = [...layers].sort((a, b) => stack.indexOf(b.kod) - stack.indexOf(a.kod));

    return (
        <>
            <fieldset className="space-y-2">
                <legend className="text-body-sm font-semibold text-txt-black-900">Peta asas</legend>
                {katalog?.peta_asas.map((a) => (
                    <label key={a.kod} className={`flex items-start gap-2 text-body-sm ${a.aktif ? '' : 'text-txt-black-500'}`}>
                        <input type="radio" name="peta-asas" value={a.kod} checked={basemap === a.kod} disabled={!a.aktif} onChange={() => onBasemap(a.kod)} className="mt-1" />
                        <span>
                            {a.nama}
                            {a.perlu_lesen && <span className="block text-body-xs text-txt-black-500">{a.aktif ? 'Berlesen' : a.catatan}</span>}
                        </span>
                    </label>
                ))}
            </fieldset>

            {groups.map((g) => {
                const inGroup = ordered.filter((l) => l.kumpulan === g.kod);
                if (inGroup.length === 0) return null;
                return (
                    <fieldset key={g.kod} className="space-y-2">
                        <legend className="text-body-sm font-semibold text-txt-black-900">{g.nama}</legend>
                        {inGroup.map((l) => {
                            const on = visible.includes(l.kod);
                            return (
                                <div key={l.kod} className="rounded-md border border-otl-gray-200 p-2">
                                    <div className="flex items-center gap-2">
                                        <input id={`lp-${l.kod}`} type="checkbox" checked={on} onChange={(e) => onToggle(l.kod, e.target.checked)} />
                                        <label htmlFor={`lp-${l.kod}`} className="flex-1 text-body-sm text-txt-black-900">
                                            {l.nama}
                                        </label>
                                        {(l.klasifikasi === 'sulit' || l.klasifikasi === 'rahsia') && <Badge variant="danger">{LABEL_KLASIFIKASI[l.klasifikasi]}</Badge>}
                                        <button type="button" aria-label={`Naikkan ${l.nama}`} className="px-1 text-txt-black-500 hover:text-txt-black-900" onClick={() => onMove(l.kod, 1)}>
                                            ▲
                                        </button>
                                        <button type="button" aria-label={`Turunkan ${l.nama}`} className="px-1 text-txt-black-500 hover:text-txt-black-900" onClick={() => onMove(l.kod, -1)}>
                                            ▼
                                        </button>
                                    </div>
                                    {on && (
                                        <div className="mt-2 space-y-2 pl-6">
                                            <label className="flex items-center gap-2 text-body-xs text-txt-black-700">
                                                Ketelusan
                                                <input
                                                    type="range"
                                                    min={0}
                                                    max={1}
                                                    step={0.05}
                                                    value={opacity[l.kod] ?? l.ketelusan}
                                                    onChange={(e) => onOpacity(l.kod, Number(e.target.value))}
                                                    className="flex-1"
                                                    aria-label={`Ketelusan ${l.nama}`}
                                                />
                                            </label>
                                            <EntriList entri={l.legenda.entri} point={l.jenis_geometri === 'point'} outline={!!l.gaya.garis_sahaja} dashed={l.gaya.garis_putus ? 'TIADA' : undefined} />
                                            {l.metadata?.sumber_data && <p className="text-body-xs text-txt-black-500">Sumber: {l.metadata.sumber_data}</p>}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </fieldset>
                );
            })}

            <details className="rounded-md border border-otl-gray-200 p-3">
                <summary className="cursor-pointer text-body-sm font-semibold text-txt-black-900">10 lapisan wajib (RFP 8.1.4)</summary>
                <ol className="mt-2 space-y-3">
                    {katalog?.wajib.map((s) => (
                        <li key={s.kod}>
                            <div className="flex items-center gap-2 text-body-sm">
                                <span className="text-txt-black-500">{s.bil}.</span>
                                <span className="flex-1">{s.nama}</span>
                                {s.status === 'menunggu_modul' ? (
                                    <Badge variant="warning">Menunggu {s.modul}</Badge>
                                ) : s.dibenarkan.length === 0 ? (
                                    <Badge variant="neutral">Tiada capaian</Badge>
                                ) : (
                                    <Badge variant={s.status === 'interim' ? 'info' : 'success'}>{s.status === 'interim' ? 'Interim' : 'Tersedia'}</Badge>
                                )}
                            </div>
                            <EntriList entri={s.legenda} muted={s.status === 'menunggu_modul'} />
                        </li>
                    ))}
                </ol>
            </details>
        </>
    );
}

// ---- Search ---------------------------------------------------------------------------

function PanelCarian({ onPick }: { onPick: (r: HasilCarian) => void }) {
    const [q, setQ] = useState('');
    const [term, setTerm] = useState('');
    const { data, isFetching, error } = useQuery({ queryKey: ['gis', 'cari', term], queryFn: () => gisApi.cari(term), enabled: term.length >= 2 });
    const label: Record<string, string> = { lot: 'Lot', daerah: 'Daerah', mukim: 'Mukim', pbt: 'PBT', tempat_ibadat: 'Masjid / surau', alamat: 'Alamat' };

    return (
        <div className="space-y-3">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    setTerm(q.trim());
                }}
                className="flex gap-2"
                role="search"
            >
                <label htmlFor="gis-cari" className="sr-only">
                    Cari lokasi
                </label>
                <input id="gis-cari" className={inputCls} placeholder="No. lot, mukim, daerah, masjid…" value={q} onChange={(e) => setQ(e.target.value)} />
                <Button type="submit" size="sm">
                    Cari
                </Button>
            </form>
            {isFetching && <Spinner size="sm" />}
            {error && <p className="text-body-sm text-txt-danger">Carian gagal.</p>}
            <ul className="divide-y divide-otl-divider">
                {data?.data.map((r, i) => (
                    <li key={`${r.jenis}-${r.id ?? i}`} className="py-2">
                        <button type="button" onClick={() => onPick(r)} className="w-full text-left">
                            <span className="block text-body-sm font-medium text-txt-black-900">{r.label}</span>
                            <span className="text-body-xs text-txt-black-500">{label[r.jenis] ?? r.jenis}</span>
                        </button>
                        {r.pautan && (
                            <Link href={r.pautan} className="text-body-xs text-txt-primary hover:underline">
                                Profil Hartanah 360° →
                            </Link>
                        )}
                    </li>
                ))}
                {term && data?.data.length === 0 && <li className="py-2 text-body-sm text-txt-black-500">Tiada hasil.</li>}
            </ul>
        </div>
    );
}

// ---- Identify / attributes -------------------------------------------------------------

const HIDDEN_PROPS = new Set(['pautan', 'label']);

function PanelAtribut({ hit, canAnalyse, onAnalyse }: { hit: KlikCiri | null; canAnalyse: boolean; onAnalyse: () => void }) {
    if (!hit) return <p className="text-body-sm text-txt-black-500">Klik pada ciri di peta untuk melihat atributnya.</p>;
    const props = Object.entries(hit.properties).filter(([k]) => !HIDDEN_PROPS.has(k));
    const isLot = hit.lapisan.kumpulan !== 'rujukan' && typeof hit.properties.pautan === 'string' && String(hit.properties.pautan).startsWith('/admin/pro/lot/');

    return (
        <div className="space-y-3">
            <div>
                <p className="text-body-xs text-txt-black-500">{hit.lapisan.nama}</p>
                <h3 className="text-body-md font-semibold text-txt-black-900">{String(hit.properties.label ?? hit.properties.id ?? '—')}</h3>
            </div>
            <div className="flex flex-wrap gap-2">
                {typeof hit.properties.pautan === 'string' && hit.properties.pautan && (
                    <LinkButton href={hit.properties.pautan} size="sm">
                        {isLot ? 'Profil Hartanah 360°' : 'Buka rekod'}
                    </LinkButton>
                )}
                {isLot && (
                    <LinkButton href={`/admin/gis/lot/${String(hit.properties.id)}/versi`} size="sm" variant="secondary">
                        Versi geometri
                    </LinkButton>
                )}
                {isLot && canAnalyse && (
                    <Button size="sm" variant="outline" onClick={onAnalyse}>
                        Analisis lot ini
                    </Button>
                )}
            </div>
            <dl className="divide-y divide-otl-divider text-body-sm">
                {props.map(([k, v]) => (
                    <div key={k} className="grid grid-cols-5 gap-2 py-1.5">
                        <dt className="col-span-2 break-words text-txt-black-500">{k.replace(/_/g, ' ')}</dt>
                        <dd className="col-span-3 break-words text-txt-black-900">{v === null || v === undefined || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v)}</dd>
                    </div>
                ))}
            </dl>
            <p className="text-body-xs text-txt-black-500">
                Koordinat klik: {hit.lngLat[1].toFixed(6)}, {hit.lngLat[0].toFixed(6)} (WGS 84)
            </p>
        </div>
    );
}

// ---- Measure (SRS-GIS-10) ----------------------------------------------------------------

function PanelUkur({
    mode,
    points,
    setMode,
    setPoints,
    canAnalyse,
}: {
    mode: ModLukis;
    points: Array<[number, number]>;
    setMode: (m: ModLukis) => void;
    setPoints: (p: Array<[number, number]>) => void;
    canAnalyse: boolean;
}) {
    const [kind, setKind] = useState<'jarak' | 'luas'>('jarak');
    const tepat = useMutation({ mutationFn: (g: GeoJSON.Geometry) => gisApi.analisis('keluasan', { sumber: { geometri: g } }) });
    const length = panjangGaris(points);
    const area = kind === 'luas' ? luasPoligon(points) : 0;
    const perimeter = kind === 'luas' && points.length >= 3 ? panjangGaris([...points, points[0]]) : 0;

    return (
        <div className="space-y-3">
            <div className="flex gap-2">
                {(['jarak', 'luas'] as const).map((k) => (
                    <Button
                        key={k}
                        size="sm"
                        variant={kind === k ? 'primary' : 'secondary'}
                        onClick={() => {
                            setKind(k);
                            setPoints([]);
                            setMode(k);
                        }}
                    >
                        {k === 'jarak' ? 'Ukur jarak' : 'Ukur keluasan'}
                    </Button>
                ))}
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                        setPoints([]);
                        setMode(null);
                        tepat.reset();
                    }}
                >
                    Kosongkan
                </Button>
            </div>
            <p className="text-body-xs text-txt-black-500">{mode ? 'Sedang melukis — klik pada peta.' : 'Pilih alat, kemudian klik pada peta.'}</p>
            <dl className="grid grid-cols-2 gap-2 text-body-sm">
                <dt className="text-txt-black-500">Bucu</dt>
                <dd>{points.length}</dd>
                {kind === 'jarak' ? (
                    <>
                        <dt className="text-txt-black-500">Jarak</dt>
                        <dd className="font-semibold">{formatJarak(length)}</dd>
                    </>
                ) : (
                    <>
                        <dt className="text-txt-black-500">Keluasan</dt>
                        <dd className="font-semibold">{formatLuas(area)}</dd>
                        <dt className="text-txt-black-500">Perimeter</dt>
                        <dd>{formatJarak(perimeter)}</dd>
                    </>
                )}
            </dl>
            <p className="text-body-xs text-txt-black-500">Kiraan geodesi (sfera WGS 84) di pelayar.</p>
            {kind === 'luas' && points.length >= 3 && canAnalyse && (
                <div className="space-y-2">
                    <Button size="sm" variant="outline" onClick={() => tepat.mutate(geometriLukisan('poligon', points) as GeoJSON.Geometry)} disabled={tepat.isPending}>
                        Kira tepat (elipsoid & Cassini)
                    </Button>
                    {tepat.data && (
                        <dl className="grid grid-cols-2 gap-1 text-body-xs">
                            <dt className="text-txt-black-500">Geodesi (elipsoid)</dt>
                            <dd>{formatLuas(Number(tepat.data.ringkasan.luas_m2))}</dd>
                            <dt className="text-txt-black-500">Cassini Selangor (EPSG:3380)</dt>
                            <dd>{formatLuas(Number(tepat.data.ringkasan.luas_cassini_m2))}</dd>
                            <dt className="text-txt-black-500">Perimeter (Cassini)</dt>
                            <dd>{formatJarak(Number(tepat.data.ringkasan.perimeter_cassini_m))}</dd>
                        </dl>
                    )}
                </div>
            )}
        </div>
    );
}

// ---- Analysis (SRS-GIS-15..21) -----------------------------------------------------------

function PanelAnalisis({
    layers,
    selectedLot,
    drawn,
    startDraw,
    canExport,
    map,
    onResult,
    onShowLayers,
}: {
    layers: Lapisan[];
    selectedLot: string | null;
    drawn: GeoJSON.Geometry | null;
    startDraw: () => void;
    canExport: boolean;
    map: React.RefObject<MapLibreMap | null>;
    onResult: (r: HasilAnalisis) => void;
    onShowLayers: (keys: string[]) => void;
}) {
    const qc = useQueryClient();
    const [jenis, setJenis] = useState<JenisAnalisis>('penimbal');
    const [sumber, setSumber] = useState<'lot' | 'lukisan'>('lot');
    const [lotId, setLotId] = useState(selectedLot ?? '');
    const [jarak, setJarak] = useState('500');
    const [sasaran, setSasaran] = useState('gis.lot');
    const [lapisanB, setLapisanB] = useState('gis.sempadan_mukim');
    const [modKedekatan, setModKedekatan] = useState<'terdekat' | 'dalam_jarak'>('dalam_jarak');
    const [kawasan, setKawasan] = useState<'poligon' | 'sempadan'>('poligon');
    const [kodSempadan, setKodSempadan] = useState('');
    const [jenisSempadan, setJenisSempadan] = useState('mukim');
    const [haba, setHaba] = useState(false);
    const [namaSenario, setNamaSenario] = useState('');
    const [kongsi, setKongsi] = useState<'peribadi' | 'semua'>('peribadi');
    const [msg, setMsg] = useState<string | null>(null);

    // Follow the lot picked on the map (adjust-state-during-render pattern).
    const [prevSelected, setPrevSelected] = useState(selectedLot);
    if (selectedLot !== prevSelected) {
        setPrevSelected(selectedLot);
        if (selectedLot) setLotId(selectedLot);
    }

    const src = sumber === 'lot' ? { lot_id: lotId } : { geometri: drawn };
    const parameter = (): Record<string, unknown> => {
        switch (jenis) {
            case 'penimbal':
                return { sumber: src, jarak_m: Number(jarak), lapisan: [sasaran] };
            case 'tindihan':
                return { lapisan_a: 'gis.lot', lapisan_b: [lapisanB] };
            case 'pertanyaan':
                return { lapisan: sasaran, kawasan: kawasan === 'poligon' ? { poligon: drawn } : { sempadan: { jenis: jenisSempadan, kod: kodSempadan } } };
            case 'keluasan':
                return { sumber: src };
            case 'kedekatan':
                return modKedekatan === 'terdekat'
                    ? { mod: 'terdekat', sumber: src, lapisan_sasaran: sasaran, bilangan: 10 }
                    : { mod: 'dalam_jarak', lapisan_sumber: 'gis.lot', lapisan_sasaran: sasaran, jarak_m: Number(jarak) };
            case 'kluster':
                return { lapisan: 'gis.lot', jarak_m: Number(jarak), minimum: 3 };
        }
    };

    const run = useMutation({
        mutationFn: () => gisApi.analisis(jenis, parameter()),
        onSuccess: (r) => {
            setMsg(null);
            onResult(r);
            onShowLayers(['gis.lot']);
        },
        onError: (e: Error) => setMsg(e.message),
    });
    const save = useMutation({
        mutationFn: () => gisApi.simpanSenario({ nama: namaSenario, jenis, parameter: parameter(), kebenaran_kongsi: kongsi }),
        onSuccess: () => {
            setMsg('Senario disimpan.');
            setNamaSenario('');
            qc.invalidateQueries({ queryKey: ['gis', 'senario'] });
        },
        onError: (e: Error) => setMsg(e.message),
    });
    const senarai = useQuery({ queryKey: ['gis', 'senario'], queryFn: gisApi.senarai_senario });

    // GIS-21 heat map of lot risk (client-side MapLibre heatmap over lot centres in view).
    useEffect(() => {
        const m = map.current;
        if (!m) return;
        if (!haba) {
            if (m.getLayer('haba')) m.removeLayer('haba');
            if (m.getSource('haba')) m.removeSource('haba');
            return;
        }
        const has = layers.some((l) => l.kod === 'gis.risiko');
        gisApi
            .ciri(has ? 'gis.risiko' : 'gis.lot', { bbox: mapBbox(m), zoom: m.getZoom() })
            .then((fc) => {
                const pts = fc.features
                    .map((f) => {
                        const b = boundsOf([f.geometry]);
                        return b ? { type: 'Feature' as const, properties: { w: Number(f.properties.skor_risiko ?? 1) + 1 }, geometry: { type: 'Point' as const, coordinates: [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2] } } : null;
                    })
                    .filter(Boolean);
                const data = { type: 'FeatureCollection', features: pts };
                if (m.getSource('haba')) (m.getSource('haba') as unknown as { setData: (d: unknown) => void }).setData(data);
                else {
                    m.addSource('haba', { type: 'geojson', data: data as never });
                    m.addLayer({
                        id: 'haba', type: 'heatmap', source: 'haba',
                        paint: {
                            'heatmap-weight': ['interpolate', ['linear'], ['get', 'w'], 1, 0.2, 8, 1] as never,
                            'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 8, 12, 14, 30] as never,
                            'heatmap-color': ['interpolate', ['linear'], ['heatmap-density'], 0, 'rgba(22,163,74,0)', 0.3, '#16A34A', 0.55, '#EAB308', 0.75, '#F97316', 1, '#DC2626'] as never,
                            'heatmap-opacity': 0.75,
                        },
                    });
                }
            })
            .catch(() => setMsg('Peta haba tidak dapat dijana.'));
    }, [haba, map, layers]);

    const result = run.data;
    const layerOptions = layers.map((l) => (
        <option key={l.kod} value={l.kod}>
            {l.nama}
        </option>
    ));
    const needsSource = jenis === 'penimbal' || jenis === 'keluasan' || (jenis === 'kedekatan' && modKedekatan === 'terdekat');
    const needsDrawing = (needsSource && sumber === 'lukisan') || (jenis === 'pertanyaan' && kawasan === 'poligon');

    return (
        <div className="space-y-4">
            <label className="block text-body-sm">
                <span className="mb-1 block font-medium text-txt-black-900">Jenis analisis</span>
                <select className={inputCls} value={jenis} onChange={(e) => setJenis(e.target.value as JenisAnalisis)}>
                    {(Object.keys(LABEL_ANALISIS) as JenisAnalisis[]).map((k) => (
                        <option key={k} value={k}>
                            {LABEL_ANALISIS[k]}
                        </option>
                    ))}
                </select>
            </label>

            {jenis === 'kedekatan' && (
                <div className="flex gap-3 text-body-sm">
                    <label className="flex items-center gap-1">
                        <input type="radio" checked={modKedekatan === 'dalam_jarak'} onChange={() => setModKedekatan('dalam_jarak')} /> Lot dalam jarak
                    </label>
                    <label className="flex items-center gap-1">
                        <input type="radio" checked={modKedekatan === 'terdekat'} onChange={() => setModKedekatan('terdekat')} /> Terdekat dari sumber
                    </label>
                </div>
            )}

            {needsSource && (
                <fieldset className="space-y-2 text-body-sm">
                    <legend className="font-medium text-txt-black-900">Sumber</legend>
                    <label className="flex items-center gap-2">
                        <input type="radio" checked={sumber === 'lot'} onChange={() => setSumber('lot')} /> Lot dipilih di peta
                    </label>
                    {sumber === 'lot' && <input className={inputCls} value={lotId} onChange={(e) => setLotId(e.target.value)} placeholder="Klik lot di peta (tab Atribut)" aria-label="ID lot" />}
                    <label className="flex items-center gap-2">
                        <input type="radio" checked={sumber === 'lukisan'} onChange={() => setSumber('lukisan')} /> Geometri dilukis
                    </label>
                </fieldset>
            )}

            {jenis === 'pertanyaan' && (
                <fieldset className="space-y-2 text-body-sm">
                    <legend className="font-medium text-txt-black-900">Kawasan</legend>
                    <label className="flex items-center gap-2">
                        <input type="radio" checked={kawasan === 'poligon'} onChange={() => setKawasan('poligon')} /> Lukis poligon
                    </label>
                    <label className="flex items-center gap-2">
                        <input type="radio" checked={kawasan === 'sempadan'} onChange={() => setKawasan('sempadan')} /> Sempadan pentadbiran
                    </label>
                    {kawasan === 'sempadan' && (
                        <div className="flex gap-2">
                            <select className={inputCls} value={jenisSempadan} onChange={(e) => setJenisSempadan(e.target.value)} aria-label="Jenis sempadan">
                                <option value="daerah">Daerah</option>
                                <option value="mukim">Mukim</option>
                                <option value="pbt">PBT</option>
                            </select>
                            <input className={inputCls} value={kodSempadan} onChange={(e) => setKodSempadan(e.target.value)} placeholder="Kod (cth. 100803)" aria-label="Kod sempadan" />
                        </div>
                    )}
                </fieldset>
            )}

            {needsDrawing && (
                <div className="flex items-center gap-2">
                    <Button size="sm" variant="secondary" onClick={startDraw}>
                        Lukis poligon di peta
                    </Button>
                    <span className="text-body-xs text-txt-black-500">{drawn && drawn.type === 'Polygon' ? 'Poligon sedia.' : 'Belum dilukis.'}</span>
                </div>
            )}

            {(jenis === 'penimbal' || jenis === 'pertanyaan' || jenis === 'kedekatan') && (
                <label className="block text-body-sm">
                    <span className="mb-1 block font-medium text-txt-black-900">{jenis === 'pertanyaan' ? 'Lapisan dicari' : 'Lapisan sasaran'}</span>
                    <select className={inputCls} value={sasaran} onChange={(e) => setSasaran(e.target.value)}>
                        {layerOptions}
                    </select>
                </label>
            )}
            {jenis === 'tindihan' && (
                <label className="block text-body-sm">
                    <span className="mb-1 block font-medium text-txt-black-900">Lot ditindih dengan lapisan</span>
                    <select className={inputCls} value={lapisanB} onChange={(e) => setLapisanB(e.target.value)}>
                        {layerOptions}
                    </select>
                </label>
            )}
            {(jenis === 'penimbal' || jenis === 'kluster' || (jenis === 'kedekatan' && modKedekatan === 'dalam_jarak')) && (
                <label className="block text-body-sm">
                    <span className="mb-1 block font-medium text-txt-black-900">Jarak (meter)</span>
                    <input className={inputCls} type="number" min={1} max={100000} value={jarak} onChange={(e) => setJarak(e.target.value)} />
                </label>
            )}

            <div className="flex flex-wrap gap-2">
                <Button onClick={() => run.mutate()} disabled={run.isPending}>
                    {run.isPending ? 'Menganalisis…' : 'Jalankan'}
                </Button>
                {jenis === 'kluster' && (
                    <label className="flex items-center gap-2 text-body-sm">
                        <input type="checkbox" checked={haba} onChange={(e) => setHaba(e.target.checked)} /> Peta haba risiko
                    </label>
                )}
            </div>
            {msg && <p className="text-body-sm text-txt-danger" role="alert">{msg}</p>}

            {result && (
                <div className="space-y-3 rounded-md border border-otl-gray-200 p-3">
                    <p className="text-body-sm font-semibold text-txt-black-900">{LABEL_ANALISIS[result.jenis]}</p>
                    <dl className="grid grid-cols-2 gap-1 text-body-xs">
                        {Object.entries(result.ringkasan)
                            .filter(([, v]) => typeof v !== 'object')
                            .map(([k, v]) => (
                                <div key={k} className="contents">
                                    <dt className="text-txt-black-500">{k.replace(/_/g, ' ')}</dt>
                                    <dd className="text-txt-black-900">{String(v ?? '—')}</dd>
                                </div>
                            ))}
                    </dl>
                    {result.jenis === 'keluasan' && result.ringkasan.unjuran ? (
                        <p className="text-body-xs text-txt-black-500">{Object.values(result.ringkasan.unjuran as Record<string, string>).join(' · ')}</p>
                    ) : null}
                    {result.hasil.length > 0 && (
                        <div className="max-h-72 overflow-y-auto">
                            <table className="w-full text-body-xs">
                                <thead className="sticky top-0 bg-bg-washed">
                                    <tr className="[&>th]:px-2 [&>th]:py-1 [&>th]:text-left">
                                        <th>Ciri</th>
                                        <th>Nilai</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-otl-divider">
                                    {result.hasil.slice(0, 300).map((r, i) => (
                                        <tr key={`${r.id}-${i}`} className="[&>td]:px-2 [&>td]:py-1">
                                            <td>{r.pautan ? <Link href={r.pautan} className="text-txt-primary hover:underline">{r.label ?? r.id}</Link> : (r.label ?? r.id)}</td>
                                            <td>
                                                {r.jarak_m !== undefined && formatJarak(Number(r.jarak_m))}
                                                {r.luas_tindih_m2 !== undefined && `${formatLuas(Number(r.luas_tindih_m2))} · ${r.label_b ?? ''}`}
                                                {r.luas_m2 !== undefined && formatLuas(Number(r.luas_m2))}
                                                {r.bilangan !== undefined && `${String(r.bilangan)} lot`}
                                                {r.sasaran_terdekat ? ` · ${String(r.sasaran_terdekat)}` : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {canExport && (
                        <div className="flex gap-2">
                            {(['csv', 'xlsx'] as const).map((f) => (
                                <Button
                                    key={f}
                                    size="sm"
                                    variant="secondary"
                                    onClick={() =>
                                        muatTurun('/gis/analisis/eksport', { method: 'POST', body: JSON.stringify({ jenis, parameter: parameter(), format: f }) }, `analisis.${f}`).catch((e: Error) => setMsg(e.message))
                                    }
                                >
                                    Eksport {f.toUpperCase()}
                                </Button>
                            ))}
                        </div>
                    )}
                    <div className="space-y-2 border-t border-otl-divider pt-3">
                        <input className={inputCls} value={namaSenario} onChange={(e) => setNamaSenario(e.target.value)} placeholder="Nama senario" aria-label="Nama senario" />
                        <div className="flex items-center gap-2">
                            <select className={inputCls} value={kongsi} onChange={(e) => setKongsi(e.target.value as 'peribadi' | 'semua')} aria-label="Perkongsian">
                                <option value="peribadi">Peribadi</option>
                                <option value="semua">Kongsi (ikut kebenaran)</option>
                            </select>
                            <Button size="sm" onClick={() => save.mutate()} disabled={!namaSenario || save.isPending}>
                                Simpan
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <div>
                <p className="mb-1 text-body-sm font-semibold text-txt-black-900">Senario tersimpan</p>
                <ul className="divide-y divide-otl-divider text-body-sm">
                    {senarai.data?.data.map((s) => (
                        <li key={s.id} className="flex items-center justify-between gap-2 py-1.5">
                            <button
                                type="button"
                                className="text-left text-txt-primary hover:underline"
                                onClick={() =>
                                    gisApi
                                        .bukaSenario(s.id)
                                        .then((r) => onResult(r.hasil))
                                        .catch((e: Error) => setMsg(e.message))
                                }
                            >
                                {s.nama}
                            </button>
                            <span className="text-body-xs text-txt-black-500">
                                {LABEL_ANALISIS[s.jenis_analisis]}
                                {!s.milik_saya && ' · dikongsi'}
                            </span>
                        </li>
                    ))}
                    {senarai.data?.data.length === 0 && <li className="py-1.5 text-txt-black-500">Tiada senario.</li>}
                </ul>
            </div>
        </div>
    );
}

// ---- Saved views + print / export (SRS-GIS-33) -------------------------------------------

function PanelPaparan({
    map,
    visible,
    basemap,
    layers,
    user,
    canPrint,
    selected,
    onOpen,
}: {
    map: React.RefObject<MapLibreMap | null>;
    visible: string[];
    basemap: string;
    layers: Lapisan[];
    user: string;
    canPrint: boolean;
    selected: KlikCiri | null;
    onOpen: (v: { lapisan: string[]; peta_asas: string; pusat: [number, number]; zum: string | number }) => void;
}) {
    const qc = useQueryClient();
    const [nama, setNama] = useState('');
    const [tajuk, setTajuk] = useState('Peta Hartanah MAIS');
    const [msg, setMsg] = useState<string | null>(null);
    const views = useQuery({ queryKey: ['gis', 'paparan'], queryFn: gisApi.paparan });
    const shownLayers = layers.filter((l) => visible.includes(l.kod));

    const saveView = useMutation({
        mutationFn: () => {
            const m = map.current;
            if (!m) throw new Error('Peta belum sedia.');
            const c = m.getCenter();
            return gisApi.simpanPaparan({ nama, lapisan: visible, pusat: [c.lng, c.lat], zum: m.getZoom(), peta_asas: basemap, dikongsi: false });
        },
        onSuccess: () => {
            setNama('');
            qc.invalidateQueries({ queryKey: ['gis', 'paparan'] });
        },
        onError: (e: Error) => setMsg(e.message),
    });

    const png = async () => {
        const m = map.current;
        if (!m) return;
        try {
            const url = await gubahPng(m, { tajuk, pengguna: user, layers: shownLayers });
            const c = m.getCenter();
            await gisApi.logCetak({ tajuk, format: 'png', lapisan: visible, pusat: [c.lng, c.lat], zum: m.getZoom() });
            const a = document.createElement('a');
            a.href = url;
            a.download = `peta-${Date.now()}.png`;
            a.click();
        } catch (e) {
            setMsg((e as Error).message);
        }
    };

    const pdf = async () => {
        const m = map.current;
        if (!m) return;
        const c = m.getCenter();
        const atribut = selected ? [Object.fromEntries(Object.entries(selected.properties).filter(([k]) => k !== 'pautan'))] : [];
        try {
            await muatTurun(
                '/gis/cetak/pdf',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        tajuk,
                        imej: imejPeta(m),
                        skala: skalaTeks(m),
                        unjuran: UNJURAN,
                        pusat: [c.lng, c.lat],
                        zum: m.getZoom(),
                        lapisan: visible,
                        legenda: shownLayers.map((l) => ({ nama: l.nama, entri: l.legenda.entri.slice(0, 40).map((e) => ({ label: e.label, warna: e.warna })) })),
                        atribut,
                    }),
                },
                'peta.pdf',
            );
        } catch (e) {
            setMsg((e as Error).message);
        }
    };

    return (
        <div className="space-y-5">
            <section className="space-y-2">
                <h3 className="text-body-sm font-semibold text-txt-black-900">Cetak / eksport peta</h3>
                <input className={inputCls} value={tajuk} onChange={(e) => setTajuk(e.target.value)} aria-label="Tajuk peta" />
                {canPrint ? (
                    <div className="flex gap-2">
                        <Button size="sm" onClick={pdf}>
                            PDF
                        </Button>
                        <Button size="sm" variant="secondary" onClick={png}>
                            Imej PNG
                        </Button>
                    </div>
                ) : (
                    <p className="text-body-xs text-txt-black-500">Tiada kebenaran mencetak peta.</p>
                )}
                <p className="text-body-xs text-txt-black-500">Termasuk tajuk, skala, legenda, arah utara, sistem unjuran, cap masa dan nama anda; direkod dalam jejak audit.</p>
            </section>

            <section className="space-y-2">
                <h3 className="text-body-sm font-semibold text-txt-black-900">Paparan tersimpan</h3>
                <div className="flex gap-2">
                    <input className={inputCls} value={nama} onChange={(e) => setNama(e.target.value)} placeholder="Nama paparan" aria-label="Nama paparan" />
                    <Button size="sm" onClick={() => saveView.mutate()} disabled={!nama || saveView.isPending}>
                        Simpan
                    </Button>
                </div>
                <ul className="divide-y divide-otl-divider text-body-sm">
                    {views.data?.data.map((v) => (
                        <li key={v.id} className="flex items-center justify-between py-1.5">
                            <button type="button" className="text-txt-primary hover:underline" onClick={() => onOpen(v)}>
                                {v.nama}
                            </button>
                            {v.milik_saya && (
                                <button
                                    type="button"
                                    className="text-body-xs text-txt-danger"
                                    onClick={() => gisApi.padamPaparan(v.id).then(() => qc.invalidateQueries({ queryKey: ['gis', 'paparan'] }))}
                                >
                                    Padam
                                </button>
                            )}
                        </li>
                    ))}
                    {views.data?.data.length === 0 && <li className="py-1.5 text-txt-black-500">Tiada paparan tersimpan.</li>}
                </ul>
            </section>

            <section>
                <h3 className="mb-2 text-body-sm font-semibold text-txt-black-900">Petunjuk lapisan dipaparkan</h3>
                <Legenda layers={shownLayers} />
            </section>
            {msg && <p className="text-body-sm text-txt-danger" role="alert">{msg}</p>}
        </div>
    );
}
