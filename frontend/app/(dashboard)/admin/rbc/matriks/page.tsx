'use client';

import { Fragment, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { AnchorButton } from '@/components/ui/Button';
import { ACTION_LABEL, exportUrl, formatDateTime, rbc } from '@/lib/rbc';
import { Card } from '../_components/ui';

/** SRS-RBC-10: role × module × function × action × scope, exportable to Excel and PDF. */
export default function MatrixPage() {
    const [activeOnly, setActiveOnly] = useState(false);
    const { data, isLoading } = useQuery({ queryKey: ['rbc', 'matrix'], queryFn: rbc.matrix });
    const matrix = data?.data;
    const roles = (matrix?.peranan ?? []).filter((r) => !activeOnly || r.status === 'aktif');

    return (
        <PageContainer>
            <Card
                title="Matriks capaian semasa"
                actions={
                    <>
                        <AnchorButton variant="secondary" href={exportUrl('matriks/eksport', { format: 'xlsx', aktif_sahaja: activeOnly })}>
                            Eksport Excel
                        </AnchorButton>
                        <AnchorButton variant="secondary" href={exportUrl('matriks/eksport', { format: 'pdf', aktif_sahaja: activeOnly })}>
                            Eksport PDF
                        </AnchorButton>
                    </>
                }
            >
                <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-body-sm text-txt-black-500">
                    <span>Dijana daripada konfigurasi langsung: {formatDateTime(matrix?.dijana_pada)}</span>
                    <label className="flex items-center gap-2">
                        <input type="checkbox" checked={activeOnly} onChange={(e) => setActiveOnly(e.target.checked)} className="rounded border-otl-gray-300 text-primary-600" />
                        Peranan aktif sahaja
                    </label>
                </div>

                {isLoading || !matrix ? (
                    <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
                ) : (
                    <div className="relative overflow-x-auto max-h-[70vh] border border-otl-gray-200 rounded-lg">
                        <table className="min-w-full text-body-xs">
                            <thead className="sticky top-0 bg-bg-white z-10">
                                <tr>
                                    <th scope="col" className="px-3 py-2 text-start font-medium text-txt-black-700 sticky left-0 bg-bg-white">Fungsi / tindakan</th>
                                    {roles.map((r) => (
                                        <th key={r.kod} scope="col" className="px-2 py-2 text-center font-medium text-txt-black-700 min-w-[6rem]">
                                            {r.nama}
                                            {r.status !== 'aktif' && <div className="font-normal text-txt-black-500">(tidak aktif)</div>}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="border-t border-otl-divider bg-bg-washed">
                                    <th scope="row" className="px-3 py-2 text-start font-medium sticky left-0 bg-bg-washed">Skop</th>
                                    {roles.map((r) => (
                                        <td key={r.kod} className="px-2 py-2 text-center text-txt-black-700">
                                            {Object.keys(r.skop).length === 0 ? 'Tiada had' : Object.entries(r.skop).map(([t, v]) => `${t}: ${v.join(', ')}`).join('; ')}
                                        </td>
                                    ))}
                                </tr>
                                {matrix.modul.map((m) => (
                                    <Fragment key={m.modul}>
                                        <tr className="border-t border-otl-gray-200 bg-bg-primary-50">
                                            <th scope="rowgroup" colSpan={roles.length + 1} className="px-3 py-2 text-start font-semibold text-txt-primary">
                                                {m.label}
                                            </th>
                                        </tr>
                                        {m.fungsi.flatMap((f) =>
                                            Object.entries(f.kebenaran).map(([tindakan, p]) => (
                                                <tr key={p.kunci} className="border-t border-otl-divider">
                                                    <th scope="row" className="px-3 py-1.5 text-start font-normal text-txt-black-900 sticky left-0 bg-bg-white" title={p.kunci}>
                                                        {f.fungsi.replaceAll('_', ' ')} · {ACTION_LABEL[tindakan] ?? tindakan}
                                                    </th>
                                                    {roles.map((r) => {
                                                        const on = matrix.pemberian[r.kod]?.includes(p.kunci);
                                                        return (
                                                            <td key={r.kod} className={`px-2 py-1.5 text-center ${on ? 'text-txt-success font-semibold' : 'text-txt-black-300'}`}>
                                                                {on ? '✓' : '·'}
                                                                <span className="sr-only">{on ? 'dibenarkan' : 'tidak dibenarkan'}</span>
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            )),
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </PageContainer>
    );
}
