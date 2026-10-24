'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Toggle } from '@/components/ui/Toggle';
import { Textarea } from '@/components/ui/Textarea';
import { FieldInput } from '@/components/ui/FieldInput';
import { api } from '@/lib/api';
import { errorText, fetchPenyelenggaraan, type Penyelenggaraan } from '@/lib/adm';

const SECTIONS = [
    { href: 'parameter', title: 'Parameter Sistem', desc: 'Tetapan mengikut modul tanpa ubah kod. Parameter kritikal perlu kelulusan pentadbir kedua.' },
    { href: 'kod-rujukan', title: 'Kod Rujukan', desc: 'Daerah, mukim, jenis hak milik, jenis dokumen dan lain-lain — berversi, import berkelompok.' },
    { href: 'templat', title: 'Templat Laporan', desc: 'Kandungan, medan dinamik, format output dan versi templat laporan.' },
    { href: 'kelulusan', title: 'Kelulusan Konfigurasi', desc: 'Perubahan konfigurasi kritikal yang menunggu keputusan pentadbir kedua.' },
    { href: 'tugasan-berjadual', title: 'Tugasan Berjadual', desc: 'Jadual cron/selang, laksana manual, sejarah larian dan amaran kegagalan.' },
    { href: 'log-ralat', title: 'Log Ralat & Integrasi', desc: 'Cari mengikut tempoh, modul, aras, kod ralat, pengguna atau ID korelasi; eksport CSV.' },
    { href: 'kesihatan', title: 'Kesihatan Sistem', desc: 'Status perkhidmatan, baris gilir, integrasi, storan, pangkalan data dan ralat terkini.' },
    { href: 'integrasi', title: 'Integrasi & Rahsia', desc: 'Endpoint, tamat masa, kadar capaian, jadual dan rahsia tersulit setiap integrasi.' },
    { href: 'carian/tetapan', title: 'Tetapan Carian', desc: 'Medan boleh dicari, pemberat, sinonim dan indeks semula carian global.' },
];

export default function PentadbiranPage() {
    return (
        <PageContainer>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {SECTIONS.map((s) => (
                    <Link
                        key={s.href}
                        href={`/admin/pentadbiran/${s.href}`}
                        className="block rounded-lg border border-otl-gray-200 bg-bg-white p-5 shadow-card hover:border-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary"
                    >
                        <h3 className="font-heading font-semibold text-body-md text-txt-black-900">{s.title}</h3>
                        <p className="mt-1 text-body-sm text-txt-black-500">{s.desc}</p>
                    </Link>
                ))}
                <Link
                    href="/admin/notifikasi/jenis"
                    className="block rounded-lg border border-dashed border-otl-gray-300 bg-bg-white p-5 hover:border-primary-600"
                >
                    <h3 className="font-heading font-semibold text-body-md text-txt-black-900">Templat Notifikasi ↗</h3>
                    <p className="mt-1 text-body-sm text-txt-black-500">Diurus oleh modul Notifikasi (MOD-NTF).</p>
                </Link>
            </div>

            <MaintenancePanel />
        </PageContainer>
    );
}

function MaintenancePanel() {
    const queryClient = useQueryClient();
    const { data } = useQuery({ queryKey: ['adm', 'penyelenggaraan'], queryFn: fetchPenyelenggaraan });
    if (!data) return null;

    return <MaintenanceForm key={JSON.stringify(data.data)} state={data.data} onSaved={() => queryClient.invalidateQueries({ queryKey: ['adm', 'penyelenggaraan'] })} />;
}

function MaintenanceForm({ state, onSaved }: { state: Penyelenggaraan; onSaved: () => void }) {
    const [notis, setNotis] = useState(state.notis);
    const [tamat, setTamat] = useState(state.tamat_dijangka ?? '');

    const save = useMutation({
        mutationFn: (aktif: boolean) =>
            api.put<{ message: string; data: Penyelenggaraan }>('/adm/penyelenggaraan', { aktif, notis, tamat_dijangka: tamat || null }),
        onSuccess: (res) => {
            toast.success(res.message);
            onSaved();
        },
        onError: (e) => toast.error(errorText(e)),
    });

    return (
        <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-6 shadow-card space-y-4" aria-labelledby="penyelenggaraan-tajuk">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 id="penyelenggaraan-tajuk" className="font-heading font-semibold text-body-lg text-txt-black-900">
                        Mod Penyelenggaraan
                    </h3>
                    <p className="text-body-sm text-txt-black-500">
                        Papar notis kepada pengguna dan sekat transaksi baharu. Pentadbir masih boleh berurus niaga.
                    </p>
                </div>
                <Badge variant={state.aktif ? 'warning' : 'success'}>{state.aktif ? 'Aktif' : 'Tidak aktif'}</Badge>
            </div>
            <Textarea label="Notis kepada pengguna" name="notis" value={notis} onChange={setNotis} rows={2} maxLength={1000} />
            <FieldInput label="Jangkaan tamat (pilihan)" name="tamat" type="datetime-local" value={tamat} onChange={setTamat} />
            <div className="flex items-center gap-3">
                <Toggle
                    name="mod_penyelenggaraan"
                    label={state.aktif ? 'Matikan mod penyelenggaraan' : 'Hidupkan mod penyelenggaraan'}
                    checked={state.aktif ?? false}
                    onChange={(v) => save.mutate(v)}
                />
                {state.aktif && (
                    <Button size="sm" variant="secondary" onClick={() => save.mutate(true)} disabled={save.isPending}>
                        Kemas kini notis
                    </Button>
                )}
            </div>
        </section>
    );
}
