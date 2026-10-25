'use client';

import { useState, type FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { pwsApi, ralat, tarikhMasa, useCan, type Klien } from '@/lib/pws';
import { Card, Field, inputClass, Kosong, Memuat } from '../_components/common';

/** The secret is returned exactly once (create / rotate) — shown here until dismissed. */
function RahsiaSekali({ klien, onClose }: { klien: Klien; onClose: () => void }) {
    return (
        <Alert type="warning">
            <div className="space-y-2">
                <p className="font-semibold">Simpan kelayakan ini sekarang — rahsia tidak akan dipaparkan lagi.</p>
                <p className="break-all">
                    ID klien: <code>{klien.id_klien}</code>
                </p>
                <p className="break-all">
                    Rahsia klien: <code>{klien.client_secret}</code>
                </p>
                <p className="text-body-xs">Masukkan dalam wp-config.php pelayan portal (HWS_CLIENT_ID / HWS_CLIENT_SECRET). Jangan hantar melalui e-mel.</p>
                <Button size="sm" variant="secondary" onClick={onClose}>
                    Saya telah menyimpannya
                </Button>
            </div>
        </Alert>
    );
}

/** SRS-PWS-23: API clients of the public gateway — scope, IP allowlist, rate limit, expiry, rotation, revocation (MFA). */
export default function PwsKlienPage() {
    const qc = useQueryClient();
    const can = useCan();
    const urus = can('pws.klien.kemas_kini');
    const { data, error, isLoading } = useQuery({ queryKey: ['pws', 'klien'], queryFn: () => pwsApi.klien() });
    const [nama, setNama] = useState('');
    const [skop, setSkop] = useState<string[]>(['baca', 'permohonan', 'bayaran', 'aduan', 'sokongan', 'analitik']);
    const [ip, setIp] = useState('');
    const [had, setHad] = useState(120);
    const [luput, setLuput] = useState('');
    const [rahsia, setRahsia] = useState<Klien | null>(null);
    const [msg, setMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const refresh = () => qc.invalidateQueries({ queryKey: ['pws', 'klien'] });
    const fail = (e: unknown) => setMsg({ type: 'error', text: ralat(e) });

    const create = useMutation({
        mutationFn: () =>
            pwsApi.ciptaKlien({
                nama,
                skop,
                ip_dibenarkan: ip
                    .split(/[\s,]+/)
                    .map((s) => s.trim())
                    .filter(Boolean),
                had_kadar_minit: had,
                tarikh_luput: luput || null,
            }),
        onSuccess: (r) => {
            setRahsia(r.data);
            setMsg({ type: 'success', text: r.message });
            setNama('');
            refresh();
        },
        onError: fail,
    });
    const rotate = useMutation({
        mutationFn: (id: number) => pwsApi.putarKlien(id),
        onSuccess: (r) => {
            setRahsia(r.data);
            setMsg({ type: 'success', text: r.message });
            refresh();
        },
        onError: fail,
    });
    const revoke = useMutation({
        mutationFn: ({ id, sebab }: { id: number; sebab: string }) => pwsApi.batalKlien(id, sebab),
        onSuccess: (r) => {
            setMsg({ type: 'success', text: r.message });
            refresh();
        },
        onError: fail,
    });

    if (error) {
        return (
            <PageContainer>
                <Alert type="error">{ralat(error)}</Alert>
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            {rahsia && <RahsiaSekali klien={rahsia} onClose={() => setRahsia(null)} />}
            {msg && !rahsia && <Alert type={msg.type}>{msg.text}</Alert>}
            <Card title="Klien API get laluan awam">
                {isLoading || !data ? (
                    <Memuat />
                ) : data.data.length === 0 ? (
                    <Kosong>Tiada klien API.</Kosong>
                ) : (
                    <Table
                        head={
                            <>
                                <th>Klien</th>
                                <th>Skop</th>
                                <th>IP dibenarkan</th>
                                <th>Had/minit</th>
                                <th>Luput</th>
                                <th>Terakhir digunakan</th>
                                <th>Status</th>
                                {urus && <th>Tindakan</th>}
                            </>
                        }
                    >
                        {data.data.map((k) => (
                            <tr key={k.id}>
                                <td className="min-w-[12rem]">
                                    <div className="font-medium">{k.nama}</div>
                                    <code className="break-all text-body-xs text-txt-black-500">{k.id_klien}</code>
                                    {k.rahsia_lama_sah_hingga && <div className="text-body-xs text-txt-warning">Rahsia lama sah hingga {tarikhMasa(k.rahsia_lama_sah_hingga)}</div>}
                                </td>
                                <td className="min-w-[10rem] text-body-xs">{k.skop.join(', ')}</td>
                                <td className="text-body-xs">{k.ip_dibenarkan.length ? k.ip_dibenarkan.join(', ') : <span className="text-txt-warning">Mana-mana (dev sahaja)</span>}</td>
                                <td>{k.had_kadar_minit}</td>
                                <td className="whitespace-nowrap">{k.tarikh_luput ?? '—'}</td>
                                <td className="whitespace-nowrap text-body-xs">{tarikhMasa(k.terakhir_digunakan)}</td>
                                <td>
                                    <Badge variant={k.aktif ? 'success' : 'danger'}>{k.aktif ? 'Aktif' : k.status === 'dibatalkan' ? 'Dibatalkan' : 'Luput'}</Badge>
                                </td>
                                {urus && (
                                    <td className="whitespace-nowrap">
                                        {k.status === 'aktif' && (
                                            <div className="flex gap-2">
                                                <Button size="sm" variant="secondary" onClick={() => rotate.mutate(k.id)} disabled={rotate.isPending}>
                                                    Putar rahsia
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="danger"
                                                    disabled={revoke.isPending}
                                                    onClick={() => {
                                                        const sebab = window.prompt('Sebab pembatalan (min. 5 aksara):');
                                                        if (sebab) revoke.mutate({ id: k.id, sebab });
                                                    }}
                                                >
                                                    Batal
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            {urus && data && (
                <Card title="Klien baharu">
                    <form
                        className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            setMsg(null);
                            create.mutate();
                        }}
                    >
                        <Field label="Nama klien" htmlFor="c-nama" className="sm:col-span-2">
                            <input id="c-nama" className={inputClass} value={nama} onChange={(e) => setNama(e.target.value)} required maxLength={150} placeholder="Pelayan Portal (produksi)" />
                        </Field>
                        <Field label="Had kadar seminit" htmlFor="c-had">
                            <input id="c-had" type="number" min={1} max={10000} className={inputClass} value={had} onChange={(e) => setHad(Number(e.target.value))} />
                        </Field>
                        <Field label="Tarikh luput" htmlFor="c-luput">
                            <input id="c-luput" type="date" className={inputClass} value={luput} onChange={(e) => setLuput(e.target.value)} />
                        </Field>
                        <Field label="IP / CIDR dibenarkan" htmlFor="c-ip" className="sm:col-span-2" help="Pisahkan dengan koma. Wajib untuk produksi (IP pelayan portal / WAF).">
                            <input id="c-ip" className={inputClass} value={ip} onChange={(e) => setIp(e.target.value)} placeholder="10.20.30.40, 10.20.31.0/24" />
                        </Field>
                        <fieldset className="sm:col-span-2">
                            <legend className="mb-1.5 text-body-sm font-medium text-txt-black-700">Skop</legend>
                            <div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                                {data.skop.map((s) => (
                                    <label key={s.kod} className="flex items-start gap-2 text-body-sm">
                                        <input
                                            type="checkbox"
                                            className="mt-1"
                                            checked={skop.includes(s.kod)}
                                            onChange={(e) => setSkop(e.target.checked ? [...skop, s.kod] : skop.filter((x) => x !== s.kod))}
                                        />
                                        <span>
                                            <span className="font-medium">{s.kod}</span> — {s.nama}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        <div className="sm:col-span-2 lg:col-span-4">
                            <Button type="submit" disabled={create.isPending || skop.length === 0}>
                                Cipta klien
                            </Button>
                            <p className="mt-2 text-body-xs text-txt-black-500">Pengurusan kunci API memerlukan log masuk SSO MAIS dengan pengesahan dua faktor.</p>
                        </div>
                    </form>
                </Card>
            )}
        </PageContainer>
    );
}
