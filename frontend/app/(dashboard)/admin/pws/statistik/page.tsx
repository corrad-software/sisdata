'use client';

import { useState, type FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { JENIS_PERMOHONAN_LABEL, pwsApi, ralat, tarikhMasa, useCan, type StatistikData } from '@/lib/pws';
import { Card, Field, inputClass, Kosong, Memuat, StatusKandunganBadge } from '../_components/common';

function Ringkas({ d }: { d: StatistikData }) {
    return (
        <div className="space-y-3 text-body-sm">
            <ul className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                {Object.entries(d.permohonan).map(([jenis, p]) => (
                    <li key={jenis} className="rounded-md bg-bg-washed p-3">
                        <span className="block text-body-xs text-txt-black-500">{JENIS_PERMOHONAN_LABEL[jenis] ?? jenis}</span>
                        <span className="font-heading text-heading-2xs font-semibold">{String(p.jumlah)}</span>
                    </li>
                ))}
                <li className="rounded-md bg-bg-washed p-3">
                    <span className="block text-body-xs text-txt-black-500">Premis ditawarkan</span>
                    <span className="font-heading text-heading-2xs font-semibold">{d.premis_ditawarkan ?? '—'}</span>
                </li>
            </ul>
            {d.bayaran_dalam_talian.length > 0 && (
                <p>
                    Bayaran dalam talian berjaya:{' '}
                    {d.bayaran_dalam_talian.map((b) => `${b.saluran.toUpperCase()} ${String(b.bilangan)}${b.amaun ? ` (RM ${b.amaun})` : ''}`).join(' · ')}
                </p>
            )}
            <p className="text-body-xs text-txt-black-500">{d.nota}</p>
        </div>
    );
}

/** Aggregate statistics for the portal: preview → snapshot → WFE approval → published. */
export default function PwsStatistikPage() {
    const qc = useQueryClient();
    const can = useCan();
    const [tempoh, setTempoh] = useState(String(new Date().getFullYear()));
    const [tajuk, setTajuk] = useState('');
    const [msg, setMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const list = useQuery({ queryKey: ['pws', 'statistik'], queryFn: () => pwsApi.statistik().then((r) => r.data) });
    const preview = useQuery({ queryKey: ['pws', 'statistik', 'pratonton', tempoh], queryFn: () => pwsApi.pratonton(tempoh).then((r) => r.data), enabled: /^\d{4}(-\d{2})?$/.test(tempoh) });
    const ok = (text: string) => {
        setMsg({ type: 'success', text });
        qc.invalidateQueries({ queryKey: ['pws', 'statistik'] });
    };
    const fail = (e: unknown) => setMsg({ type: 'error', text: ralat(e) });
    const create = useMutation({ mutationFn: () => pwsApi.ciptaStatistik(tajuk, tempoh), onSuccess: (r) => ok(r.message), onError: fail });
    const submit = useMutation({ mutationFn: (id: number) => pwsApi.hantarStatistik(id), onSuccess: (r) => ok(r.message), onError: fail });
    const withdraw = useMutation({ mutationFn: (id: number) => pwsApi.tarikStatistik(id), onSuccess: (r) => ok(r.message), onError: fail });

    if (list.error) {
        return (
            <PageContainer>
                <Alert type="error">{ralat(list.error)}</Alert>
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            {msg && <Alert type={msg.type}>{msg.text}</Alert>}
            <Card title="Pratonton statistik agregat">
                <Field label="Tempoh (TTTT atau TTTT-BB)" htmlFor="s-tempoh" className="max-w-xs">
                    <input id="s-tempoh" className={inputClass} value={tempoh} onChange={(e) => setTempoh(e.target.value)} placeholder="2026 atau 2026-10" />
                </Field>
                {preview.isLoading ? <Memuat /> : preview.data ? <Ringkas d={preview.data} /> : preview.error ? <Alert type="error">{ralat(preview.error)}</Alert> : null}
                {can('pws.statistik.cipta') && (
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            create.mutate();
                        }}
                    >
                        <Field label="Tajuk terbitan" htmlFor="s-tajuk" className="min-w-0 flex-1">
                            <input id="s-tajuk" className={inputClass} value={tajuk} onChange={(e) => setTajuk(e.target.value)} required maxLength={200} placeholder={`Statistik Portal ${tempoh}`} />
                        </Field>
                        <Button type="submit" variant="secondary" disabled={create.isPending}>
                            Simpan sebagai draf terbitan
                        </Button>
                    </form>
                )}
            </Card>
            <Card title="Terbitan statistik">
                {list.isLoading || !list.data ? (
                    <Memuat />
                ) : list.data.length === 0 ? (
                    <Kosong>Belum ada terbitan.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>Tajuk</th>
                                <th>Tempoh</th>
                                <th>Status</th>
                                <th>Diterbitkan</th>
                                <th>Tindakan</th>
                            </>
                        }
                    >
                        {list.data.map((s) => (
                            <tr key={s.id}>
                                <td className="min-w-[12rem]">{s.tajuk}</td>
                                <td>{s.tempoh}</td>
                                <td>
                                    <StatusKandunganBadge s={s.status} />
                                </td>
                                <td className="whitespace-nowrap text-body-xs">{tarikhMasa(s.tarikh_terbit)}</td>
                                <td className="whitespace-nowrap">
                                    {can('pws.statistik.cipta') && ['draf', 'ditolak', 'ditarik'].includes(s.status) && (
                                        <Button size="sm" onClick={() => submit.mutate(s.id)} disabled={submit.isPending}>
                                            Hantar untuk kelulusan
                                        </Button>
                                    )}
                                    {can('pws.statistik.cipta') && s.status === 'diterbitkan' && (
                                        <Button size="sm" variant="danger" onClick={() => withdraw.mutate(s.id)} disabled={withdraw.isPending}>
                                            Tarik balik
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
        </PageContainer>
    );
}
