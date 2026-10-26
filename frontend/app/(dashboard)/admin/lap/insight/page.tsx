'use client';

import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { lapApi, ralat, tarikhMasa, type Insight } from '@/lib/lap';
import { Card, Kosong, LabelCadangan } from '../_components/common';

const TAHAP: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = { kritikal: 'danger', tinggi: 'warning', perhatian: 'warning', maklumat: 'info', baik: 'success' };

/** SRS-LAP-20 executive insight summary (state-wide on schedule, or for the user's own scope). */
export default function InsightPage() {
    const qc = useQueryClient();
    const q = useQuery({ queryKey: ['lap', 'insight'], queryFn: () => lapApi.insight() });
    const jana = useMutation({
        mutationFn: () => lapApi.janaInsight(),
        onSuccess: () => {
            toast.success('Insight eksekutif dijana.');
            qc.invalidateQueries({ queryKey: ['lap', 'insight'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const [terkini, ...lama] = q.data?.data ?? [];

    return (
        <PageContainer>
            <LabelCadangan teks={q.data?.label_cadangan} />
            <Card title="Ringkasan insight eksekutif" actions={<Button onClick={() => jana.mutate()} disabled={jana.isPending}>{jana.isPending ? 'Menjana…' : 'Jana untuk skop saya'}</Button>}>
                {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}
                {q.data && !terkini && <Kosong>Belum ada ringkasan. Ringkasan seluruh negeri dijana automatik mengikut kekerapan yang ditetapkan MAIS.</Kosong>}
                {terkini && <Ringkasan i={terkini} />}
            </Card>
            {lama.length > 0 && (
                <Card title="Ringkasan terdahulu">
                    <ul className="divide-y divide-otl-gray-200">
                        {lama.map((i) => (
                            <li key={i.id} className="py-3 space-y-2">
                                <p className="text-body-sm font-medium">
                                    {i.tempoh} · {i.negeri ? 'Seluruh negeri' : 'Skop saya'} · {tarikhMasa(i.tarikh_jana)}
                                </p>
                                <p className="text-body-sm text-txt-black-700">{i.isu_utama.map((x) => x.teks).join(' ')}</p>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </PageContainer>
    );
}

function Senarai({ tajuk, items }: { tajuk: string; items: { tahap?: string; teks: string; pautan?: string }[] }) {
    return (
        <div className="space-y-2">
            <h4 className="font-semibold text-body-md text-txt-black-900">{tajuk}</h4>
            {items.length === 0 ? (
                <p className="text-body-sm text-txt-black-500">Tiada.</p>
            ) : (
                <ul className="space-y-2">
                    {items.map((x, n) => (
                        <li key={n} className="flex gap-2 items-start text-body-sm">
                            {x.tahap && <Badge variant={TAHAP[x.tahap] ?? 'neutral'}>{x.tahap}</Badge>}
                            <span>
                                {x.teks}{' '}
                                {x.pautan && (
                                    <Link href={x.pautan} className="text-txt-primary hover:underline">
                                        Lihat
                                    </Link>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Ringkasan({ i }: { i: Insight }) {
    return (
        <div className="space-y-6">
            <p className="text-body-sm text-txt-black-500">
                Tempoh {i.tempoh} · {i.negeri ? 'Seluruh negeri' : 'Skop capaian saya'} · dijana {tarikhMasa(i.tarikh_jana)} ({i.sumber === 'berjadual' ? 'berjadual' : 'manual'})
            </p>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Senarai tajuk="Isu utama" items={i.isu_utama} />
                <Senarai tajuk="Risiko" items={i.risiko} />
                <Senarai tajuk="Tindakan segera" items={i.tindakan_segera} />
            </div>
        </div>
    );
}
