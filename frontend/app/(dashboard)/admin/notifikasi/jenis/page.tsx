'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { fetchTypes, KEUTAMAAN_BADGE, KEUTAMAAN_LABEL, SALURAN_LABEL } from '@/lib/ntf';

export default function TypesPage() {
    const [modul, setModul] = useState('');
    const { data, isLoading, error } = useQuery({ queryKey: ['ntf', 'jenis'], queryFn: fetchTypes });
    const all = data?.data ?? [];
    const modules = Array.from(new Set(all.map((j) => j.modul))).sort();
    const rows = modul ? all.filter((j) => j.modul === modul) : all;

    if (error instanceof ApiError && error.status === 403) {
        return (
            <PageContainer>
                <Alert type="error">Anda tidak mempunyai kebenaran untuk melihat konfigurasi notifikasi.</Alert>
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-body-sm text-txt-black-700">
                        Jenis notifikasi diisytihar oleh modul. Pentadbir boleh mengubah keutamaan, saluran, penerima, kekerapan dan templat tanpa
                        pengubahsuaian kod.
                    </p>
                    <select
                        value={modul}
                        onChange={(e) => setModul(e.target.value)}
                        aria-label="Tapis modul"
                        className="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm sm:w-48"
                    >
                        <option value="">Semua modul</option>
                        {modules.map((m) => (
                            <option key={m} value={m}>
                                {m.toUpperCase()}
                            </option>
                        ))}
                    </select>
                </div>

                <Table
                    head={
                        <>
                            <th>Jenis</th>
                            <th>Modul</th>
                            <th>Keutamaan</th>
                            <th>Saluran</th>
                            <th>Templat</th>
                            <th>Status</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {isLoading ? (
                        <tr>
                            <td colSpan={7} className="py-6 text-center text-txt-black-500">
                                Memuatkan…
                            </td>
                        </tr>
                    ) : (
                        rows.map((j) => (
                            <tr key={j.id}>
                                <td>
                                    <div className="font-medium">{j.nama}</div>
                                    <div className="text-body-xs text-txt-black-500">{j.kod}</div>
                                </td>
                                <td className="uppercase">{j.modul}</td>
                                <td>
                                    <Badge variant={KEUTAMAAN_BADGE[j.keutamaan]}>{KEUTAMAAN_LABEL[j.keutamaan]}</Badge>
                                    {j.wajib && (
                                        <span className="ms-2">
                                            <Badge variant="neutral" shape="tag">
                                                Wajib
                                            </Badge>
                                        </span>
                                    )}
                                </td>
                                <td className="whitespace-nowrap">{j.saluran_lalai.map((s) => SALURAN_LABEL[s]).join(', ')}</td>
                                <td>v{j.versi_templat ?? '—'}</td>
                                <td>{j.aktif ? <Badge variant="success">Aktif</Badge> : <Badge variant="neutral">Tidak aktif</Badge>}</td>
                                <td className="text-right">
                                    <Link href={`/admin/notifikasi/jenis/${j.id}`} className="text-body-sm font-medium text-primary-600 hover:text-primary-700">
                                        Konfigurasi
                                    </Link>
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </div>
        </PageContainer>
    );
}
