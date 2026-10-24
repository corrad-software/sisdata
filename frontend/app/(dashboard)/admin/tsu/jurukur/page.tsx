'use client';

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { ralat, rm, tarikh, tsuApi } from '@/lib/tsu';
import { Card, Kosong, Ralat } from '../_components/common';
import { BorangKemajuan } from '../_components/TabUkur';

/** SRS-TSU-11 + security control: an external licensed surveyor sees and updates progress of their OWN appointments only. */
export default function LantikanSaya() {
    const qc = useQueryClient();
    const { data, error } = useQuery({ queryKey: ['tsu', 'lantikan-saya'], queryFn: tsuApi.lantikanSaya });

    return (
        <PageContainer>
            {error && <Ralat>{ralat(error)}</Ralat>}
            {!data ? (
                <Kosong>Memuatkan…</Kosong>
            ) : data.length === 0 ? (
                <Card><Kosong>Tiada lantikan kerja ukur bagi akaun anda.</Kosong></Card>
            ) : (
                data.map((l) => (
                    <Card key={l.id} title={`${l.no_rujukan_urusan} — ${l.jenis_urusan}`}>
                        <p className="text-body-sm">{l.skop_kerja}</p>
                        <p className="text-body-xs text-txt-black-500">
                            Surat setuju terima {l.no_surat_setuju_terima} · {rm(l.nilai_kontrak)} · Mula {tarikh(l.tarikh_mula)} · Jangka siap {tarikh(l.tarikh_jangka_siap)}
                        </p>
                        <p className="text-body-sm">
                            Kemajuan: <strong>{l.peratus_siap_semasa}%</strong> (dijangka {l.peratus_dijangka}%) {l.lewat && <Badge variant="danger">Terkebelakang</Badge>}
                        </p>
                        {l.status === 'aktif' ? <BorangKemajuan lantikan={l.id} onDone={() => qc.invalidateQueries({ queryKey: ['tsu', 'lantikan-saya'] })} /> : <Badge variant="success">Siap</Badge>}
                    </Card>
                ))
            )}
        </PageContainer>
    );
}
