'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { masa, ptnApi, ralat, tarikh, useCan, WARNA_HEX } from '@/lib/ptn';
import { Card, InfoGrid, KodWarnaBadge, Kosong, Tahap } from '../../_components/common';

/** SRS-PTN-11/16: lot monitoring card — risk (six categories), schedule, visits, issues. */
export default function LotDetail() {
    const { id } = useParams<{ id: string }>();
    const qc = useQueryClient();
    const can = useCan();
    const { data: d, error } = useQuery({ queryKey: ['ptn', 'lot', id], queryFn: () => ptnApi.lotButiran(id) });
    const kira = useMutation({ mutationFn: () => ptnApi.kiraRisiko(id), onSuccess: (r) => { toast.success(r.message); qc.invalidateQueries({ queryKey: ['ptn', 'lot', id] }); }, onError: (e) => toast.error(ralat(e)) });
    if (error) return <PageContainer><p className="text-body-sm text-txt-danger">{ralat(error)}</p></PageContainer>;
    if (!d) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    const r = d.risiko;

    return (
        <PageContainer>
            <Card title={`Lot ${d.lot.no_lot}`} actions={<><Link href={d.lot.pautan} className="text-body-sm text-txt-primary hover:underline">Profil Hartanah 360°</Link>{can('ptn.konfigurasi.kemas_kini') && <Button size="sm" variant="secondary" onClick={() => kira.mutate()}>Kira semula risiko</Button>}</>}>
                <InfoGrid items={[
                    ['Daerah / Mukim', `${d.lot.daerah ?? '—'} / ${d.lot.mukim ?? '—'}`],
                    ['Kategori guna tanah', d.lot.kategori_guna_tanah],
                    ['Keluasan', d.lot.keluasan ? `${d.lot.keluasan} ${d.lot.unit_keluasan}` : '—'],
                    ['Risiko', r ? <KodWarnaBadge key="w" warna={r.kod_warna} label={`${r.warna.label} · skor ${r.skor_keseluruhan.toFixed(1)}`} /> : '—'],
                    ['Dikira pada', masa(r?.tarikh_kiraan)],
                    ['Penemuan kritikal terbuka', r?.paksa_merah ? 'Ya — lot ditanda merah' : 'Tiada'],
                ]} />
                {r?.kategori && (
                    <div className="space-y-2" aria-label="Skor kategori risiko">
                        {r.kategori.map((k) => (
                            <div key={k.kod} className="grid grid-cols-[10rem_1fr_auto] sm:grid-cols-[14rem_1fr_auto] items-center gap-3 text-body-sm">
                                <span>{k.label} <span className="text-body-xs text-txt-black-500">(pemberat {k.pemberat})</span></span>
                                <div className="h-2.5 rounded-full bg-bg-washed overflow-hidden" role="presentation"><div className="h-full" style={{ width: `${k.skor}%`, background: WARNA_HEX[k.kod_warna] }} /></div>
                                <span className="tabular-nums">{k.skor.toFixed(0)}{k.butiran ? ` · ${k.butiran}` : ''}</span>
                            </div>
                        ))}
                    </div>
                )}
            </Card>
            <Card title="Peta"><PetaSisdata layers={['ptn.risiko', 'ptn.pemantauan']} focus={{ lotId: id }} height={320} label="Lokasi lot" /></Card>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card title="Jadual seterusnya">
                    {d.jadual.length === 0 ? <Kosong>Tiada jadual terbuka.</Kosong> : (
                        <Table head={<><th>Tarikh</th><th>Keutamaan</th><th>Pegawai</th><th>Tempoh</th></>}>
                            {d.jadual.map((j) => <tr key={j.id}><td>{tarikh(j.tarikh_sasaran)}</td><td><Tahap tahap={j.keutamaan_risiko} label={j.keutamaan_nama} /></td><td>{j.pegawai?.nama ?? '—'}</td><td><KodWarnaBadge warna={j.kod_warna} label={j.kod_warna === 'merah' ? 'Tertunggak' : j.kod_warna === 'kuning' ? 'Hampir tempoh' : 'Dalam tempoh'} /></td></tr>)}
                        </Table>
                    )}
                </Card>
                <Card title="Isu">
                    {d.isu.length === 0 ? <Kosong>Tiada isu.</Kosong> : (
                        <Table head={<><th>No.</th><th>Kategori</th><th>Tahap</th><th>Status</th></>}>
                            {d.isu.map((i) => <tr key={i.id}><td><Link className="text-txt-primary hover:underline" href={`/admin/ptn/isu/${i.id}`}>{i.no_rujukan}</Link></td><td>{i.kategori_nama}</td><td><Tahap tahap={i.tahap_keterukan} label={i.tahap_nama} /></td><td>{i.status_nama}</td></tr>)}
                        </Table>
                    )}
                </Card>
            </div>
            <Card title="Sejarah lawatan">
                {d.lawatan.length === 0 ? <Kosong>Belum pernah dilawat.</Kosong> : (
                    <Table head={<><th>No.</th><th>Tarikh</th><th>Pegawai</th><th>Keadaan</th><th>Status</th></>}>
                        {d.lawatan.map((v) => <tr key={v.id}><td><Link className="text-txt-primary hover:underline" href={`/admin/ptn/lawatan/${v.id}`}>{v.no_rujukan}</Link></td><td>{tarikh(v.tarikh_lawatan)}</td><td>{v.pegawai?.nama}</td><td>{v.keadaan_nama ?? '—'}</td><td>{v.status_nama}</td></tr>)}
                    </Table>
                )}
            </Card>
        </PageContainer>
    );
}
