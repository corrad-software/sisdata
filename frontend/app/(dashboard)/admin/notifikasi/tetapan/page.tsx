'use client';

import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Toggle } from '@/components/ui/Toggle';
import { ApiError } from '@/lib/api';
import { fetchPreferences, KEUTAMAAN_BADGE, KEUTAMAAN_LABEL, SALURAN_LABEL, savePreferences, type Keutamaanku, type Saluran } from '@/lib/ntf';

export default function PreferencesPage() {
    const queryClient = useQueryClient();
    const { data, isLoading } = useQuery({ queryKey: ['ntf', 'keutamaan'], queryFn: fetchPreferences });
    const [edits, setEdits] = useState<Record<number, Partial<Keutamaanku>>>({});
    const rows = useMemo(() => (data?.data ?? []).map((r) => ({ ...r, ...edits[r.jenis_id] })), [data, edits]);

    const channels: Saluran[] = data?.meta.saluran_tersedia ?? ['dalam_sistem', 'emel'];
    const byModule = useMemo(() => {
        const groups: Record<string, Keutamaanku[]> = {};
        for (const row of rows) (groups[row.modul] ??= []).push(row);
        return groups;
    }, [rows]);

    const save = useMutation({
        mutationFn: () =>
            savePreferences(
                rows.filter((r) => !r.wajib).map((r) => ({ jenis_id: r.jenis_id, aktif: r.aktif, saluran: r.saluran, ringkasan: r.ringkasan })),
            ),
        onSuccess: async (res) => {
            toast.success(res.message);
            await queryClient.invalidateQueries({ queryKey: ['ntf', 'keutamaan'] });
            setEdits({});
        },
        onError: (error) => {
            const first = error instanceof ApiError && error.errors ? Object.values(error.errors)[0]?.[0] : null;
            toast.error(first ?? 'Tetapan tidak dapat disimpan.');
        },
    });

    function patch(id: number, change: Partial<Keutamaanku>) {
        setEdits((e) => ({ ...e, [id]: { ...e[id], ...change } }));
    }

    function toggleChannel(row: Keutamaanku, channel: Saluran) {
        const saluran = row.saluran.includes(channel) ? row.saluran.filter((c) => c !== channel) : [...row.saluran, channel];
        patch(row.jenis_id, { saluran });
    }

    return (
        <PageContainer>
            <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-6">
                <p className="text-body-sm text-txt-black-700">
                    Pilih jenis notifikasi yang anda mahu terima dan salurannya. Jenis bertanda <strong>Wajib</strong> ditetapkan oleh pentadbir dan
                    tidak boleh dimatikan. Notifikasi Biasa dan Maklumat boleh digabungkan dalam satu e-mel ringkasan harian.
                </p>

                {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}

                {Object.entries(byModule).map(([modul, list]) => (
                    <section key={modul} className="space-y-3">
                        <h3 className="font-heading text-body-md font-semibold text-txt-black-900">Modul {modul.toUpperCase()}</h3>
                        <ul className="divide-y divide-otl-divider rounded-lg border border-otl-gray-200">
                            {list.map((row) => (
                                <li key={row.jenis_id} className="flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between" data-testid={`pref-${row.kod}`}>
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-body-sm font-medium text-txt-black-900">{row.nama}</span>
                                            <Badge variant={KEUTAMAAN_BADGE[row.keutamaan]}>{KEUTAMAAN_LABEL[row.keutamaan]}</Badge>
                                            {row.wajib && (
                                                <Badge variant="neutral" shape="tag">
                                                    <svg className="me-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                                    </svg>
                                                    Wajib — tidak boleh diubah
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-body-xs text-txt-black-500 break-all">{row.kod}</p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-x-5 gap-y-3">
                                        {row.wajib ? (
                                            <span className="text-body-xs text-txt-black-500">
                                                Saluran: {row.saluran.map((c) => SALURAN_LABEL[c]).join(', ')}
                                            </span>
                                        ) : (
                                            <>
                                                <Toggle name={`aktif-${row.jenis_id}`} label="Terima" checked={row.aktif} onChange={(v) => patch(row.jenis_id, { aktif: v })} />
                                                {channels.map((c) => (
                                                    <label key={c} className="inline-flex items-center gap-2 text-body-sm text-txt-black-900">
                                                        <input
                                                            type="checkbox"
                                                            className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary disabled:opacity-50"
                                                            checked={row.saluran.includes(c)}
                                                            disabled={!row.aktif}
                                                            onChange={() => toggleChannel(row, c)}
                                                        />
                                                        {SALURAN_LABEL[c]}
                                                    </label>
                                                ))}
                                                {row.boleh_ringkasan && (
                                                    <label className="inline-flex items-center gap-2 text-body-sm text-txt-black-900">
                                                        <input
                                                            type="checkbox"
                                                            className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary disabled:opacity-50"
                                                            checked={row.ringkasan}
                                                            disabled={!row.aktif || !row.saluran.includes('emel')}
                                                            onChange={(e) => patch(row.jenis_id, { ringkasan: e.target.checked })}
                                                        />
                                                        Ringkasan harian
                                                    </label>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}

                <div className="flex justify-end">
                    <Button onClick={() => save.mutate()} disabled={save.isPending || isLoading}>
                        Simpan tetapan
                    </Button>
                </div>
            </div>
        </PageContainer>
    );
}
