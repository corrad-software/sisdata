'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { JENIS_PERMOHONAN_LABEL, PENGHANTARAN_LABEL, pwsApi, ralat, tarikhMasa, useCan } from '@/lib/pws';
import { Card, Kosong, Memuat, Select } from '../_components/common';

const PENGHANTARAN_VARIANT = { menunggu: 'neutral', dihantar: 'info', diterima: 'success', gagal: 'danger' } as const;

/** SRS-PWS-22: portal submissions and their delivery to PER / PJK, integration log, payment sessions. */
export default function PwsPemantauanPage() {
    const qc = useQueryClient();
    const can = useCan();
    const [jenis, setJenis] = useState('');
    const [penghantaran, setPenghantaran] = useState('');
    const [arah, setArah] = useState('');
    const [gagal, setGagal] = useState(false);
    const [msg, setMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const apps = useQuery({ queryKey: ['pws', 'permohonan', jenis, penghantaran], queryFn: () => pwsApi.permohonan({ jenis, status_penghantaran: penghantaran }) });
    const log = useQuery({ queryKey: ['pws', 'log', arah, gagal], queryFn: () => pwsApi.log({ arah, gagal }) });
    const pay = useQuery({ queryKey: ['pws', 'bayaran'], queryFn: () => pwsApi.bayaran().then((r) => r.data) });
    const retry = useMutation({
        mutationFn: () => pwsApi.cubaSemula(),
        onSuccess: (r) => {
            setMsg({ type: 'success', text: r.message });
            qc.invalidateQueries({ queryKey: ['pws'] });
        },
        onError: (e) => setMsg({ type: 'error', text: ralat(e) }),
    });

    if (apps.error) {
        return (
            <PageContainer>
                <Alert type="error">{ralat(apps.error)}</Alert>
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            {msg && <Alert type={msg.type}>{msg.text}</Alert>}
            <Card
                title="Permohonan daripada portal"
                actions={
                    <>
                        <div className="w-40">
                            <Select id="p-jenis" value={jenis} onChange={setJenis} empty="Semua jenis" options={Object.entries(JENIS_PERMOHONAN_LABEL).map(([value, label]) => ({ value, label }))} />
                        </div>
                        <div className="w-44">
                            <Select id="p-hantar" value={penghantaran} onChange={setPenghantaran} empty="Semua penghantaran" options={Object.entries(PENGHANTARAN_LABEL).map(([value, label]) => ({ value, label }))} />
                        </div>
                        {can('pws.pemantauan.kemas_kini') && (
                            <Button size="sm" variant="secondary" onClick={() => retry.mutate()} disabled={retry.isPending}>
                                Cuba semula yang gagal
                            </Button>
                        )}
                    </>
                }
            >
                {apps.isLoading || !apps.data ? (
                    <Memuat />
                ) : apps.data.data.length === 0 ? (
                    <Kosong>Tiada permohonan.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>No. rujukan</th>
                                <th>Jenis</th>
                                <th>Status permohonan</th>
                                <th>Penghantaran</th>
                                <th>Rujukan SISDATA</th>
                                <th>Dihantar</th>
                            </>
                        }
                    >
                        {apps.data.data.map((p) => (
                            <tr key={p.id}>
                                <td className="whitespace-nowrap font-medium">{p.no_rujukan}</td>
                                <td>{JENIS_PERMOHONAN_LABEL[p.jenis] ?? p.jenis}</td>
                                <td>{p.status_nama ?? p.status}</td>
                                <td>
                                    <Badge variant={PENGHANTARAN_VARIANT[p.status_penghantaran]}>{PENGHANTARAN_LABEL[p.status_penghantaran]}</Badge>
                                    {p.ralat_terakhir && <div className="max-w-[16rem] break-words text-body-xs text-txt-danger">{p.ralat_terakhir}</div>}
                                    {p.bilangan_cuba > 1 && <div className="text-body-xs text-txt-black-500">{p.bilangan_cuba} cubaan</div>}
                                </td>
                                <td className="whitespace-nowrap">{p.rujukan_sisdata ?? '—'}</td>
                                <td className="whitespace-nowrap text-body-xs">{tarikhMasa(p.dihantar)}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <Card title="Bayaran dalam talian (sesi portal)">
                {pay.isLoading || !pay.data ? (
                    <Memuat />
                ) : pay.data.length === 0 ? (
                    <Kosong>Tiada bayaran.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>No. rujukan</th>
                                <th>No. perjanjian</th>
                                <th className="text-right">Jumlah (RM)</th>
                                <th>Saluran</th>
                                <th>Status</th>
                                <th>Disahkan</th>
                            </>
                        }
                    >
                        {pay.data.map((b) => (
                            <tr key={b.no_rujukan}>
                                <td className="whitespace-nowrap">{b.no_rujukan}</td>
                                <td className="whitespace-nowrap">{b.no_kontrak}</td>
                                <td className="text-right">{b.jumlah}</td>
                                <td className="uppercase">{b.saluran}</td>
                                <td>
                                    <Badge variant={b.status === 'berjaya' ? 'success' : ['gagal', 'dibatalkan', 'tamat_tempoh'].includes(b.status) ? 'danger' : 'warning'}>{b.status}</Badge>
                                </td>
                                <td className="whitespace-nowrap text-body-xs">
                                    {tarikhMasa(b.disahkan)}
                                    {b.disahkan_melalui ? ` · ${b.disahkan_melalui}` : ''}
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <Card
                title="Log integrasi portal ↔ SISDATA"
                actions={
                    <>
                        <div className="w-36">
                            <Select id="l-arah" value={arah} onChange={setArah} empty="Semua arah" options={[{ value: 'masuk', label: 'Masuk' }, { value: 'keluar', label: 'Keluar' }]} />
                        </div>
                        <label className="flex items-center gap-2 text-body-sm">
                            <input type="checkbox" checked={gagal} onChange={(e) => setGagal(e.target.checked)} /> Gagal sahaja
                        </label>
                    </>
                }
            >
                {log.isLoading || !log.data ? (
                    <Memuat />
                ) : log.data.data.length === 0 ? (
                    <Kosong>Tiada rekod.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>Masa</th>
                                <th>Arah</th>
                                <th>Endpoint</th>
                                <th>Status</th>
                                <th>Tempoh</th>
                                <th>Rujukan</th>
                                <th>Ringkasan muatan</th>
                            </>
                        }
                    >
                        {log.data.data.map((l) => (
                            <tr key={l.id}>
                                <td className="whitespace-nowrap text-body-xs">{tarikhMasa(l.cap_masa)}</td>
                                <td>{l.arah}</td>
                                <td className="whitespace-nowrap text-body-xs">
                                    {l.kaedah} {l.endpoint}
                                    {l.klien ? <div className="text-txt-black-500">{l.klien}</div> : null}
                                </td>
                                <td>
                                    <Badge variant={l.kod_status !== null && l.kod_status < 400 ? 'success' : 'danger'}>{l.kod_status ?? '—'}</Badge>
                                    {l.bilangan_cuba_semula > 0 && <div className="text-body-xs text-txt-black-500">cubaan semula {l.bilangan_cuba_semula}</div>}
                                </td>
                                <td className="whitespace-nowrap text-body-xs">{l.tempoh_ms ?? '—'} ms</td>
                                <td className="whitespace-nowrap text-body-xs">{l.nombor_rujukan ?? '—'}</td>
                                <td className="max-w-[20rem] break-words font-mono text-body-xs">{l.ringkasan_payload ? JSON.stringify(l.ringkasan_payload) : '—'}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
        </PageContainer>
    );
}
