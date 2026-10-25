'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { lapApi, ralat, useCan, type PapanBi } from '@/lib/lap';
import { Card, Field, inputClass, Kosong } from '../_components/common';

/**
 * SRS-LAP-01 — approved Metabase (OSS) dashboards embedded with a signed
 * token; the user's district scope is a LOCKED parameter the browser cannot
 * change. Metabase itself reads only aggregate `laporan.bi_*` views.
 */
export default function BiPage() {
    const can = useCan();
    const qc = useQueryClient();
    const q = useQuery({ queryKey: ['lap', 'bi'], queryFn: () => lapApi.bi() });
    const [pilih, setPilih] = useState<string | null>(null);
    const embed = useQuery({ queryKey: ['lap', 'bi', 'embed', pilih], queryFn: () => lapApi.embed(pilih as string).then((r) => r.data), enabled: !!pilih, staleTime: 5 * 60_000 });
    const st = q.data?.status;

    return (
        <PageContainer>
            <Card title="Papan pemuka BI (Metabase)">
                {st && (
                    <div className="flex flex-wrap gap-2 items-center text-body-sm">
                        <Badge variant={st.hidup ? 'success' : 'danger'}>{st.hidup ? `Metabase aktif ${st.versi ?? ''}` : 'Metabase tidak dapat dihubungi'}</Badge>
                        <Badge variant={st.dikonfigurasi ? 'success' : 'warning'}>{st.dikonfigurasi ? 'Pembenaman bertandatangan dikonfigurasi' : 'Rahsia pembenaman belum ditetapkan'}</Badge>
                    </div>
                )}
                {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}
                {q.data && q.data.data.length === 0 && <Kosong>Tiada papan pemuka BI yang diluluskan untuk anda.</Kosong>}
                <div className="flex flex-wrap gap-2">
                    {q.data?.data.filter((p) => p.aktif && p.boleh_buka).map((p) => (
                        <Button key={p.kod} variant={pilih === p.kod ? 'primary' : 'outline'} size="sm" onClick={() => setPilih(p.kod)}>
                            {p.nama}
                        </Button>
                    ))}
                </div>
            </Card>

            {pilih && (
                <Card title={embed.data?.nama ?? 'Memuatkan…'}>
                    {embed.isError && <Callout type="danger">{ralat(embed.error)}</Callout>}
                    {embed.data && (
                        <>
                            <p className="text-body-xs text-txt-black-500">
                                Skop dikunci:{' '}
                                {Object.entries(embed.data.parameter)
                                    .map(([k, v]) => `${k} = ${v.length ? v.join(', ') : 'semua'}`)
                                    .join('; ') || 'tiada'}
                            </p>
                            <iframe title={embed.data.nama} src={embed.data.url} className="w-full rounded-md border border-otl-gray-200" style={{ height: 640 }} />
                        </>
                    )}
                </Card>
            )}

            {can('lap.bi.kemas_kini') && <Daftar papan={q.data?.data ?? []} onSaved={() => qc.invalidateQueries({ queryKey: ['lap', 'bi'] })} />}
        </PageContainer>
    );
}

function Daftar({ papan, onSaved }: { papan: PapanBi[]; onSaved: () => void }) {
    const [kod, setKod] = useState('');
    const [nama, setNama] = useState('');
    const [jenis, setJenis] = useState<'dashboard' | 'question'>('dashboard');
    const [id, setId] = useState('');
    const [kebenaran, setKebenaran] = useState('');
    const [param, setParam] = useState('kod_daerah');
    const m = useMutation({
        mutationFn: () => lapApi.simpanBi({ kod, nama, jenis, id_metabase: Number(id), kebenaran: kebenaran || null, parameter_kunci: param ? { [param]: 'daerah' } : null, aktif: true }),
        onSuccess: () => {
            toast.success('Papan pemuka didaftarkan.');
            onSaved();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const padam = useMutation({ mutationFn: (pid: number) => lapApi.padamBi(pid), onSuccess: onSaved, onError: (e) => toast.error(ralat(e)) });

    return (
        <Card title="Daftar papan pemuka / soalan Metabase">
            <p className="text-body-sm text-txt-black-500">
                Dalam Metabase: aktifkan pembenaman statik untuk papan pemuka / soalan, tetapkan parameter daerah sebagai «Locked». Kemudian daftarkan di sini — SISDATA menandatangani token dengan skop daerah pengguna.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Field label="Kod" htmlFor="bkod">
                    <input id="bkod" className={inputClass} value={kod} onChange={(e) => setKod(e.target.value.toLowerCase())} placeholder="portfolio_daerah" />
                </Field>
                <Field label="Nama" htmlFor="bnama">
                    <input id="bnama" className={inputClass} value={nama} onChange={(e) => setNama(e.target.value)} />
                </Field>
                <Field label="Jenis" htmlFor="bjenis">
                    <select id="bjenis" className={inputClass} value={jenis} onChange={(e) => setJenis(e.target.value as typeof jenis)}>
                        <option value="dashboard">Papan pemuka</option>
                        <option value="question">Soalan</option>
                    </select>
                </Field>
                <Field label="ID Metabase" htmlFor="bid">
                    <input id="bid" type="number" className={inputClass} value={id} onChange={(e) => setId(e.target.value)} />
                </Field>
                <Field label="Parameter dikunci kepada skop daerah" htmlFor="bparam">
                    <input id="bparam" className={inputClass} value={param} onChange={(e) => setParam(e.target.value)} />
                </Field>
                <Field label="Kebenaran tambahan (pilihan)" htmlFor="bkeb">
                    <input id="bkeb" className={inputClass} value={kebenaran} onChange={(e) => setKebenaran(e.target.value)} placeholder="cth. pjk.laporan.lihat" />
                </Field>
            </div>
            <div className="flex justify-end">
                <Button onClick={() => m.mutate()} disabled={!kod || !nama || !id || m.isPending}>
                    Daftar
                </Button>
            </div>
            {papan.length > 0 && (
                <ul className="divide-y divide-otl-gray-200">
                    {papan.map((p) => (
                        <li key={p.id} className="py-2 flex flex-wrap justify-between gap-2 text-body-sm">
                            <span>
                                <span className="font-mono">{p.kod}</span> — {p.nama} ({p.jenis} #{p.id_metabase}) {p.kebenaran && <Badge variant="neutral">{p.kebenaran}</Badge>}
                            </span>
                            <Button size="sm" variant="danger" onClick={() => confirm('Padam pendaftaran?') && padam.mutate(p.id)}>
                                Padam
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
