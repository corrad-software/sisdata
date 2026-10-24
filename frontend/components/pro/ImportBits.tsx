'use client';

import Link from 'next/link';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { StatCard } from '@/components/ui/StatCard';
import { EmptyRow } from '@/components/pro/common';
import { STATUS_BARIS_LABEL, STATUS_SESI_LABEL, fmtNilai, fmtNombor, type BarisDitolak, type BarisPratonton, type MedanSasaran, type RingkasanImport } from '@/lib/pro';

export const ENTITI_LABEL: Record<string, string> = { lot_tanah: 'Lot tanah', data_spatial: 'Data spatial (geometri)' };

export function SesiBadge({ status }: { status: string }) {
    return <Badge variant={status === 'dikomit' ? 'success' : status === 'dibatalkan' ? 'neutral' : 'info'}>{STATUS_SESI_LABEL[status] ?? status}</Badge>;
}

export function BarisBadge({ status }: { status: string }) {
    return (
        <Badge variant={status === 'sah' || status === 'dikomit' ? 'success' : status === 'ditolak' ? 'danger' : 'neutral'}>{STATUS_BARIS_LABEL[status] ?? status}</Badge>
    );
}

export function RingkasanStats({ r, committed }: { r: RingkasanImport; committed?: boolean }) {
    return (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <StatCard label="Baris dibaca" value={fmtNombor(r.bil_baris_dibaca, 0)} />
            {committed ? (
                <StatCard label="Berjaya diimport" value={fmtNombor(r.bil_berjaya, 0)} trend="up" change="dikomit" />
            ) : (
                <StatCard label="Baris sah" value={fmtNombor(r.bil_sah, 0)} />
            )}
            <StatCard label="Ditolak" value={fmtNombor(r.bil_ditolak, 0)} trend={r.bil_ditolak > 0 ? 'down' : 'neutral'} change={r.bil_ditolak > 0 ? 'perlu dibetulkan' : undefined} />
            <StatCard label="Diabaikan" value={fmtNombor(r.bil_diabaikan, 0)} />
        </div>
    );
}

export function DitolakTable({ rows }: { rows: BarisDitolak[] }) {
    return (
        <div className="overflow-x-auto">
            <Table
                head={
                    <>
                        <th>Baris</th>
                        <th>Medan</th>
                        <th className="hidden sm:table-cell">Nilai diberi</th>
                        <th>Sebab penolakan</th>
                    </>
                }
            >
                {rows.length === 0 ? (
                    <EmptyRow colSpan={4}>Tiada baris ditolak.</EmptyRow>
                ) : (
                    rows.map((r, i) => (
                        <tr key={`${r.no_baris}-${r.medan}-${i}`}>
                            <td className="whitespace-nowrap">{r.no_baris || '—'}</td>
                            <td>{r.label_medan ?? r.medan ?? '—'}</td>
                            <td className="hidden sm:table-cell break-all max-w-xs">{fmtNilai(r.nilai_diberi)}</td>
                            <td className="text-txt-danger min-w-[12rem]">
                                {r.sebab_penolakan ?? '—'}
                                {r.kod_ralat && <span className="block text-body-xs text-txt-black-500 font-mono">{r.kod_ralat}</span>}
                            </td>
                        </tr>
                    ))
                )}
            </Table>
        </div>
    );
}

/** Preview rows; errors are highlighted per row and per field. */
export function BarisTable({ rows, medan }: { rows: BarisPratonton[]; medan: MedanSasaran[] }) {
    const cols = medan.slice(0, 5);
    return (
        <div className="overflow-x-auto">
            <Table
                head={
                    <>
                        <th>Baris</th>
                        <th>Status</th>
                        {cols.map((m) => (
                            <th key={m.medan} className="hidden md:table-cell">
                                {m.label}
                            </th>
                        ))}
                        <th>Ralat / amaran</th>
                    </>
                }
            >
                {rows.length === 0 ? (
                    <EmptyRow colSpan={cols.length + 3}>Tiada baris.</EmptyRow>
                ) : (
                    rows.map((r) => {
                        const bad = new Set(r.ralat.map((e) => e.medan));
                        return (
                            <tr key={r.no_baris} className={r.status === 'ditolak' ? 'bg-bg-danger-50/60' : undefined}>
                                <td className="whitespace-nowrap">
                                    {r.id_rekod && r.status === 'dikomit' ? (
                                        <Link href={`/admin/pro/lot/${r.id_rekod}`} className="text-txt-primary hover:underline">
                                            {r.no_baris}
                                        </Link>
                                    ) : (
                                        r.no_baris
                                    )}
                                </td>
                                <td>
                                    <BarisBadge status={r.status} />
                                </td>
                                {cols.map((m) => (
                                    <td key={m.medan} className={`hidden md:table-cell break-all max-w-[12rem] ${bad.has(m.medan) ? 'text-txt-danger font-medium' : ''}`}>
                                        {fmtNilai(r.nilai?.[m.medan])}
                                    </td>
                                ))}
                                <td className="min-w-[12rem]">
                                    {r.ralat.length === 0 && r.amaran.length === 0 && <span className="text-txt-black-500">—</span>}
                                    <ul className="space-y-0.5">
                                        {r.ralat.map((e, i) => (
                                            <li key={`r${i}`} className="text-body-xs text-txt-danger">
                                                {e.medan ? <strong>{medan.find((m) => m.medan === e.medan)?.label ?? e.medan}: </strong> : null}
                                                {e.sebab}
                                            </li>
                                        ))}
                                        {r.amaran.map((a, i) => (
                                            <li key={`a${i}`} className="text-body-xs text-txt-warning">
                                                {a}
                                            </li>
                                        ))}
                                    </ul>
                                </td>
                            </tr>
                        );
                    })
                )}
            </Table>
        </div>
    );
}

export function StatusFilter({ value, onChange, committed }: { value: string; onChange: (v: string) => void; committed?: boolean }) {
    const opts = committed ? ['', 'dikomit', 'ditolak', 'diabaikan'] : ['', 'sah', 'ditolak', 'diabaikan'];
    return (
        <div className="flex flex-wrap gap-2" role="group" aria-label="Tapis status baris">
            {opts.map((o) => (
                <button
                    key={o || 'semua'}
                    type="button"
                    aria-pressed={value === o}
                    onClick={() => onChange(o)}
                    className={`rounded-full border px-3 py-1 text-body-xs font-medium ${
                        value === o ? 'border-primary-600 bg-primary-600 text-white' : 'border-otl-gray-300 bg-bg-white text-txt-black-700 hover:bg-bg-washed'
                    }`}
                >
                    {o ? (STATUS_BARIS_LABEL[o] ?? o) : 'Semua'}
                </button>
            ))}
        </div>
    );
}
