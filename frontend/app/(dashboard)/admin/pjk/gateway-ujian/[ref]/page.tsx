'use client';

import { use, useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { pjkApi, ralat } from '@/lib/pjk';
import { Card, InfoGrid, Status } from '../../_components/common';

const KEPUTUSAN = [
    { kod: 'berjaya', label: 'Bayaran berjaya', variant: 'primary' as const },
    { kod: 'gagal', label: 'Bayaran gagal', variant: 'danger' as const },
    { kod: 'dibatalkan', label: 'Pembayar membatalkan', variant: 'secondary' as const },
    { kod: 'tiada_panggilan_balik', label: 'Berjaya tanpa panggilan balik', variant: 'outline' as const },
    { kod: 'tamat_masa', label: 'Tiada respons (tamat masa)', variant: 'outline' as const },
];

/**
 * Stand-in for the bank / gateway payment page (mock adapter only). In
 * production the payer lands on the gateway's own page; card data never
 * passes through SISDATA.
 */
export default function GatewayUjianPage({ params }: { params: Promise<{ ref: string }> }) {
    const { ref } = use(params);
    const qc = useQueryClient();
    const [busy, setBusy] = useState(false);
    const { data: t, error } = useQuery({ queryKey: ['pjk', 'gateway-ujian', ref], queryFn: () => pjkApi.gatewayUjian(decodeURIComponent(ref)) });

    async function pilih(kod: string) {
        setBusy(true);
        try {
            const r = await pjkApi.simulasiGateway(decodeURIComponent(ref), kod);
            toast.success(`Status transaksi: ${r.data.status_nama}${r.panggilan_balik ? ` (panggilan balik: ${r.panggilan_balik})` : ' (tiada panggilan balik — akan diselesaikan oleh semakan status berjadual)'}`);
            qc.invalidateQueries({ queryKey: ['pjk', 'gateway-ujian', ref] });
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <PageContainer>
            <Alert type="warning">
                <strong>Persekitaran ujian payment gateway.</strong> Halaman ini mensimulasikan laman bank / gateway (FPX atau kad). Panggilan balik ditandatangani (HMAC) dan melalui webhook yang sama seperti gateway sebenar.
            </Alert>
            {error && <p className="text-txt-danger">{ralat(error)}</p>}
            {t && (
                <Card title={`Bayaran ${t.no_kontrak ?? ''}`}>
                    <InfoGrid
                        items={[
                            ['No. rujukan', t.no_rujukan],
                            ['Saluran', t.saluran_nama],
                            ['Amaun', t.amaun.rm],
                            ['Pembayar', t.nama_pembayar ?? '—'],
                            ['Status', <Status key="s" kod={t.status} nama={t.status_nama} />],
                        ]}
                    />
                    {t.status === 'menunggu' ? (
                        <div className="flex flex-wrap gap-2">
                            {KEPUTUSAN.map((k) => (
                                <Button key={k.kod} variant={k.variant} disabled={busy} onClick={() => pilih(k.kod)}>
                                    {k.label}
                                </Button>
                            ))}
                        </div>
                    ) : (
                        <Link href={`/admin/pjk/kontrak/${t.kontrak_id}?tab=bayaran`} className="text-txt-primary hover:underline">
                            Kembali ke perjanjian
                        </Link>
                    )}
                </Card>
            )}
        </PageContainer>
    );
}
