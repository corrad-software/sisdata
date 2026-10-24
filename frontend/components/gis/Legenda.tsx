import type { EntriLegenda, Lapisan } from '@/lib/gis';
import { LABEL_KLASIFIKASI } from '@/lib/gis';

/** Thematic legend for visible layers (SRS-GIS-12). */
export function Legenda({ layers, compact = false }: { layers: Lapisan[]; compact?: boolean }) {
    return (
        <div className={compact ? 'flex flex-wrap gap-x-6 gap-y-2' : 'space-y-3'} aria-label="Petunjuk peta">
            {layers.map((l) => (
                <div key={l.kod} className="min-w-0">
                    <p className="text-body-xs font-semibold text-txt-black-700">
                        {l.nama}
                        {l.klasifikasi === 'sulit' || l.klasifikasi === 'rahsia' ? (
                            <span className="ml-1.5 rounded bg-bg-danger-50 px-1 text-[10px] font-medium uppercase text-txt-danger">{LABEL_KLASIFIKASI[l.klasifikasi]}</span>
                        ) : null}
                    </p>
                    <EntriList entri={l.legenda.entri} dashed={l.gaya.garis_putus ? 'TIADA' : undefined} outline={!!l.gaya.garis_sahaja} point={l.jenis_geometri === 'point'} />
                </div>
            ))}
        </div>
    );
}

export function EntriList({ entri, dashed, outline, point, muted }: { entri: EntriLegenda[]; dashed?: string; outline?: boolean; point?: boolean; muted?: boolean }) {
    return (
        <ul className={`mt-1 flex flex-wrap gap-x-3 gap-y-1 text-body-xs ${muted ? 'text-txt-black-500' : 'text-txt-black-700'}`}>
            {entri.map((e) => (
                <li key={e.nilai} className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className={`inline-block h-3 w-3 ${point ? 'rounded-full' : 'rounded-sm'}`}
                        style={
                            outline || e.nilai === dashed
                                ? { border: `2px ${e.nilai === dashed ? 'dashed' : 'solid'} ${e.warna}`, opacity: muted ? 0.5 : 1 }
                                : { backgroundColor: e.warna, opacity: muted ? 0.45 : 0.85 }
                        }
                    />
                    {e.label}
                </li>
            ))}
        </ul>
    );
}
