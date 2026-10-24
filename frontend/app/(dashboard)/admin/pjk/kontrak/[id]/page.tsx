'use client';

import { use } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { DocumentAttachments } from '@/components/dms/DocumentAttachments';
import { pjkApi, ralat, tarikh, useCan } from '@/lib/pjk';
import { Card, InfoGrid, Sintetik, Status } from '../../_components/common';
import { BayaranPanel, CajPanel, DepositPanel, LejarPanel, PindaanPanel } from './panels';

const TABS = { ringkasan: 'Ringkasan', caj: 'Jadual caj', lejar: 'Lejar', bayaran: 'Bayaran & resit', deposit: 'Deposit', pindaan: 'Pindaan, versi & surat', dokumen: 'Dokumen' };

/** One agreement: lifecycle, schedule, ledger, payments, deposit, amendments, letters, documents. */
export default function KontrakPage({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const can = useCan();
    const qc = useQueryClient();
    const search = useSearchParams();
    const tab = search.get('tab');
    const { data: k, isLoading, error } = useQuery({ queryKey: ['pjk', 'kontrak', id], queryFn: () => pjkApi.kontrakSatu(id) });

    async function hantar() {
        try {
            const r = await pjkApi.hantar(id);
            toast.success(r.message ?? 'Dihantar.');
            qc.invalidateQueries({ queryKey: ['pjk', 'kontrak', id] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    if (isLoading) return <PageContainer>Memuatkan…</PageContainer>;
    if (error || !k) return <PageContainer><p className="text-txt-danger">{error ? ralat(error) : 'Tidak dijumpai.'}</p></PageContainer>;

    const p = k.penyewa_rekod;

    return (
        <PageContainer>
            <Card
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {k.no_kontrak} <Status kod={k.status} nama={k.status_nama} /> <Sintetik show={k.sintetik} />
                    </span>
                }
                actions={
                    k.status === 'draf' && can('pjk.kontrak.kemas_kini') ? (
                        <Button size="sm" onClick={hantar}>
                            Hantar untuk kelulusan
                        </Button>
                    ) : undefined
                }
            >
                <InfoGrid
                    items={[
                        ['Penyewa', k.penyewa],
                        ['Premis', k.premis ? <Link href={`/admin/pjk/premis?premis=${k.premis.id}`} className="text-txt-primary hover:underline">{k.premis.kod_premis} · {k.premis.nama_premis}</Link> : '—'],
                        ['Lot', k.lot ? <Link href={k.lot.pautan} className="text-txt-primary hover:underline">{k.lot.no_lot}</Link> : '—'],
                        ['Tempoh', `${tarikh(k.tarikh_mula)} – ${tarikh(k.tarikh_tamat)}${k.baki_hari !== null ? ` (${k.baki_hari} hari lagi)` : ''}`],
                        ['Kadar sebulan', k.kadar.rm],
                        ['Baki lejar', k.baki_lejar?.rm ?? '—'],
                    ]}
                />
            </Card>

            <Card>
                <Tabs tabs={TABS} defaultTab={tab && tab in TABS ? tab : 'ringkasan'}>
                    <TabPanel value="ringkasan">
                        <InfoGrid
                            items={[
                                ['Jenis perjanjian', k.jenis_perjanjian_nama],
                                ['Kategori hartanah', k.kategori_hartanah_nama],
                                ['Kitaran caj', `${k.kitaran_caj_nama} · tempoh bayar ${k.hari_tempoh_bayar} hari`],
                                ['Deposit', k.deposit.rm],
                                ['Nilai perjanjian', k.nilai_kontrak.rm],
                                ['Daerah / mukim', `${k.kod_daerah ?? '—'} / ${k.kod_mukim ?? '—'}`],
                                ['Sumber', k.sumber === 'portal' ? 'Portal Hartanah Wakaf' : 'Kaunter'],
                                ['Versi semasa', `v${k.no_versi}`],
                                ['Status sejak', tarikh(k.tarikh_status)],
                                ['Perjanjian asal (pembaharuan)', k.id_kontrak_asal ? <Link href={`/admin/pjk/kontrak/${k.id_kontrak_asal}`} className="text-txt-primary hover:underline">Lihat</Link> : '—'],
                                ['No. pengenalan / pendaftaran', p ? (p.no_pengenalan ?? p.no_pendaftaran_syarikat ?? '—') : '—'],
                                ['Penamatan', k.tarikh_penamatan ? `${tarikh(k.tarikh_penamatan)} — ${k.sebab_penamatan ?? ''}` : '—'],
                            ]}
                        />
                        {k.terma && (
                            <div className="mt-4">
                                <p className="text-body-xs font-medium text-txt-black-500">Terma khas</p>
                                <p className="text-body-sm whitespace-pre-wrap break-words">{k.terma}</p>
                            </div>
                        )}
                    </TabPanel>
                    <TabPanel value="caj">
                        <CajPanel k={k} />
                    </TabPanel>
                    <TabPanel value="lejar">
                        <LejarPanel k={k} />
                    </TabPanel>
                    <TabPanel value="bayaran">
                        <BayaranPanel k={k} />
                    </TabPanel>
                    <TabPanel value="deposit">
                        <DepositPanel k={k} />
                    </TabPanel>
                    <TabPanel value="pindaan">
                        <PindaanPanel k={k} />
                    </TabPanel>
                    <TabPanel value="dokumen">
                        <DocumentAttachments ownerType="kontrak_pajakan" ownerId={k.id} sourceModule="pjk" ownerLink={`/admin/pjk/kontrak/${k.id}`} readOnly={!can('pjk.kontrak.kemas_kini')} />
                    </TabPanel>
                </Tabs>
            </Card>
        </PageContainer>
    );
}
