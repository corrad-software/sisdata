'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { STATUS_LABEL, formatDateTime, rbc } from '@/lib/rbc';
import type { Catalogue, Role, ScopeMap, UserAccess } from '@/lib/rbc';
import type { AccountStatus } from '@/lib/types';
import { Card, EmptyRow, ScopeEditor, ScrollX, Select, StatusBadge, TextInput } from '../../rbc/_components/ui';

function onError(error: unknown) {
    toast.error(error instanceof ApiError && error.errors ? Object.values(error.errors).flat().join(' ') : error instanceof Error ? error.message : 'Tindakan gagal.');
}

function Detail({ user, roles, catalogue }: { user: UserAccess; roles: Role[]; catalogue: Catalogue }) {
    const queryClient = useQueryClient();
    const refresh = (message: string) => {
        queryClient.invalidateQueries({ queryKey: ['rbc', 'user', String(user.id)] });
        queryClient.invalidateQueries({ queryKey: ['users'] });
        toast.success(message);
    };

    const [statusOpen, setStatusOpen] = useState(false);
    const [nextStatus, setNextStatus] = useState<AccountStatus>(user.status_akaun === 'aktif' ? 'gantung' : 'aktif');
    const [reason, setReason] = useState('');
    const manualRoleIds = user.peranan.filter((p) => p.sumber === 'manual').map((p) => p.id);
    const [selectedRoles, setSelectedRoles] = useState<number[]>(manualRoleIds);
    const [profile, setProfile] = useState({
        no_pekerja: user.no_pekerja ?? '',
        bahagian: user.bahagian ?? '',
        unit: user.unit ?? '',
        jawatan: user.jawatan ?? '',
        tarikh_mula_capaian: user.tarikh_mula_capaian ?? '',
        tarikh_tamat_capaian: user.tarikh_tamat_capaian ?? '',
    });
    const manualScopes: ScopeMap = {};
    for (const s of user.skop.filter((x) => x.sumber === 'manual')) (manualScopes[s.jenis_skop] ??= []).push(s.nilai_skop);

    const changeStatus = useMutation({
        mutationFn: () => rbc.setUserStatus(user.id, nextStatus, reason),
        onSuccess: () => {
            setStatusOpen(false);
            refresh(nextStatus === 'aktif' ? 'Akaun diaktifkan.' : 'Status dikemas kini — semua sesi pengguna telah ditamatkan.');
        },
        onError,
    });
    const saveRoles = useMutation({ mutationFn: () => rbc.setUserRoles(user.id, selectedRoles), onSuccess: () => refresh('Peranan disimpan dan berkuat kuasa serta-merta.'), onError });
    const saveScopes = useMutation({ mutationFn: (s: ScopeMap) => rbc.setUserScopes(user.id, s), onSuccess: () => refresh('Skop disimpan.'), onError });
    const saveProfile = useMutation({
        mutationFn: () => rbc.setUserAccessPeriod(user.id, Object.fromEntries(Object.entries(profile).map(([k, v]) => [k, v || null]))),
        onSuccess: () => refresh('Maklumat disimpan.'),
        onError,
    });
    const unlock = useMutation({ mutationFn: () => rbc.unlockUser(user.id), onSuccess: () => refresh('Akaun dibuka kunci.'), onError });
    const endSessions = useMutation({ mutationFn: () => rbc.endUserSessions(user.id), onSuccess: (r) => refresh(`${r.data.ditamatkan} sesi ditamatkan.`), onError });

    const ssoRoles = user.peranan.filter((p) => p.sumber === 'sso');

    return (
        <>
            <PageHeader title={user.name} breadcrumbs={[{ label: 'Pengguna', href: '/admin/users' }, { label: user.name }]} />
            <PageContainer>
                {user.dikunci_hingga && (
                    <Alert type="error">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span>Akaun dikunci hingga {formatDateTime(user.dikunci_hingga)} kerana cubaan log masuk gagal.</span>
                            <Button size="sm" variant="secondary" onClick={() => unlock.mutate()}>
                                Buka Kunci
                            </Button>
                        </div>
                    </Alert>
                )}

                <Card
                    title="Akaun"
                    actions={
                        <Button variant={user.status_akaun === 'aktif' ? 'danger' : 'primary'} onClick={() => setStatusOpen(true)}>
                            Tukar Status
                        </Button>
                    }
                >
                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-body-sm">
                        <div>
                            <dt className="text-txt-black-500">E-mel</dt>
                            <dd className="font-medium break-all">{user.email}</dd>
                        </div>
                        <div>
                            <dt className="text-txt-black-500">Status</dt>
                            <dd>
                                <StatusBadge status={user.status_akaun} />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-txt-black-500">Pengesahan</dt>
                            <dd className="font-medium">{user.jenis_pengesahan === 'sso' ? 'SSO MAIS' : 'Akaun dalaman'}{user.sso && user.jenis_pengesahan === 'dalaman' ? ' + SSO' : ''}</dd>
                        </div>
                        <div>
                            <dt className="text-txt-black-500">Log masuk terakhir</dt>
                            <dd className="font-medium">{formatDateTime(user.tarikh_log_masuk_akhir)}</dd>
                        </div>
                    </dl>
                </Card>

                <Card title="Maklumat organisasi & tempoh capaian">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <TextInput id="no_pekerja" label="No. pekerja" value={profile.no_pekerja} onChange={(v) => setProfile((p) => ({ ...p, no_pekerja: v }))} />
                        <TextInput id="bahagian" label="Bahagian" value={profile.bahagian} onChange={(v) => setProfile((p) => ({ ...p, bahagian: v }))} />
                        <TextInput id="unit" label="Unit" value={profile.unit} onChange={(v) => setProfile((p) => ({ ...p, unit: v }))} />
                        <TextInput id="jawatan" label="Jawatan" value={profile.jawatan} onChange={(v) => setProfile((p) => ({ ...p, jawatan: v }))} />
                        <TextInput id="mula" type="date" label="Tarikh mula capaian" value={profile.tarikh_mula_capaian} onChange={(v) => setProfile((p) => ({ ...p, tarikh_mula_capaian: v }))} />
                        <TextInput id="tamat" type="date" label="Tarikh tamat capaian" value={profile.tarikh_tamat_capaian} onChange={(v) => setProfile((p) => ({ ...p, tarikh_tamat_capaian: v }))} />
                    </div>
                    <p className="mt-2 text-body-xs text-txt-black-500">Selepas tarikh tamat capaian, status bertukar kepada Tamat Tempoh secara automatik.</p>
                    <div className="mt-4 flex justify-end">
                        <Button onClick={() => saveProfile.mutate()} disabled={saveProfile.isPending}>
                            Simpan
                        </Button>
                    </div>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card title="Peranan" actions={<Button onClick={() => saveRoles.mutate()} disabled={saveRoles.isPending}>Simpan Peranan</Button>}>
                        <div className="space-y-2">
                            {roles.map((r) => (
                                <label key={r.id} className="flex items-start gap-2 text-body-sm">
                                    <input
                                        type="checkbox"
                                        checked={selectedRoles.includes(r.id)}
                                        onChange={() => setSelectedRoles((s) => (s.includes(r.id) ? s.filter((x) => x !== r.id) : [...s, r.id]))}
                                        className="mt-0.5 rounded border-otl-gray-300 text-primary-600"
                                    />
                                    <span>
                                        {r.nama}
                                        {r.status !== 'aktif' && <span className="text-txt-black-500"> (tidak aktif)</span>}
                                    </span>
                                </label>
                            ))}
                        </div>
                        {ssoRoles.length > 0 && (
                            <p className="mt-4 text-body-xs text-txt-black-500">
                                Daripada SSO MAIS (diselaraskan setiap log masuk): {ssoRoles.map((r) => r.nama).join(', ')}
                            </p>
                        )}
                    </Card>

                    <Card title="Skop capaian pengguna">
                        <ScopeEditor types={catalogue.jenis_skop} value={manualScopes} onSave={(s) => saveScopes.mutate(s)} saving={saveScopes.isPending} />
                        <div className="mt-4 border-t border-otl-divider pt-4">
                            <h4 className="text-body-sm font-medium text-txt-black-900 mb-2">Skop berkesan (peranan ∩ pengguna)</h4>
                            <ul className="text-body-sm text-txt-black-700 space-y-1">
                                {Object.entries(user.skop_berkesan).map(([type, values]) => (
                                    <li key={type}>
                                        {catalogue.jenis_skop[type] ?? type}: {values === null ? 'tiada had' : values.length === 0 ? 'tiada rekod' : values.join(', ')}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </Card>
                </div>

                <Card title={`Kebenaran berkesan (${user.kebenaran.filter((k) => k.split('.').length === 3).length})`}>
                    <div className="flex flex-wrap gap-1">
                        {user.kebenaran.filter((k) => k.split('.').length === 3).map((k) => (
                            <Badge key={k}>{k}</Badge>
                        ))}
                        {user.kebenaran.length === 0 && <span className="text-body-sm text-txt-black-500">Tiada kebenaran.</span>}
                    </div>
                </Card>

                <Card
                    title={`Sesi aktif (${user.sesi_aktif.length})`}
                    actions={
                        user.sesi_aktif.length > 0 ? (
                            <Button variant="danger" onClick={() => endSessions.mutate()} disabled={endSessions.isPending}>
                                Tamatkan Semua Sesi
                            </Button>
                        ) : undefined
                    }
                >
                    <ScrollX>
                        <Table head={<><th>Peranti</th><th>Alamat IP</th><th>Mula</th><th>Aktiviti terakhir</th></>}>
                            {user.sesi_aktif.length === 0 ? (
                                <EmptyRow colSpan={4}>Tiada sesi aktif.</EmptyRow>
                            ) : (
                                user.sesi_aktif.map((s) => (
                                    <tr key={s.id}>
                                        <td>
                                            {s.pelayar} · {s.platform} {s.semasa && <Badge variant="info">Sesi anda</Badge>}
                                        </td>
                                        <td>{s.alamat_ip}</td>
                                        <td className="whitespace-nowrap">{formatDateTime(s.tarikh_mula)}</td>
                                        <td className="whitespace-nowrap">{formatDateTime(s.tarikh_aktiviti_akhir)}</td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </ScrollX>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card title="Log status akaun">
                        <ul className="space-y-3 text-body-sm">
                            {user.log_status.length === 0 && <li className="text-txt-black-500">Tiada perubahan status.</li>}
                            {user.log_status.map((l, i) => (
                                <li key={i} className="border-l-2 border-otl-gray-200 ps-3">
                                    <div>
                                        {STATUS_LABEL[l.status_lama]} → <strong>{STATUS_LABEL[l.status_baru]}</strong>
                                    </div>
                                    <div className="text-body-xs text-txt-black-500">
                                        {formatDateTime(l.created_at)} · {l.pelaku ?? 'Sistem'} {l.sebab ? `· ${l.sebab}` : ''}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card title="Log masuk terkini">
                        <ul className="space-y-3 text-body-sm">
                            {user.log_masuk_terkini.length === 0 && <li className="text-txt-black-500">Tiada rekod.</li>}
                            {user.log_masuk_terkini.map((l) => (
                                <li key={l.id} className="flex items-start justify-between gap-3">
                                    <div>
                                        <div>
                                            {formatDateTime(l.created_at)} · {l.alamat_ip}
                                        </div>
                                        <div className="text-body-xs text-txt-black-500">
                                            {l.lokasi} · {l.pelayar} {l.sebab_label ? `· ${l.sebab_label}` : ''}
                                        </div>
                                    </div>
                                    {l.berjaya ? <Badge variant="success">Berjaya</Badge> : <Badge variant="danger">Gagal</Badge>}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            </PageContainer>

            <Modal open={statusOpen} onClose={() => setStatusOpen(false)}>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        changeStatus.mutate();
                    }}
                    className="p-6 space-y-4"
                >
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tukar status akaun</h2>
                    <Select id="status-baru" label="Status baharu" value={nextStatus} onChange={(e) => setNextStatus(e.target.value as AccountStatus)}>
                        {(Object.keys(STATUS_LABEL) as AccountStatus[])
                            .filter((s) => s !== user.status_akaun)
                            .map((s) => (
                                <option key={s} value={s}>
                                    {STATUS_LABEL[s]}
                                </option>
                            ))}
                    </Select>
                    <FieldInput label="Sebab" name="sebab" value={reason} onChange={setReason} required />
                    {nextStatus !== 'aktif' && <Alert type="warning">Semua sesi aktif dan token capaian pengguna ini akan ditamatkan serta-merta.</Alert>}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setStatusOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={changeStatus.isPending}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default function UserDetailPage() {
    const { id } = useParams<{ id: string }>();
    const user = useQuery({ queryKey: ['rbc', 'user', id], queryFn: () => rbc.userAccess(id) });
    const roles = useQuery({ queryKey: ['rbc', 'roles'], queryFn: rbc.roles });
    const catalogue = useQuery({ queryKey: ['rbc', 'catalogue'], queryFn: rbc.catalogue });

    if (user.data && roles.data && catalogue.data) {
        return <Detail key={user.dataUpdatedAt} user={user.data.data} roles={roles.data.data} catalogue={catalogue.data.data} />;
    }

    return (
        <>
            <PageHeader title="Pengguna" breadcrumbs={[{ label: 'Pengguna', href: '/admin/users' }, { label: 'Butiran' }]} />
            <PageContainer>
                <p className="text-body-sm text-txt-black-500">{user.error || roles.error || catalogue.error ? 'Maklumat tidak dapat dimuatkan.' : 'Memuatkan…'}</p>
            </PageContainer>
        </>
    );
}
