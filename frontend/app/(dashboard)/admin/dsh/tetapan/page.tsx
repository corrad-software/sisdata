'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Spinner } from '@/components/ui/Spinner';
import { Table } from '@/components/ui/Table';
import { ARAS_LABEL, DOMAIN_LABEL, VISUAL_LABEL, dshApi, errorMessage, type Aras, type Tetapan, type Visual } from '@/lib/dsh';
import { inputClass } from '../_components/PenapisBar';

type KpiRow = Tetapan['kpi'][number];

/** Admin configuration without code (SRS-DSH-10): thresholds and widget definitions per indicator. */
export default function TetapanPage() {
    const q = useQuery({ queryKey: ['dsh', 'tetapan'], queryFn: dshApi.tetapan });
    const [edit, setEdit] = useState<KpiRow | null>(null);

    if (q.isLoading) return <PageContainer><Spinner /></PageContainer>;
    if (q.isError || !q.data) return <PageContainer><Alert type="error">{errorMessage(q.error)}</Alert></PageContainer>;
    const t = q.data;
    const widget = (key: string) => t.widget.find((w) => w.kod_widget === `kpi:${key}`);

    return (
        <PageContainer>
            <p className="text-body-sm text-txt-black-500">
                Tetapkan ambang lampu isyarat / peta haba dan definisi widget bagi setiap indikator. Perubahan berkuat kuasa serta-merta untuk semua pengguna dan direkod dalam jejak audit.
            </p>
            <Table head={<><th>Indikator</th><th>Domain</th><th>Ambang</th><th>Widget</th><th>Tindakan</th></>}>
                {t.kpi.map((k) => {
                    const w = widget(k.key);
                    return (
                        <tr key={k.key}>
                            <td>
                                <p className="font-medium">{k.label}</p>
                                <p className="text-body-xs text-txt-black-500">{k.key} · kebenaran {k.kebenaran}</p>
                            </td>
                            <td>{DOMAIN_LABEL[k.domain] ?? k.domain}</td>
                            <td className="whitespace-nowrap text-body-xs">
                                {k.ambang ? (
                                    <>
                                        {k.ambang.arah === 'tinggi' ? '≥' : '≤'} {k.ambang.hijau} hijau · {k.ambang.arah === 'tinggi' ? '≥' : '≤'} {k.ambang.kuning} kuning{' '}
                                        <Badge variant={k.ambang.sumber === 'pentadbir' ? 'info' : 'neutral'}>{k.ambang.sumber}</Badge>
                                    </>
                                ) : (
                                    '—'
                                )}
                            </td>
                            <td className="text-body-xs">
                                {w ? `${VISUAL_LABEL[w.jenis_visual]} · ${w.aras_dashboard.map((a) => ARAS_LABEL[a]).join(', ')}${w.aktif ? '' : ' · tidak aktif'}` : 'Lalai'}
                            </td>
                            <td>
                                <Button size="sm" variant="secondary" onClick={() => setEdit(k)}>
                                    Ubah
                                </Button>
                            </td>
                        </tr>
                    );
                })}
            </Table>
            {edit && <UbahModal k={edit} t={t} onClose={() => setEdit(null)} />}
        </PageContainer>
    );
}

function UbahModal({ k, t, onClose }: { k: KpiRow; t: Tetapan; onClose: () => void }) {
    const qc = useQueryClient();
    const row = t.ambang.find((a) => a.kod_indikator === k.key);
    const w = t.widget.find((x) => x.kod_widget === `kpi:${k.key}`);
    const [arah, setArah] = useState<'tinggi' | 'rendah'>(row?.arah ?? k.ambang?.arah ?? 'tinggi');
    const [hijau, setHijau] = useState(String(row?.nilai_hijau ?? k.ambang?.hijau ?? ''));
    const [kuning, setKuning] = useState(String(row?.nilai_kuning ?? k.ambang?.kuning ?? ''));
    const [visual, setVisual] = useState<Visual>(w?.jenis_visual ?? 'auto');
    const [aras, setAras] = useState<Aras[]>(w?.aras_dashboard ?? ['eksekutif', 'pengurusan']);
    const [tajuk, setTajuk] = useState(w?.tajuk ?? '');
    const [aktif, setAktif] = useState(w?.aktif ?? true);
    const done = (m: string) => {
        toast.success(m);
        qc.invalidateQueries({ queryKey: ['dsh'] });
    };
    const saveAmbang = useMutation({ mutationFn: () => dshApi.simpanAmbang(k.key, { arah, nilai_hijau: Number(hijau), nilai_kuning: Number(kuning) }), onSuccess: (r) => done(r.message), onError: (e) => toast.error(errorMessage(e)) });
    const delAmbang = useMutation({ mutationFn: () => dshApi.padamAmbang(k.key), onSuccess: (r) => done(r.message), onError: (e) => toast.error(errorMessage(e)) });
    const saveWidget = useMutation({ mutationFn: () => dshApi.simpanWidget(`kpi:${k.key}`, { tajuk: tajuk || null, jenis_visual: visual, aras_dashboard: aras, aktif }), onSuccess: (r) => done(r.message), onError: (e) => toast.error(errorMessage(e)) });
    const delWidget = useMutation({ mutationFn: () => dshApi.padamWidget(`kpi:${k.key}`), onSuccess: (r) => done(r.message), onError: (e) => toast.error(errorMessage(e)) });

    return (
        <Modal open onClose={onClose}>
            <div className="max-h-[85vh] space-y-5 overflow-y-auto p-5">
                <h2 className="font-heading text-heading-2xs font-semibold text-txt-black-900">{k.label}</h2>
                <section className="space-y-2">
                    <h3 className="text-body-sm font-semibold">Ambang lampu isyarat</h3>
                    <div className="grid grid-cols-3 gap-2">
                        <label className="text-body-xs">
                            Arah
                            <select className={inputClass} value={arah} onChange={(e) => setArah(e.target.value as 'tinggi' | 'rendah')}>
                                <option value="tinggi">Tinggi lebih baik</option>
                                <option value="rendah">Rendah lebih baik</option>
                            </select>
                        </label>
                        <label className="text-body-xs">
                            Hijau
                            <input className={inputClass} inputMode="decimal" value={hijau} onChange={(e) => setHijau(e.target.value)} />
                        </label>
                        <label className="text-body-xs">
                            Kuning
                            <input className={inputClass} inputMode="decimal" value={kuning} onChange={(e) => setKuning(e.target.value)} />
                        </label>
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" onClick={() => saveAmbang.mutate()} disabled={saveAmbang.isPending || hijau === '' || kuning === ''}>Simpan ambang</Button>
                        {row && <Button size="sm" variant="secondary" onClick={() => delAmbang.mutate()}>Guna lalai</Button>}
                    </div>
                </section>
                <section className="space-y-2">
                    <h3 className="text-body-sm font-semibold">Widget</h3>
                    <label className="block text-body-xs">
                        Tajuk (kosong = lalai)
                        <input className={inputClass} value={tajuk} maxLength={160} onChange={(e) => setTajuk(e.target.value)} />
                    </label>
                    <label className="block text-body-xs">
                        Jenis visual
                        <select className={inputClass} value={visual} onChange={(e) => setVisual(e.target.value as Visual)}>
                            {(['auto', 'stat', 'bar', 'pie', 'line', 'progress', 'heatmap', 'lampu'] as Visual[]).map((v) => (
                                <option key={v} value={v}>{VISUAL_LABEL[v]}</option>
                            ))}
                        </select>
                    </label>
                    <fieldset className="flex flex-wrap gap-4 text-body-xs">
                        <legend className="mb-1">Aras dashboard</legend>
                        {t.aras.map((a) => (
                            <label key={a} className="flex items-center gap-1">
                                <input type="checkbox" className="rounded border-otl-gray-300" checked={aras.includes(a)} onChange={(e) => setAras((x) => (e.target.checked ? [...x, a] : x.filter((y) => y !== a)))} />
                                {ARAS_LABEL[a]}
                            </label>
                        ))}
                        <label className="flex items-center gap-1">
                            <input type="checkbox" className="rounded border-otl-gray-300" checked={aktif} onChange={(e) => setAktif(e.target.checked)} />
                            Aktif
                        </label>
                    </fieldset>
                    <div className="flex gap-2">
                        <Button size="sm" onClick={() => saveWidget.mutate()} disabled={saveWidget.isPending || aras.length === 0}>Simpan widget</Button>
                        {w && <Button size="sm" variant="secondary" onClick={() => delWidget.mutate()}>Guna lalai</Button>}
                    </div>
                </section>
                <div className="flex justify-end">
                    <Button variant="secondary" onClick={onClose}>Tutup</Button>
                </div>
            </div>
        </Modal>
    );
}
