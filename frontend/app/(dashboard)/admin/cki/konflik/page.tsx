'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { ckiApi, MEDAN_LABEL, masa, ralat, type Konflik } from '@/lib/cki';
import { Card, Field, inputClass, Kosong, Status } from '../_components/common';

function Banding({ k }: { k: Konflik }) {
    const qc = useQueryClient();
    const [catatan, setCatatan] = useState('');
    const fields = Array.from(new Set([...k.medan_berkonflik, 'no_bil', 'amaun_pokok', 'amaun_denda', 'tarikh_akhir', 'status_bayaran']));

    async function putus(keputusan: 'terima_api' | 'kekal_manual') {
        try {
            const r = await ckiApi.selesaiKonflik(k.id, { keputusan, catatan });
            toast.success(r.message ?? 'Disimpan.');
            qc.invalidateQueries({ queryKey: ['cki', 'konflik'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <Card
            title={
                <span className="flex flex-wrap items-center gap-2">
                    <Link href={`/admin/cki/bil/${k.id_bil}`} className="text-txt-primary hover:underline">
                        {k.no_bil} ({k.tahun_taksiran})
                    </Link>
                    <Status kod={k.status_resolusi} nama={k.status_resolusi} />
                </span>
            }
        >
            <p className="text-body-xs text-txt-black-500">Dikesan {masa(k.dicipta)} · daerah {k.kod_daerah}</p>
            <Table head={<><th>Medan</th><th>Rekod manual</th><th>Rekod API</th></>}>
                {fields.map((f) => (
                    <tr key={f} className={k.medan_berkonflik.includes(f) ? 'bg-bg-warning-50' : ''}>
                        <td>{MEDAN_LABEL[f] ?? f}</td>
                        <td className="break-words">{k.nilai_manual[f] ?? '—'}</td>
                        <td className="break-words">{k.nilai_api[f] ?? '—'}</td>
                    </tr>
                ))}
            </Table>
            {k.status_resolusi === 'menunggu' ? (
                <div className="space-y-3">
                    <Field label="Catatan pengesahan (wajib)" htmlFor={`k-${k.id}`}>
                        <input id={`k-${k.id}`} className={inputClass} value={catatan} onChange={(e) => setCatatan(e.target.value)} />
                    </Field>
                    <div className="flex flex-wrap gap-2">
                        <Button onClick={() => putus('terima_api')} disabled={catatan.length < 5}>
                            Terima rekod API
                        </Button>
                        <Button variant="outline" onClick={() => putus('kekal_manual')} disabled={catatan.length < 5}>
                            Kekalkan rekod manual
                        </Button>
                    </div>
                    <p className="text-body-xs text-txt-black-500">Kedua-dua versi disimpan dalam jejak audit.</p>
                </div>
            ) : (
                <p className="text-body-sm">
                    Diselesaikan {masa(k.tarikh_resolusi)} — {k.catatan}
                </p>
            )}
        </Card>
    );
}

/** SRS-CKI-09: manual vs API comparison after re-synchronisation; an officer confirms before a record is replaced. */
export default function KonflikPage() {
    const [status, setStatus] = useState('menunggu');
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'konflik', status], queryFn: () => ckiApi.konflik({ status }) });
    const rows = data?.data ?? [];

    return (
        <PageContainer>
            <div className="flex flex-wrap gap-2" role="group" aria-label="Status konflik">
                {[['menunggu', 'Menunggu pengesahan'], ['semua', 'Semua']].map(([k, l]) => (
                    <Button key={k} size="sm" variant={status === k ? 'primary' : 'outline'} onClick={() => setStatus(k)}>
                        {l}
                    </Button>
                ))}
            </div>
            {error && <p className="text-txt-danger">{ralat(error)}</p>}
            {isLoading ? <p>Memuatkan…</p> : rows.length === 0 ? <Kosong>Tiada konflik.</Kosong> : rows.map((k) => <Banding key={k.id} k={k} />)}
        </PageContainer>
    );
}
