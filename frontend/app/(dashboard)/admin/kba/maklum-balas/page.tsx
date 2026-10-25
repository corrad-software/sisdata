'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { kbaApi, PENILAIAN_LABEL, STATUS_TINDAKAN_LABEL, tarikhMasa, useCan, type MaklumBalas } from '@/lib/kba';
import { Card, inputClass, Kosong, Memuat, ralat, RalatAkses, Select } from '../_components/common';

function Item({ m, boleh }: { m: MaklumBalas; boleh: boolean }) {
    const qc = useQueryClient();
    const [status, setStatus] = useState<string>(m.status_tindakan);
    const [catatan, setCatatan] = useState(m.catatan_tindakan ?? '');
    const [msg, setMsg] = useState<string | null>(null);
    const simpan = useMutation({
        mutationFn: () => kbaApi.kemasKiniMaklumBalas(m.id, status, catatan),
        onSuccess: (r) => {
            setMsg(r.message);
            qc.invalidateQueries({ queryKey: ['kba', 'maklum-balas'] });
        },
        onError: (e) => setMsg(ralat(e)),
    });
    return (
        <li className="py-4 space-y-2">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={m.penilaian === 'membantu' ? 'success' : 'warning'}>{PENILAIAN_LABEL[m.penilaian]}</Badge>
                <Badge variant="neutral" shape="tag">
                    {STATUS_TINDAKAN_LABEL[m.status_tindakan]}
                </Badge>
                <span className="text-body-xs text-txt-black-500">
                    {tarikhMasa(m.cap_masa)} · log #{m.id_log_prompt}
                </span>
            </div>
            {m.log && (
                <div className="space-y-1 text-body-sm">
                    <p className="font-medium text-txt-black-900 break-words">S: {m.log.prompt}</p>
                    <p className="text-txt-black-700 whitespace-pre-wrap break-words line-clamp-4">J: {m.log.jawapan}</p>
                    {m.log.senarai_sumber_dipetik.length > 0 && (
                        <p className="text-body-xs text-txt-black-500">Sumber: {m.log.senarai_sumber_dipetik.map((s) => `${s.dokumen} ${s.versi} ${s.seksyen ?? ''}`).join('; ')}</p>
                    )}
                </div>
            )}
            {m.ulasan && <p className="rounded-md bg-bg-washed px-3 py-2 text-body-sm text-txt-black-700 break-words">“{m.ulasan}”</p>}
            {boleh && (
                <div className="flex flex-col gap-2 sm:flex-row">
                    <div className="sm:w-48">
                        <Select id={`st-${m.id}`} value={status} onChange={setStatus} options={Object.entries(STATUS_TINDAKAN_LABEL).map(([value, label]) => ({ value, label }))} />
                    </div>
                    <input aria-label="Catatan tindakan" className={inputClass} value={catatan} onChange={(e) => setCatatan(e.target.value)} placeholder="Tindakan penambahbaikan (cth. artikel dikemas kini)" maxLength={1000} />
                    <Button size="sm" onClick={() => simpan.mutate()} disabled={simpan.isPending}>
                        Simpan
                    </Button>
                </div>
            )}
            {msg && <p className="text-body-xs text-txt-black-500">{msg}</p>}
        </li>
    );
}

/** SRS-KBA-17: user feedback on AI answers, routed to content admins as improvement input. */
export default function MaklumBalasPage() {
    const can = useCan();
    const [status, setStatus] = useState('');
    const [penilaian, setPenilaian] = useState('');
    const { data, error, isLoading } = useQuery({ queryKey: ['kba', 'maklum-balas', status, penilaian], queryFn: () => kbaApi.senaraiMaklumBalas({ status_tindakan: status, penilaian }).then((r) => r.data) });

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }
    return (
        <PageContainer>
            <Card
                title="Maklum balas pengguna"
                actions={
                    <>
                        <div className="w-44">
                            <Select id="mb-st" value={status} onChange={setStatus} empty="Semua status" options={Object.entries(STATUS_TINDAKAN_LABEL).map(([value, label]) => ({ value, label }))} />
                        </div>
                        <div className="w-44">
                            <Select id="mb-pn" value={penilaian} onChange={setPenilaian} empty="Semua penilaian" options={Object.entries(PENILAIAN_LABEL).map(([value, label]) => ({ value, label }))} />
                        </div>
                    </>
                }
            >
                {isLoading || !data ? (
                    <Memuat />
                ) : data.length === 0 ? (
                    <Kosong>Tiada maklum balas.</Kosong>
                ) : (
                    <ul className="divide-y divide-otl-divider">
                        {data.map((m) => (
                            <Item key={m.id} m={m} boleh={can('kba.maklum_balas.kemas_kini')} />
                        ))}
                    </ul>
                )}
            </Card>
        </PageContainer>
    );
}
