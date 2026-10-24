'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { InputLabel } from '@/components/ui/InputLabel';
import { Card, EmptyRow, FieldError, inputClass } from '@/components/pro/common';
import { errorMessage, fieldErrors, fmtNombor, fmtTarikh, proApi, useCan, useMe, type KunciApi } from '@/lib/pro';

function CreateModal({ open, skop, onClose }: { open: boolean; skop: Record<string, string>; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [nama, setNama] = useState('');
    const [sistem, setSistem] = useState('');
    const [pilih, setPilih] = useState<string[]>([]);
    const [hari, setHari] = useState('90');
    const [kadar, setKadar] = useState('60');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [token, setToken] = useState<{ token: string; tamat_pada: string | null } | null>(null);

    async function submit(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        setError(null);
        if (pilih.length === 0) {
            setErrors({ skop: 'Pilih sekurang-kurangnya satu skop.' });
            return;
        }
        setBusy(true);
        try {
            const res = await proApi.ciptaKunci({ nama: nama.trim(), sistem_pemanggil: sistem.trim(), skop: pilih, tamat_hari: Number(hari), had_kadar_seminit: Number(kadar) });
            setToken({ token: res.data.token, tamat_pada: res.data.tamat_pada });
            queryClient.invalidateQueries({ queryKey: ['pro', 'kunci-api'] });
        } catch (err) {
            setErrors(fieldErrors(err));
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    async function copy() {
        if (!token) return;
        try {
            await navigator.clipboard.writeText(token.token);
            toast.success('Token disalin ke papan klip.');
        } catch {
            toast.error('Tidak dapat menyalin. Salin token secara manual.');
        }
    }

    return (
        <Modal open={open} onClose={onClose}>
            {token ? (
                <div className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Kunci API dicipta</h2>
                    <Callout type="warning" title="Salin token sekarang">
                        Token ini hanya dipaparkan <strong>sekali</strong>. Simpan dengan selamat dalam sistem pemanggil — ia tidak boleh dipaparkan semula.
                    </Callout>
                    <div className="flex flex-col sm:flex-row gap-2">
                        <code className="flex-1 min-w-0 break-all rounded-md border border-otl-gray-300 bg-bg-washed px-3 py-2 font-mono text-body-xs select-all">{token.token}</code>
                        <Button onClick={copy}>Salin</Button>
                    </div>
                    <p className="text-body-sm text-txt-black-700">Tamat pada {fmtTarikh(token.tamat_pada, true)}. Hantar sebagai pengepala <code className="font-mono">Authorization: Bearer &lt;token&gt;</code>.</p>
                    <div className="flex justify-end">
                        <Button variant="secondary" onClick={onClose}>
                            Tutup
                        </Button>
                    </div>
                </div>
            ) : (
                <form onSubmit={submit} className="p-6 space-y-4" noValidate>
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Cipta kunci API</h2>
                    <div>
                        <InputLabel htmlFor="ka-nama" className="mb-1.5">
                            Nama <span className="text-danger-600">*</span>
                        </InputLabel>
                        <input id="ka-nama" className={inputClass} value={nama} maxLength={80} onChange={(e) => setNama(e.target.value)} />
                        <FieldError message={errors.nama} />
                    </div>
                    <div>
                        <InputLabel htmlFor="ka-sistem" className="mb-1.5">
                            Sistem pemanggil <span className="text-danger-600">*</span>
                        </InputLabel>
                        <input id="ka-sistem" className={inputClass} value={sistem} maxLength={100} onChange={(e) => setSistem(e.target.value)} placeholder="cth. Portal GIS MAIS" />
                        <FieldError message={errors.sistem_pemanggil} />
                    </div>
                    <fieldset>
                        <legend className="text-body-sm font-medium text-txt-black-900 mb-1.5">
                            Skop <span className="text-danger-600">*</span>
                        </legend>
                        <div className="space-y-2">
                            {Object.entries(skop).map(([k, l]) => (
                                <label key={k} className="flex items-center gap-2 text-body-sm">
                                    <input
                                        type="checkbox"
                                        className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                        checked={pilih.includes(k)}
                                        onChange={(e) => setPilih((cur) => (e.target.checked ? [...cur, k] : cur.filter((x) => x !== k)))}
                                    />
                                    {l} <span className="font-mono text-body-xs text-txt-black-500">{k}</span>
                                </label>
                            ))}
                        </div>
                        <FieldError message={errors.skop} />
                    </fieldset>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="ka-hari" className="mb-1.5">
                                Tempoh sah (hari)
                            </InputLabel>
                            <input id="ka-hari" type="number" min={1} max={365} className={inputClass} value={hari} onChange={(e) => setHari(e.target.value)} />
                            <FieldError message={errors.tamat_hari} />
                        </div>
                        <div>
                            <InputLabel htmlFor="ka-kadar" className="mb-1.5">
                                Had kadar (panggilan seminit)
                            </InputLabel>
                            <input id="ka-kadar" type="number" min={1} max={6000} className={inputClass} value={kadar} onChange={(e) => setKadar(e.target.value)} />
                            <FieldError message={errors.had_kadar_seminit} />
                        </div>
                    </div>
                    {error && <Alert type="error">{error}</Alert>}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={onClose}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={busy}>
                            {busy ? 'Mencipta…' : 'Cipta kunci'}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}

export default function KunciApiPage() {
    const can = useCan();
    const { data: me } = useMe();
    const queryClient = useQueryClient();
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'kunci-api'], queryFn: () => proApi.kunciApi() });
    const usage = useQuery({ queryKey: ['pro', 'kunci-api', 'penggunaan'], queryFn: () => proApi.penggunaanApi().then((r) => r.data) });
    const [open, setOpen] = useState(false);
    const [modalKey, setModalKey] = useState(0);
    const rows = data?.data ?? [];
    const skop = data?.skop ?? {};

    async function revoke(k: KunciApi) {
        if (!window.confirm(`Batalkan kunci "${k.nama ?? k.id}" untuk ${k.sistem_pemanggil}? Sistem pemanggil tidak lagi boleh mengakses API.`)) return;
        try {
            await proApi.batalKunci(k.id);
            toast.success('Kunci API dibatalkan.');
            queryClient.invalidateQueries({ queryKey: ['pro', 'kunci-api'] });
        } catch (e) {
            toast.error(errorMessage(e));
        }
    }

    if (me && !can('pro.api.cipta')) {
        return (
            <PageContainer>
                <Alert type="error">Anda tidak mempunyai kebenaran untuk mengurus kunci API.</Alert>
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            <Card
                title="Kunci API"
                actions={
                    <Button
                        onClick={() => {
                            setModalKey((k) => k + 1);
                            setOpen(true);
                        }}
                    >
                        Cipta kunci
                    </Button>
                }
            >
                <p className="text-body-sm text-txt-black-500">
                    Kunci untuk sistem luar membaca API lot, GeoJSON dan kedudukan kewangan. Spesifikasi:{' '}
                    <a href="/api/v1/pro/openapi.yaml" className="text-txt-primary hover:underline" target="_blank" rel="noreferrer">
                        OpenAPI (YAML)
                    </a>
                    .
                </p>
                {error ? (
                    <Alert type="error">{errorMessage(error)}</Alert>
                ) : (
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>Nama / sistem</th>
                                    <th className="hidden md:table-cell">Skop</th>
                                    <th>Status</th>
                                    <th className="hidden lg:table-cell text-right">Had / minit</th>
                                    <th className="hidden md:table-cell text-right">Panggilan</th>
                                    <th className="hidden lg:table-cell">Terakhir digunakan</th>
                                    <th className="hidden sm:table-cell">Tamat</th>
                                    <th>
                                        <span className="sr-only">Tindakan</span>
                                    </th>
                                </>
                            }
                        >
                            {isLoading ? (
                                <EmptyRow colSpan={8}>Memuatkan…</EmptyRow>
                            ) : rows.length === 0 ? (
                                <EmptyRow colSpan={8}>Tiada kunci API.</EmptyRow>
                            ) : (
                                rows.map((k) => (
                                    <tr key={k.id}>
                                        <td className="min-w-[10rem]">
                                            <span className="font-medium">{k.nama ?? `#${k.id}`}</span>
                                            <span className="block text-body-xs text-txt-black-500">
                                                {k.sistem_pemanggil}
                                                {k.pemilik ? ` · ${k.pemilik}` : ''}
                                            </span>
                                        </td>
                                        <td className="hidden md:table-cell">
                                            <span className="flex flex-wrap gap-1">
                                                {k.skop.map((s) => (
                                                    <Badge key={s} variant="neutral" shape="tag">
                                                        {skop[s] ?? s}
                                                    </Badge>
                                                ))}
                                            </span>
                                        </td>
                                        <td>
                                            <Badge variant={k.status === 'aktif' ? 'success' : k.status === 'tamat' ? 'warning' : 'neutral'}>
                                                {k.status === 'aktif' ? 'Aktif' : k.status === 'tamat' ? 'Tamat tempoh' : 'Dibatalkan'}
                                            </Badge>
                                        </td>
                                        <td className="hidden lg:table-cell text-right">{k.had_kadar_seminit}</td>
                                        <td className="hidden md:table-cell text-right">{fmtNombor(k.bil_panggilan, 0)}</td>
                                        <td className="hidden lg:table-cell whitespace-nowrap">{fmtTarikh(k.terakhir_digunakan, true)}</td>
                                        <td className="hidden sm:table-cell whitespace-nowrap">{fmtTarikh(k.tamat_pada)}</td>
                                        <td>
                                            {k.status === 'aktif' && (
                                                <Button size="sm" variant="danger" onClick={() => revoke(k)}>
                                                    Batal
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </div>
                )}
            </Card>

            <Card title="Penggunaan API">
                {usage.error ? (
                    <Alert type="error">{errorMessage(usage.error)}</Alert>
                ) : (
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>Sistem pemanggil</th>
                                    <th className="text-right">Panggilan</th>
                                    <th className="text-right">Gagal</th>
                                    <th className="hidden sm:table-cell">Terakhir</th>
                                </>
                            }
                        >
                            {usage.isLoading ? (
                                <EmptyRow colSpan={4}>Memuatkan…</EmptyRow>
                            ) : (usage.data ?? []).length === 0 ? (
                                <EmptyRow colSpan={4}>Tiada panggilan direkodkan.</EmptyRow>
                            ) : (
                                usage.data?.map((u) => (
                                    <tr key={u.id_token}>
                                        <td>
                                            {u.sistem_pemanggil}
                                            <span className="block text-body-xs text-txt-black-500">Kunci #{u.id_token}</span>
                                        </td>
                                        <td className="text-right">{fmtNombor(Number(u.bil_panggilan), 0)}</td>
                                        <td className={`text-right ${Number(u.bil_gagal) > 0 ? 'text-txt-danger' : ''}`}>{fmtNombor(Number(u.bil_gagal), 0)}</td>
                                        <td className="hidden sm:table-cell whitespace-nowrap">{fmtTarikh(u.terakhir, true)}</td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </div>
                )}
            </Card>

            <CreateModal key={modalKey} open={open} skop={skop} onClose={() => setOpen(false)} />
        </PageContainer>
    );
}
