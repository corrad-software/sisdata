'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Spinner } from '@/components/ui/Spinner';
import { Pagination } from '@/components/ui/Pagination';
import { BarChart } from '@/components/ui/charts/BarChart';
import { HorizontalBarChart } from '@/components/ui/charts/HorizontalBarChart';
import { MultiLineChart } from '@/components/ui/charts/MultiLineChart';
import { PieChart } from '@/components/ui/charts/PieChart';
import {
    cartaKePng, eksport, lapApi, nombor, papar, PRATETAP_LABEL, ralat, tarikhMasa, useCan, useMe,
    type Definisi, type Hasil, type Laporan, type Parameter, type Sumber,
} from '@/lib/lap';
import { Card, Field, inputClass, Kosong, LabelCadangan } from './common';

type Drill = Record<string, string | null>;

function useDaerah() {
    return useQuery({ queryKey: ['ref', 'daerah'], queryFn: () => api.get<{ data: Array<{ kod: string; nama: string }> }>('/ref/daerah').then((r) => r.data), staleTime: 600_000 });
}

/**
 * Runs a report (catalogue / saved / ad hoc) and renders every section with
 * its report header (SRS-LAP-11), chart (+PNG export, SRS-LAP-05), table with
 * level-by-level drill-down to the original record (SRS-LAP-12) and exports
 * (SRS-LAP-04 / 07).
 */
export function Viewer({ sumber, parameterAwal }: { sumber: Sumber; parameterAwal?: Parameter }) {
    const can = useCan();
    const { data: me } = useMe();
    const daerah = useDaerah();
    const [parameter, setParameter] = useState<Parameter>(parameterAwal ?? {});
    const [drill, setDrill] = useState<Drill>({});
    const [halaman, setHalaman] = useState(1);
    const [sibuk, setSibuk] = useState<string | null>(null);

    const q = useQuery({
        queryKey: ['lap', 'jana', sumber, parameter, drill, halaman],
        queryFn: () => lapApi.jana({ sumber, parameter, drill, halaman, per_halaman: 50 }).then((r) => r.data),
    });

    const tukar = (p: Parameter) => {
        setParameter(p);
        setDrill({});
        setHalaman(1);
    };

    const muatTurun = async (format: 'pdf' | 'xlsx' | 'csv', latar = false) => {
        setSibuk(format + (latar ? '-latar' : ''));
        try {
            const r = await eksport({ sumber, parameter, format, latar });
            if (r.latar) toast.success('Laporan dijana di latar belakang. Anda akan dimaklumkan apabila siap (Log Penjanaan).');
            else toast.success('Fail dimuat turun.');
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setSibuk(null);
        }
    };

    const laporan = q.data;

    return (
        <div className="space-y-6">
            <Card
                title={laporan?.tajuk ?? 'Laporan'}
                actions={
                    can('lap.laporan.eksport') ? (
                        <>
                            {(['pdf', 'xlsx', 'csv'] as const).map((f) => (
                                <Button key={f} variant="outline" size="sm" onClick={() => muatTurun(f)} disabled={sibuk !== null || !laporan}>
                                    {sibuk === f ? 'Menjana…' : f === 'xlsx' ? 'Excel' : f.toUpperCase()}
                                </Button>
                            ))}
                            <Button variant="secondary" size="sm" onClick={() => muatTurun('xlsx', true)} disabled={sibuk !== null || !laporan}>
                                Jana di latar
                            </Button>
                        </>
                    ) : null
                }
            >
                <Penapis parameter={parameter} onChange={tukar} daerah={daerah.data ?? []} />
                <LabelCadangan teks={laporan?.label_cadangan} />
            </Card>

            {q.isLoading && (
                <div className="flex justify-center py-10">
                    <Spinner />
                </div>
            )}
            {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}

            {laporan?.bahagian.map((b, i) => (
                <Bahagian
                    key={`${b.kod ?? 'x'}-${i}`}
                    laporan={laporan}
                    tajuk={b.tajuk}
                    definisi={b.definisi}
                    hasil={b.hasil}
                    komposit={laporan.komposit}
                    drill={laporan.komposit ? {} : drill}
                    onDrill={(d) => {
                        setDrill(d);
                        setHalaman(1);
                    }}
                    halaman={halaman}
                    onHalaman={setHalaman}
                    tandaAir={me ? `SISDATA · ${me.name} (${me.email}) · ${new Date().toLocaleString('ms-MY')}` : 'SISDATA'}
                    parameter={parameter}
                    bolehEksport={can('lap.laporan.eksport')}
                />
            ))}
        </div>
    );
}

function Penapis({ parameter, onChange, daerah }: { parameter: Parameter; onChange: (p: Parameter) => void; daerah: Array<{ kod: string; nama: string }> }) {
    const t = parameter.tempoh ?? {};
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <Field label="Tempoh pratetap" htmlFor="lap-pratetap">
                <select
                    id="lap-pratetap"
                    className={inputClass}
                    value={t.pratetap ?? ''}
                    onChange={(e) => onChange({ ...parameter, tempoh: e.target.value ? { pratetap: e.target.value } : undefined })}
                >
                    <option value="">Ikut definisi laporan</option>
                    {Object.entries(PRATETAP_LABEL).map(([k, v]) => (
                        <option key={k} value={k}>
                            {v}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Dari" htmlFor="lap-dari">
                <input id="lap-dari" type="date" className={inputClass} value={t.dari ?? ''} onChange={(e) => onChange({ ...parameter, tempoh: { dari: e.target.value || undefined, hingga: t.hingga } })} />
            </Field>
            <Field label="Hingga" htmlFor="lap-hingga">
                <input id="lap-hingga" type="date" className={inputClass} value={t.hingga ?? ''} onChange={(e) => onChange({ ...parameter, tempoh: { dari: t.dari, hingga: e.target.value || undefined } })} />
            </Field>
            <Field label="Daerah" htmlFor="lap-daerah" help="Hanya daerah dalam skop capaian anda akan dipaparkan.">
                <select id="lap-daerah" className={inputClass} value={parameter.kod_daerah ?? ''} onChange={(e) => onChange({ ...parameter, kod_daerah: e.target.value || undefined })}>
                    <option value="">Semua dalam skop</option>
                    {daerah.map((d) => (
                        <option key={d.kod} value={d.kod}>
                            {d.nama}
                        </option>
                    ))}
                </select>
            </Field>
        </div>
    );
}

function Bahagian({
    laporan, tajuk, definisi, hasil, komposit, drill, onDrill, halaman, onHalaman, tandaAir, parameter, bolehEksport,
}: {
    laporan: Laporan;
    tajuk: string;
    definisi: Definisi;
    hasil: Hasil;
    komposit: boolean;
    drill: Drill;
    onDrill: (d: Drill) => void;
    halaman: number;
    onHalaman: (p: number) => void;
    tandaAir: string;
    parameter: Parameter;
    bolehEksport: boolean;
}) {
    const m = hasil.meta;
    const kumpulan = hasil.lajur.filter((l) => l.kumpulan);
    const nilai = hasil.lajur.filter((l) => !l.kumpulan && !l.kunci.includes('__'));
    const utama = nilai[0];
    const label = hasil.baris.map((b) => papar(kumpulan[0]?.jenis ?? 'teks', b[kumpulan[0]?.kunci ?? ''], b._label?.[kumpulan[0]?.kunci ?? '']));
    const carta = definisi.carta ?? 'bar';
    const adaCarta = hasil.mod === 'kumpulan' && utama && hasil.baris.length > 0 && carta !== 'jadual';
    const lepas = utama ? hasil.lajur.find((l) => l.kunci === `${utama.kunci}__lepas`) : undefined;

    const simpanImej = async () => {
        if (!utama) return;
        const siri = [{ nama: utama.label, nilai: hasil.baris.map((b) => nombor(b[utama.kunci])) }];
        if (lepas) siri.push({ nama: lepas.label, nilai: hasil.baris.map((b) => nombor(b[lepas.kunci])) });
        cartaKePng({ tajuk, subtajuk: `${m.penapis_teks.join('; ') || 'Tiada penapis'} · data setakat ${tarikhMasa(m.data_dikemas_kini)}`, label, siri, jenis: carta === 'jadual' ? 'bar' : carta, tandaAir });
        try {
            await lapApi.imej({ tajuk, kod_laporan: laporan.kod_laporan, id_definisi: laporan.id_definisi, bilangan_rekod: hasil.bilangan_rekod, parameter });
        } catch {
            /* the image is already saved; logging failure is reported by the API */
        }
    };

    const jejak = Object.entries(hasil.drill ?? {});

    return (
        <Card
            title={komposit ? tajuk : undefined}
            actions={adaCarta && bolehEksport ? <Button size="sm" variant="outline" onClick={simpanImej}>Simpan carta (PNG)</Button> : undefined}
        >
            <div className="flex flex-wrap gap-2 items-center">
                {m.klasifikasi_tertinggi === 'sulit' || m.klasifikasi_tertinggi === 'rahsia' ? <Badge variant="danger">{m.klasifikasi_tertinggi.toUpperCase()}</Badge> : null}
                <Badge variant="neutral">{hasil.bilangan_rekod.toLocaleString('ms-MY')} rekod</Badge>
                {hasil.dari_cache && <Badge variant="info">Dari cache</Badge>}
                {m.replika && <Badge variant="info">Replika baca</Badge>}
                {m.dipotong && <Badge variant="warning">Hasil dipotong pada had baris</Badge>}
            </div>
            <dl className="grid grid-cols-1 sm:grid-cols-3 gap-2 text-body-xs text-txt-black-500">
                <div>
                    <dt className="font-medium">Data dikemas kini terakhir</dt>
                    <dd className="text-txt-black-900">{tarikhMasa(m.data_dikemas_kini)}</dd>
                </div>
                <div>
                    <dt className="font-medium">Tempoh data</dt>
                    <dd className="text-txt-black-900">{m.tempoh ? `${m.tempoh.dari.split('-').reverse().join('/')} – ${m.tempoh.hingga.split('-').reverse().join('/')}` : 'Semua tempoh'}</dd>
                </div>
                <div>
                    <dt className="font-medium">Penapis</dt>
                    <dd className="text-txt-black-900 break-words">{m.penapis_teks.length ? m.penapis_teks.join('; ') : 'Tiada'}</dd>
                </div>
            </dl>
            {m.medan_disembunyikan.length > 0 && <Callout type="info">Medan berikut disembunyikan kerana tiada kebenaran: {m.medan_disembunyikan.join(', ')}.</Callout>}

            {!komposit && jejak.length > 0 && (
                <nav aria-label="Jejak drill-down" className="flex flex-wrap items-center gap-2 text-body-sm">
                    <button type="button" className="text-txt-primary hover:underline" onClick={() => onDrill({})}>
                        Semua
                    </button>
                    {jejak.map(([k, v], i) => (
                        <span key={k} className="flex items-center gap-2">
                            <span aria-hidden>›</span>
                            <button type="button" className="text-txt-primary hover:underline" onClick={() => onDrill(Object.fromEntries(jejak.slice(0, i + 1)))}>
                                {v ?? '(tiada)'}
                            </button>
                        </span>
                    ))}
                </nav>
            )}

            {adaCarta && utama && (
                <div className="overflow-x-auto">
                    <div className="min-w-[320px]">
                        {carta === 'garis' ? (
                            <MultiLineChart
                                labels={label}
                                series={[
                                    { name: utama.label, data: hasil.baris.map((b) => nombor(b[utama.kunci])) },
                                    ...(lepas ? [{ name: lepas.label, data: hasil.baris.map((b) => nombor(b[lepas.kunci])), color: 'text-txt-black-500' }] : []),
                                ]}
                            />
                        ) : carta === 'pai' ? (
                            <PieChart data={hasil.baris.slice(0, 8).map((b, i) => ({ label: label[i], value: nombor(b[utama.kunci]) }))} />
                        ) : hasil.baris.length > 8 ? (
                            <HorizontalBarChart data={hasil.baris.slice(0, 20).map((b, i) => ({ label: label[i], value: nombor(b[utama.kunci]) }))} />
                        ) : (
                            <BarChart data={hasil.baris.map((b, i) => ({ label: label[i], value: nombor(b[utama.kunci]) }))} />
                        )}
                    </div>
                </div>
            )}

            {hasil.baris.length === 0 ? (
                <Kosong>Tiada rekod dalam skop dan penapis ini.</Kosong>
            ) : (
                <Table
                    head={
                        <>
                            {hasil.lajur.map((l) => (
                                <th key={l.kunci} className={['wang', 'nombor', 'bilangan', 'peratus'].includes(l.jenis) ? 'text-right' : ''}>
                                    {l.label}
                                </th>
                            ))}
                            {(hasil.boleh_drill && !komposit) || hasil.mod === 'rekod' ? <th>Tindakan</th> : null}
                        </>
                    }
                >
                    {hasil.baris.map((b, i) => (
                        <tr key={b._id ?? i}>
                            {hasil.lajur.map((l) => (
                                <td key={l.kunci} className={['wang', 'nombor', 'bilangan', 'peratus'].includes(l.jenis) ? 'text-right tabular-nums' : ''}>
                                    {papar(l.jenis, b[l.kunci], b._label?.[l.kunci])}
                                </td>
                            ))}
                            {hasil.mod === 'kumpulan' && hasil.boleh_drill && !komposit ? (
                                <td>
                                    <button type="button" className="text-txt-primary hover:underline whitespace-nowrap" onClick={() => onDrill({ ...drill, ...(b._drill ?? {}) })}>
                                        Perincian ›
                                    </button>
                                </td>
                            ) : hasil.mod === 'rekod' ? (
                                <td>
                                    {b._pautan ? (
                                        <Link href={b._pautan} className="text-txt-primary hover:underline whitespace-nowrap">
                                            Buka rekod
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </td>
                            ) : null}
                        </tr>
                    ))}
                    {hasil.jumlah_keseluruhan && (
                        <tr className="font-semibold bg-bg-washed">
                            {hasil.lajur.map((l, i) => (
                                <td key={l.kunci} className={['wang', 'nombor', 'bilangan', 'peratus'].includes(l.jenis) ? 'text-right tabular-nums' : ''}>
                                    {l.kunci in (hasil.jumlah_keseluruhan ?? {}) ? papar(l.jenis, hasil.jumlah_keseluruhan?.[l.kunci]) : i === 0 ? 'Jumlah' : ''}
                                </td>
                            ))}
                            {hasil.boleh_drill && !komposit ? <td /> : null}
                        </tr>
                    )}
                </Table>
            )}

            {hasil.mod === 'rekod' && !komposit && hasil.bilangan_rekod > 50 && (
                <Pagination currentPage={halaman} lastPage={Math.ceil(hasil.bilangan_rekod / 50)} onPageChange={onHalaman} total={hasil.bilangan_rekod} />
            )}
            <p className="text-body-xs text-txt-black-500">
                Dijana {tarikhMasa(m.dijana_pada)} dalam {m.tempoh_ms} ms · tahap klasifikasi dibenarkan: {m.tahap_klasifikasi.join(', ')}
            </p>
        </Card>
    );
}
