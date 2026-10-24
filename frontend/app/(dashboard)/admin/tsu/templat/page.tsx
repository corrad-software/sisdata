'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { ralat, tsuApi, useCan, useRujukan, type Kod, type Templat } from '@/lib/tsu';
import { Card, Field, inputClass, Kosong, Ralat, Select } from '../_components/common';

type Baris = Templat['peringkat'][number];

function Pilihan({ id, label, options, value, onChange }: { id: string; label: string; options: Kod[]; value: string[]; onChange: (v: string[]) => void }) {
    return (
        <fieldset id={id} className="space-y-1">
            <legend className="sr-only">{label}</legend>
            {options.map((o) => (
                <label key={o.kod} className="flex items-center gap-1.5 text-body-xs">
                    <input type="checkbox" checked={value.includes(o.kod)} onChange={(e) => onChange(e.target.checked ? [...value, o.kod] : value.filter((v) => v !== o.kod))} />
                    {o.nama}
                </label>
            ))}
        </fieldset>
    );
}

/** SRS-TSU-01/02: seven process templates; admins change stages, order, durations, mandatory documents and cost categories — saved as a new version. */
export default function TemplatProses() {
    const can = useCan();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const { data, error } = useQuery({ queryKey: ['tsu', 'templat'], queryFn: tsuApi.templat });
    const [ubah, setUbah] = useState<{ jenis: string; nama: string; peringkat: Baris[] } | null>(null);
    const m = useMutation({
        mutationFn: () => tsuApi.simpanTemplat(ubah!.jenis, { nama: ubah!.nama, peringkat: ubah!.peringkat.map((p) => ({ kod: p.kod, nama_peringkat: p.nama_peringkat, tindakan_diperlukan: p.tindakan_diperlukan, pihak_bertanggungjawab: p.pihak_bertanggungjawab, tempoh_sasaran_hari: p.tempoh_sasaran_hari, senarai_dokumen_wajib: p.senarai_dokumen_wajib, senarai_kategori_kos: p.senarai_kategori_kos, perlu_kelulusan: p.perlu_kelulusan, kesan: p.kesan || null })) }),
        onSuccess: (r) => {
            toast.success(r.message);
            setUbah(null);
            qc.invalidateQueries({ queryKey: ['tsu', 'templat'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const setRow = (i: number, patch: Partial<Baris>) => setUbah((u) => (u ? { ...u, peringkat: u.peringkat.map((p, j) => (j === i ? { ...p, ...patch } : p)) } : u));
    const move = (i: number, d: -1 | 1) =>
        setUbah((u) => {
            if (!u) return u;
            const rows = [...u.peringkat];
            const j = i + d;
            if (j < 0 || j >= rows.length) return u;
            [rows[i], rows[j]] = [rows[j], rows[i]];
            return { ...u, peringkat: rows };
        });

    if (error) return <PageContainer><Ralat>{ralat(error)}</Ralat></PageContainer>;
    if (!data) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;

    return (
        <PageContainer>
            {ubah ? (
                <Card title={`Versi baharu templat: ${ref?.jenis_urusan.find((j) => j.kod === ubah.jenis)?.nama ?? ubah.jenis}`} actions={<><Button size="sm" variant="secondary" onClick={() => setUbah(null)}>Batal</Button><Button size="sm" onClick={() => m.mutate()} disabled={m.isPending}>Simpan sebagai versi baharu</Button></>}>
                    <Field label="Nama templat" htmlFor="tp-nama"><input id="tp-nama" className={inputClass} value={ubah.nama} onChange={(e) => setUbah({ ...ubah, nama: e.target.value })} /></Field>
                    <div className="space-y-3">
                        {ubah.peringkat.map((p, i) => (
                            <div key={i} className="rounded-md border border-otl-gray-200 p-3 grid grid-cols-1 md:grid-cols-6 gap-3">
                                <Field label={`${i + 1}. Kod`} htmlFor={`tp-k-${i}`}><input id={`tp-k-${i}`} className={inputClass} value={p.kod} onChange={(e) => setRow(i, { kod: e.target.value })} /></Field>
                                <Field label="Nama peringkat" htmlFor={`tp-n-${i}`} className="md:col-span-2"><input id={`tp-n-${i}`} className={inputClass} value={p.nama_peringkat} onChange={(e) => setRow(i, { nama_peringkat: e.target.value })} /></Field>
                                <Field label="Pihak" htmlFor={`tp-p-${i}`}><Select id={`tp-p-${i}`} value={p.pihak_bertanggungjawab} onChange={(v) => setRow(i, { pihak_bertanggungjawab: v })} options={ref?.pihak ?? []} /></Field>
                                <Field label="Tempoh (hari)" htmlFor={`tp-h-${i}`}><input id={`tp-h-${i}`} type="number" min={1} className={inputClass} value={p.tempoh_sasaran_hari} onChange={(e) => setRow(i, { tempoh_sasaran_hari: Number(e.target.value) })} /></Field>
                                <Field label="Kesan sistem" htmlFor={`tp-e-${i}`}><Select id={`tp-e-${i}`} value={p.kesan ?? ''} onChange={(v) => setRow(i, { kesan: v || null })} options={ref?.kesan ?? []} empty="Tiada" /></Field>
                                <div className="md:col-span-2"><p className="text-body-xs font-medium mb-1">Dokumen wajib</p><Pilihan id={`tp-d-${i}`} label="Dokumen wajib" options={ref?.kategori_dokumen ?? []} value={p.senarai_dokumen_wajib} onChange={(v) => setRow(i, { senarai_dokumen_wajib: v })} /></div>
                                <div className="md:col-span-2"><p className="text-body-xs font-medium mb-1">Kategori kos</p><Pilihan id={`tp-c-${i}`} label="Kategori kos" options={ref?.kategori_kos ?? []} value={p.senarai_kategori_kos} onChange={(v) => setRow(i, { senarai_kategori_kos: v })} /></div>
                                <div className="md:col-span-2 space-y-2">
                                    <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={p.perlu_kelulusan} onChange={(e) => setRow(i, { perlu_kelulusan: e.target.checked })} /> Perlu kelulusan (Modul Workflow)</label>
                                    <div className="flex flex-wrap gap-2">
                                        <Button size="sm" variant="secondary" onClick={() => move(i, -1)} aria-label="Naik">↑</Button>
                                        <Button size="sm" variant="secondary" onClick={() => move(i, 1)} aria-label="Turun">↓</Button>
                                        <Button size="sm" variant="danger" onClick={() => setUbah({ ...ubah, peringkat: ubah.peringkat.filter((_, j) => j !== i) })}>Buang</Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                        <Button variant="outline" size="sm" onClick={() => setUbah({ ...ubah, peringkat: [...ubah.peringkat, { no_urutan: ubah.peringkat.length + 1, kod: `peringkat_${ubah.peringkat.length + 1}`, nama_peringkat: '', tindakan_diperlukan: null, pihak_bertanggungjawab: 'mais', tempoh_sasaran_hari: 14, senarai_dokumen_wajib: [], senarai_kategori_kos: [], perlu_kelulusan: false, kesan: null }] })}>Tambah peringkat</Button>
                    </div>
                </Card>
            ) : (
                data.map((t) => (
                    <Card key={t.id} title={<span className="flex flex-wrap items-center gap-2">{t.jenis_nama} <Badge>Versi {t.versi}</Badge> <span className="text-body-sm font-normal text-txt-black-500">{t.jumlah_hari} hari sasaran</span></span>} actions={can('tsu.templat.kemas_kini') && <Button size="sm" variant="secondary" onClick={() => setUbah({ jenis: t.jenis_urusan, nama: t.nama, peringkat: t.peringkat.map((p) => ({ ...p })) })}>Ubah</Button>}>
                        <Table head={<><th>#</th><th>Peringkat</th><th>Pihak</th><th>Hari</th><th>Dokumen wajib</th><th>Kos</th><th>Kelulusan</th></>}>
                            {t.peringkat.map((p) => (
                                <tr key={p.no_urutan}>
                                    <td>{p.no_urutan}</td>
                                    <td>{p.nama_peringkat}</td>
                                    <td>{ref?.pihak.find((x) => x.kod === p.pihak_bertanggungjawab)?.nama ?? p.pihak_bertanggungjawab}</td>
                                    <td>{p.tempoh_sasaran_hari}</td>
                                    <td className="text-body-xs">{p.senarai_dokumen_wajib.map((d) => ref?.kategori_dokumen.find((x) => x.kod === d)?.nama ?? d).join(', ') || '—'}</td>
                                    <td className="text-body-xs">{p.senarai_kategori_kos.map((d) => ref?.kategori_kos.find((x) => x.kod === d)?.nama ?? d).join(', ') || '—'}</td>
                                    <td>{p.perlu_kelulusan ? <Badge variant="warning">Ya</Badge> : '—'}</td>
                                </tr>
                            ))}
                        </Table>
                    </Card>
                ))
            )}
        </PageContainer>
    );
}
