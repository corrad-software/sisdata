'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { astApi, ralat, useCan, useRujukan } from '@/lib/ast';
import { Card, Field, inputClass, ModalBody, Select } from '../_components/common';

type Kat = Record<string, string | number | boolean | string[] | null>;

const MEDAN: Array<[string, string]> = [
    ['kitaran_penyelenggaraan_bulan', 'Kitaran penyelenggaraan (bulan)'], ['tempoh_ambang_penilaian_bulan', 'Ambang penilaian semula (bulan)'],
    ['tempoh_pemeriksaan_bulan', 'Tempoh pemeriksaan (bulan)'], ['hayat_guna_tahun', 'Hayat guna (tahun)'], ['kadar_nilai_sisa', 'Nilai sisa (%)'],
];

/** SRS-AST-03: configurable categories — maintenance cycle (SRS-AST-15), revaluation threshold (19), inspection period (21), depreciation. */
export default function KonfigurasiKategori() {
    const can = useCan();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const { data, error } = useQuery({ queryKey: ['ast', 'kategori'], queryFn: astApi.kategori });
    const [edit, setEdit] = useState<{ id?: number; f: Kat } | null>(null);
    const simpan = useMutation({
        mutationFn: () => {
            const { id, ...rest } = { id: edit!.id, ...edit!.f };
            const b: Kat = { ...rest };
            delete b.bilangan_aset;
            if (id) delete b.kod;
            MEDAN.forEach(([k]) => { b[k] = Number(b[k]); });
            return astApi.simpanKategori(b, id);
        },
        onSuccess: (r) => { toast.success(r.message); setEdit(null); qc.invalidateQueries({ queryKey: ['ast', 'kategori'] }); qc.invalidateQueries({ queryKey: ['ast', 'rujukan'] }); },
        onError: (e) => toast.error(ralat(e)),
    });
    const boleh = can('ast.konfigurasi.kemas_kini');
    const baru: Kat = { kod: '', nama_kategori: '', jenis_lalai: 'bangunan', kitaran_penyelenggaraan_bulan: 12, tempoh_ambang_penilaian_bulan: 60, tempoh_pemeriksaan_bulan: 12, hayat_guna_tahun: 50, kadar_nilai_sisa: 10, jenis_penyelenggaraan_lalai: [], status_aktif: true };

    return (
        <PageContainer>
            <Card title="Kategori aset" actions={boleh ? <Button size="sm" onClick={() => setEdit({ f: baru })}>Tambah kategori</Button> : undefined}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                <Table head={<><th>Kod</th><th>Nama</th><th>Kitaran (bln)</th><th>Ambang nilai (bln)</th><th>Pemeriksaan (bln)</th><th>Hayat (thn)</th><th>Penyelenggaraan berjadual</th><th>Aset</th><th></th></>}>
                    {(data ?? []).map((k) => (
                        <tr key={String(k.id)}>
                            <td className="font-mono text-body-xs">{String(k.kod)}</td>
                            <td>{String(k.nama_kategori)} {!k.status_aktif && <Badge>Tidak aktif</Badge>}</td>
                            <td>{String(k.kitaran_penyelenggaraan_bulan)}</td><td>{String(k.tempoh_ambang_penilaian_bulan)}</td><td>{String(k.tempoh_pemeriksaan_bulan)}</td><td>{String(k.hayat_guna_tahun)}</td>
                            <td className="text-body-xs">{(k.jenis_penyelenggaraan_lalai as string[]).map((j) => ref?.jenis_penyelenggaraan.find((x) => x.kod === j)?.nama ?? j).join(', ')}</td>
                            <td>{String(k.bilangan_aset)}</td>
                            <td>{boleh && <Button size="sm" variant="secondary" onClick={() => setEdit({ id: Number(k.id), f: { ...k } })}>Ubah</Button>}</td>
                        </tr>
                    ))}
                </Table>
            </Card>
            <Modal open={!!edit} onClose={() => setEdit(null)}>
                {edit && (
                    <ModalBody title={edit.id ? `Ubah kategori ${String(edit.f.kod)}` : 'Kategori baharu'} footer={<><Button variant="secondary" onClick={() => setEdit(null)}>Batal</Button><Button disabled={simpan.isPending} onClick={() => simpan.mutate()}>Simpan</Button></>}>
                        {!edit.id && <Field label="Kod (huruf besar, _)" htmlFor="k-kod"><input id="k-kod" className={inputClass} value={String(edit.f.kod)} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, kod: e.target.value.toUpperCase() } })} /></Field>}
                        <Field label="Nama kategori" htmlFor="k-nama"><input id="k-nama" className={inputClass} value={String(edit.f.nama_kategori)} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, nama_kategori: e.target.value } })} /></Field>
                        <Field label="Jenis lalai" htmlFor="k-j"><Select id="k-j" value={String(edit.f.jenis_lalai)} onChange={(v) => setEdit({ ...edit, f: { ...edit.f, jenis_lalai: v } })} options={ref?.jenis_aset ?? []} /></Field>
                        {MEDAN.map(([k, l]) => (
                            <Field key={k} label={l} htmlFor={`k-${k}`}><input id={`k-${k}`} inputMode="decimal" className={inputClass} value={String(edit.f[k] ?? '')} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, [k]: e.target.value } })} /></Field>
                        ))}
                        <fieldset className="space-y-1">
                            <legend className="text-body-sm font-medium">Penyelenggaraan berjadual dijana automatik</legend>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-1">
                                {(ref?.jenis_penyelenggaraan ?? []).map((j) => {
                                    const cur = (edit.f.jenis_penyelenggaraan_lalai as string[]) ?? [];
                                    return <label key={j.kod} className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={cur.includes(j.kod)} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, jenis_penyelenggaraan_lalai: e.target.checked ? [...cur, j.kod] : cur.filter((x) => x !== j.kod) } })} />{j.nama}</label>;
                                })}
                            </div>
                        </fieldset>
                        <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={!!edit.f.status_aktif} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, status_aktif: e.target.checked } })} /> Aktif</label>
                    </ModalBody>
                )}
            </Modal>
        </PageContainer>
    );
}
