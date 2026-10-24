import Link from 'next/link';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { ACTION_LABELS, CHANNEL_LABELS, STATUS_LABELS, label, type AuditFieldChange, type AuditRecord } from '@/lib/aud';

export function StatusBadge({ status }: { status: string }) {
    const variant = status === 'berjaya' ? 'success' : status === 'gagal' || status === 'ditolak' ? 'danger' : 'neutral';
    return <Badge variant={variant}>{label(STATUS_LABELS, status)}</Badge>;
}

export function ClassificationBadge({ value }: { value: string | null }) {
    if (!value) return null;
    const variant = value === 'rahsia' ? 'danger' : value === 'sulit' ? 'warning' : 'neutral';
    return (
        <Badge variant={variant} shape="tag">
            {value.charAt(0).toUpperCase() + value.slice(1)}
        </Badge>
    );
}

export function FieldChanges({ changes }: { changes: AuditFieldChange[] }) {
    if (changes.length === 0) {
        return <p className="text-body-sm text-txt-black-500">Tiada perubahan medan.</p>;
    }

    return (
        <div className="overflow-x-auto rounded-md border border-otl-gray-200">
            <table className="min-w-full text-left text-body-xs">
                <thead className="bg-bg-washed">
                    <tr className="[&>th]:px-3 [&>th]:py-2 [&>th]:font-semibold [&>th]:text-txt-black-700">
                        <th>Medan</th>
                        <th>Nilai asal</th>
                        <th>Nilai baharu</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-otl-gray-200 [&>tr>td]:px-3 [&>tr>td]:py-2 [&>tr>td]:align-top">
                    {changes.map((c) => (
                        <tr key={c.nama_medan}>
                            <td className="font-mono whitespace-nowrap">
                                {c.nama_medan}
                                {c.sensitif_ditapis && (
                                    <span className="ms-2">
                                        <Badge variant="warning" shape="tag">
                                            Sensitif
                                        </Badge>
                                    </span>
                                )}
                            </td>
                            <td className="break-all text-txt-black-500">{c.nilai_asal ?? '—'}</td>
                            <td className="break-all">{c.nilai_baharu ?? '—'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function RecordDetail({ record }: { record: AuditRecord }) {
    const rows: Array<[string, ReactNode]> = [
        ['No. urutan', record.no_urutan],
        ['Tarikh & masa (MYT)', record.tarikh_masa_tempatan],
        ['Tarikh & masa (UTC)', record.tarikh_masa_utc],
        ['Pengguna', record.pengecam_pengguna ? `${record.pengecam_pengguna} (ID ${record.id_pengguna ?? '—'})` : 'Sistem'],
        ['Peranan semasa', record.peranan_semasa.join(', ') || '—'],
        ['Modul', record.modul.toUpperCase()],
        [
            'Entiti / ID rekod',
            record.nama_entiti ? (
                <Link
                    className="text-primary-600 hover:underline"
                    href={`/admin/aud/kronologi?nama_entiti=${encodeURIComponent(record.nama_entiti)}&id_rekod=${encodeURIComponent(record.id_rekod ?? '')}`}
                >
                    {record.nama_entiti} #{record.id_rekod ?? '—'}
                </Link>
            ) : (
                '—'
            ),
        ],
        ['Tindakan', label(ACTION_LABELS, record.jenis_tindakan)],
        ['Status', <StatusBadge key="s" status={record.status_transaksi} />],
        ['Sebab perubahan', record.sebab_perubahan ?? '—'],
        ['Alamat IP', record.alamat_ip ?? '—'],
        ['Lokasi anggaran', record.lokasi_anggaran ?? '—'],
        ['Saluran / peranti', `${label(CHANNEL_LABELS, record.saluran_capaian)} · ${record.jenis_peranti.replace(/_/g, ' ')}`],
        ['ID sesi', record.id_sesi ?? '—'],
        ['ID korelasi', record.id_korelasi ?? '—'],
        ['Klasifikasi', <ClassificationBadge key="k" value={record.klasifikasi} />],
        ['Sumber', record.sumber === 'arkib' ? 'Arkib' : 'Dalam talian'],
        ['Cincangan rekod', <span key="h" className="font-mono break-all">{record.cincangan_rekod}</span>],
        ['Cincangan sebelum', <span key="p" className="font-mono break-all">{record.cincangan_sebelum}</span>],
    ];

    return (
        <div className="space-y-4">
            <dl className="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-2 text-body-sm">
                {rows.map(([k, v]) => (
                    <div key={k} className="contents">
                        <dt className="text-txt-black-500">{k}</dt>
                        <dd className="sm:col-span-2 text-txt-black-900 break-words">{v}</dd>
                    </div>
                ))}
            </dl>

            <div>
                <h3 className="text-body-sm font-semibold text-txt-black-900 mb-2">Perubahan medan</h3>
                <FieldChanges changes={record.perubahan} />
            </div>

            {record.meta && Object.keys(record.meta).length > 0 && (
                <div>
                    <h3 className="text-body-sm font-semibold text-txt-black-900 mb-2">Maklumat tambahan</h3>
                    <pre className="text-body-xs bg-bg-washed rounded-md p-3 overflow-x-auto">{JSON.stringify(record.meta, null, 2)}</pre>
                </div>
            )}
        </div>
    );
}
