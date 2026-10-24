'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { astApi, masa, ralat, rm, STATUS_KERJA_VARIANT, tarikh, type KerjaButiran } from '@/lib/ast';
import { Card, Field, InfoGrid, inputClass, Kosong, Select } from '../../_components/common';

const SETERUSNYA: Record<string, Array<{ kod: string; nama: string }>> = {
    dirancang: [{ kod: 'dalam_pelaksanaan', nama: 'Mula kerja' }, { kod: 'selesai', nama: 'Selesai' }, { kod: 'dibatalkan', nama: 'Batal' }],
    diluluskan: [{ kod: 'dalam_pelaksanaan', nama: 'Mula kerja' }, { kod: 'selesai', nama: 'Selesai' }, { kod: 'dibatalkan', nama: 'Batal' }],
    dalam_pelaksanaan: [{ kod: 'selesai', nama: 'Selesai' }, { kod: 'dibatalkan', nama: 'Batal' }],
    ditolak: [{ kod: 'dirancang', nama: 'Semak semula' }, { kod: 'dibatalkan', nama: 'Batal' }],
    menunggu_kelulusan: [{ kod: 'dibatalkan', nama: 'Tarik balik' }],
};

function Kemaskini({ k }: { k: KerjaButiran }) {
    const qc = useQueryClient();
    const { data: kontraktor } = useQuery({ queryKey: ['ast', 'kontraktor'], queryFn: () => astApi.kontraktor(), enabled: !k.kontraktor_sahaja });
    const [f, setF] = useState({ status: '', tarikh_mula: k.tarikh_mula ?? '', tarikh_siap: k.tarikh_siap ?? '', kos: k.kos ?? '', catatan_penyelesaian: k.catatan_penyelesaian ?? '', id_kontraktor: k.id_kontraktor ? String(k.id_kontraktor) : '', kos_anggaran: k.kos_anggaran ?? '', tarikh_dijadual: k.tarikh_dijadual ?? '' });
    const pilihan = (SETERUSNYA[k.status] ?? []).filter((s) => !k.kontraktor_sahaja || ['dalam_pelaksanaan', 'selesai'].includes(s.kod));
    const m = useMutation({
        mutationFn: () => {
            const base: Record<string, unknown> = { tarikh_mula: f.tarikh_mula || null, tarikh_siap: f.tarikh_siap || null, kos: f.kos || null, catatan_penyelesaian: f.catatan_penyelesaian || null };
            if (f.status) base.status = f.status;
            if (!k.kontraktor_sahaja) Object.assign(base, { id_kontraktor: f.id_kontraktor ? Number(f.id_kontraktor) : null, kos_anggaran: f.kos_anggaran || null, tarikh_dijadual: f.tarikh_dijadual || null });
            return astApi.kemaskiniKerja(k.id, base);
        },
        onSuccess: (r) => { toast.success(r.message); qc.invalidateQueries({ queryKey: ['ast', 'kerja', k.id] }); },
        onError: (e) => toast.error(ralat(e)),
    });
    const gambar = async (files: FileList | null, kategori: 'sebelum' | 'selepas') => {
        if (!files?.length) return;
        const fd = new FormData();
        fd.append('fail', files[0]);
        fd.append('kategori_gambar', kategori);
        try { const r = await astApi.gambarKerja(k.id, fd); toast.success(r.message); qc.invalidateQueries({ queryKey: ['ast', 'kerja', k.id] }); } catch (e) { toast.error(ralat(e)); }
    };
    return (
        <Card title={k.kontraktor_sahaja ? 'Kemas kini kemajuan kerja (kontraktor)' : 'Kemas kini kerja'}>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Field label="Tukar status" htmlFor="u-s"><Select id="u-s" value={f.status} onChange={(v) => setF({ ...f, status: v })} options={pilihan} empty="Kekal" /></Field>
                <Field label="Tarikh mula" htmlFor="u-tm"><input id="u-tm" type="date" className={inputClass} value={f.tarikh_mula} onChange={(e) => setF({ ...f, tarikh_mula: e.target.value })} /></Field>
                <Field label="Tarikh siap" htmlFor="u-ts"><input id="u-ts" type="date" className={inputClass} value={f.tarikh_siap} onChange={(e) => setF({ ...f, tarikh_siap: e.target.value })} /></Field>
                <Field label="Kos sebenar (RM)" htmlFor="u-k" help="Wajib sebelum kerja ditanda selesai."><input id="u-k" inputMode="decimal" className={inputClass} value={f.kos} onChange={(e) => setF({ ...f, kos: e.target.value })} /></Field>
                {!k.kontraktor_sahaja && (
                    <>
                        <Field label="Kontraktor" htmlFor="u-c"><Select id="u-c" value={f.id_kontraktor} onChange={(v) => setF({ ...f, id_kontraktor: v })} options={(kontraktor ?? []).map((c) => ({ kod: String(c.id), nama: c.nama_syarikat }))} empty="Tiada / dalaman" /></Field>
                        <Field label="Anggaran kos (RM)" htmlFor="u-ka"><input id="u-ka" inputMode="decimal" className={inputClass} value={f.kos_anggaran} onChange={(e) => setF({ ...f, kos_anggaran: e.target.value })} /></Field>
                        <Field label="Tarikh dijadual" htmlFor="u-td"><input id="u-td" type="date" className={inputClass} value={f.tarikh_dijadual} onChange={(e) => setF({ ...f, tarikh_dijadual: e.target.value })} /></Field>
                    </>
                )}
                <Field label="Catatan penyelesaian" htmlFor="u-cp" className="sm:col-span-2 lg:col-span-3"><textarea id="u-cp" rows={2} className={inputClass} value={f.catatan_penyelesaian} onChange={(e) => setF({ ...f, catatan_penyelesaian: e.target.value })} /></Field>
            </div>
            <div className="flex flex-wrap gap-2 justify-between">
                <div className="flex flex-wrap gap-2">
                    <label className="inline-flex items-center rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm cursor-pointer">Gambar sebelum<input type="file" accept="image/*" capture="environment" className="sr-only" onChange={(e) => gambar(e.target.files, 'sebelum')} /></label>
                    <label className="inline-flex items-center rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm cursor-pointer">Gambar selepas<input type="file" accept="image/*" capture="environment" className="sr-only" onChange={(e) => gambar(e.target.files, 'selepas')} /></label>
                </div>
                <Button disabled={m.isPending} onClick={() => m.mutate()}>Simpan</Button>
            </div>
        </Card>
    );
}

/** One maintenance work order: details, before/after photos, status (WFE approval above threshold). */
export default function ButiranKerja() {
    const { id } = useParams<{ id: string }>();
    const { data: k, error } = useQuery({ queryKey: ['ast', 'kerja', id], queryFn: () => astApi.kerjaButiran(id) });
    if (error) return <PageContainer><p className="text-body-sm text-txt-danger">{ralat(error)}</p></PageContainer>;
    if (!k) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    const tutup = ['selesai', 'dibatalkan'].includes(k.status);

    return (
        <PageContainer>
            {k.status === 'menunggu_kelulusan' && <Callout type="info" title="Menunggu kelulusan">Anggaran kos melebihi ambang — kerja boleh dimulakan selepas diluluskan melalui Modul Workflow.</Callout>}
            <Card title={<span className="flex flex-wrap items-center gap-2">{k.no_rujukan} <Badge variant={STATUS_KERJA_VARIANT[k.status] ?? 'neutral'}>{k.status_nama}</Badge>{k.tertunggak && <Badge variant="danger">Melepasi tarikh</Badge>}</span>}>
                <InfoGrid items={[
                    ['Aset', k.aset ? (k.kontraktor_sahaja ? k.aset.nama_aset : <Link key="a" href={`/admin/ast/aset/${k.aset.id}`} className="text-txt-primary hover:underline">{k.aset.nama_aset} ({k.aset.no_rujukan_aset})</Link>) : '—'],
                    ['Jenis', `${k.jenis_nama} · ${k.jenis_kerja_nama}`],
                    ['Keutamaan', k.keutamaan_nama],
                    ['Sumber', k.sumber_nama + (k.id_keperluan_ptn ? ` (#${k.id_keperluan_ptn})` : '')],
                    ['Tarikh dijadual', tarikh(k.tarikh_dijadual)],
                    ['Tarikh mula / siap', `${tarikh(k.tarikh_mula)} / ${tarikh(k.tarikh_siap)}`],
                    ['Anggaran kos', rm(k.kos_anggaran)],
                    ['Kos sebenar', rm(k.kos)],
                    ['Pelaksana', k.nama_syarikat_pelaksana],
                    ['Pegawai', k.pegawai?.nama],
                    ['Jadual berkala', k.jadual ? `${k.jadual.jenis_nama} · setiap ${k.jadual.kitaran_bulan} bulan` : '—'],
                ]} />
                <p className="text-body-sm">{k.keterangan}</p>
                {k.catatan_penyelesaian && <p className="text-body-sm text-txt-black-700 whitespace-pre-line">{k.catatan_penyelesaian}</p>}
                {k.gambar.length > 0 && (
                    <ul className="text-body-sm space-y-1">
                        {k.gambar.map((g) => <li key={g.id}><Badge variant={g.kategori_gambar === 'sebelum' ? 'warning' : 'success'}>{g.kategori_nama}</Badge> {masa(g.tarikh_gambar)} {g.keterangan ?? ''}</li>)}
                    </ul>
                )}
            </Card>
            {k.boleh_urus && !tutup && <Kemaskini k={k} />}
        </PageContainer>
    );
}
