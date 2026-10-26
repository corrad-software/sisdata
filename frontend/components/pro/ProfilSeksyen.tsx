'use client';

import Link from 'next/link';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Alert } from '@/components/ui/Alert';
import { Table } from '@/components/ui/Table';
import { Card, InfoList, KlasifikasiBadge, SpatialBadge } from '@/components/pro/common';
import { fmtNilai, fmtTarikh, pautanUi, type Bendera, type ProfilItem, type Seksyen } from '@/lib/pro';

const FLAG_TONE: Record<string, 'danger' | 'warning' | 'info' | 'success' | 'neutral'> = {
    danger: 'danger',
    warning: 'warning',
    info: 'info',
    success: 'success',
};

export function Flags({ flags }: { flags: Bendera[] }) {
    if (!flags.length) return null;
    return (
        <div className="flex flex-wrap gap-2">
            {flags.map((f, i) => (
                <Badge key={`${f.label}-${i}`} variant={FLAG_TONE[f.level] ?? 'neutral'}>
                    {f.label}
                </Badge>
            ))}
        </div>
    );
}

function str(v: unknown): string {
    return v === null || v === undefined ? '' : String(v);
}

function LinkOrText({ href, children }: { href: string | null; children: ReactNode }) {
    return href ? (
        <Link href={href} className="text-txt-primary hover:underline">
            {children}
        </Link>
    ) : (
        <>{children}</>
    );
}

function DokumenItems({ items }: { items: ProfilItem[] }) {
    return (
        <div className="overflow-x-auto">
            <Table
                head={
                    <>
                        <th>Dokumen</th>
                        <th className="hidden sm:table-cell">Kategori</th>
                        <th className="hidden md:table-cell">Versi</th>
                        <th>Klasifikasi</th>
                        <th className="hidden md:table-cell">Tarikh</th>
                    </>
                }
            >
                {items.map((d, i) => (
                    <tr key={str(d.id) || i}>
                        <td className="max-w-[14rem] sm:max-w-md break-words">
                            <LinkOrText href={pautanUi(str(d.link))}>{str(d.label) || '—'}</LinkOrText>
                        </td>
                        <td className="hidden sm:table-cell">{fmtNilai(d.nilai)}</td>
                        <td className="hidden md:table-cell">v{fmtNilai(d.versi)}</td>
                        <td>
                            <KlasifikasiBadge value={str(d.klasifikasi)} />
                        </td>
                        <td className="hidden md:table-cell whitespace-nowrap">{fmtTarikh(str(d.tarikh))}</td>
                    </tr>
                ))}
            </Table>
        </div>
    );
}

function JejakAuditItems({ items }: { items: ProfilItem[] }) {
    return (
        <ol className="space-y-3">
            {items.map((r, i) => {
                const medan = (Array.isArray(r.medan) ? r.medan : []) as Array<{ medan: string; asal: unknown; baharu: unknown }>;
                const gagal = str(r.status) && str(r.status) !== 'berjaya';
                return (
                    <li key={i} className="rounded-md border border-otl-gray-200 p-3 space-y-2">
                        <div className="flex flex-wrap items-center gap-2 text-body-sm">
                            <span className="font-medium text-txt-black-900">{str(r.label)}</span>
                            <Badge variant="neutral" shape="tag">
                                {str(r.entiti) === 'data_spatial' ? 'Geometri' : 'Lot'}
                            </Badge>
                            {gagal && <Badge variant="danger">{str(r.status)}</Badge>}
                            <span className="text-body-xs text-txt-black-500">
                                {fmtTarikh(str(r.tarikh), true)} · {fmtNilai(r.nilai)}
                            </span>
                        </div>
                        {str(r.sebab) && <p className="text-body-sm text-txt-black-700">Sebab: “{str(r.sebab)}”</p>}
                        {medan.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-body-xs">
                                    <thead>
                                        <tr className="text-left text-txt-black-500">
                                            <th className="py-1 pe-3 font-medium">Medan</th>
                                            <th className="py-1 pe-3 font-medium">Asal</th>
                                            <th className="py-1 font-medium">Baharu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {medan.map((m, j) => (
                                            <tr key={j} className="border-t border-otl-gray-200 align-top">
                                                <td className="py-1 pe-3 font-mono">{m.medan}</td>
                                                <td className="py-1 pe-3 text-txt-danger break-all">{fmtNilai(m.asal)}</td>
                                                <td className="py-1 text-txt-success break-all">{fmtNilai(m.baharu)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

function LogAksesItems({ items }: { items: ProfilItem[] }) {
    return (
        <div className="overflow-x-auto">
            <Table
                head={
                    <>
                        <th>Tarikh</th>
                        <th>Capaian</th>
                        <th>Pengguna</th>
                        <th className="hidden sm:table-cell">Saluran</th>
                        <th className="hidden md:table-cell">Alamat IP</th>
                    </>
                }
            >
                {items.map((r, i) => (
                    <tr key={i}>
                        <td className="whitespace-nowrap">{fmtTarikh(str(r.tarikh), true)}</td>
                        <td>{str(r.label)}</td>
                        <td>{fmtNilai(r.nilai)}</td>
                        <td className="hidden sm:table-cell">{fmtNilai(r.saluran)}</td>
                        <td className="hidden md:table-cell font-mono text-body-xs">{fmtNilai(r.alamat_ip)}</td>
                    </tr>
                ))}
            </Table>
        </div>
    );
}

/** Keys that best name a row, in order — sections from other modules don't all send `label`/`nilai`. */
const LABEL_KEYS = ['label', 'nama', 'tajuk', 'no_kontrak', 'no_rujukan', 'no_hak_milik', 'no_aset', 'kod_aset', 'no_kes', 'no_aduan', 'kategori', 'jenis'];
const SKIP_KEYS = new Set(['id', 'link', 'pautan', 'kod', 'tahap', 'klasifikasi', 'lot_id', 'medan']);

function humanise(key: string): string {
    const s = key.replace(/_/g, ' ');
    return s.charAt(0).toUpperCase() + s.slice(1);
}

/** Label + value for a row: explicit `label`/`nilai` when sent, otherwise derived from the row's own fields. */
function deriveRow(it: ProfilItem): { label: string; nilai: unknown } {
    if (str(it.label)) return { label: str(it.label), nilai: it.nilai };
    const labelKey = LABEL_KEYS.find((k) => str(it[k]) !== '');
    const rest = Object.entries(it)
        .filter(([k, v]) => k !== labelKey && !SKIP_KEYS.has(k) && v !== null && v !== undefined && v !== '' && typeof v !== 'object')
        .slice(0, 4)
        .map(([k, v]) => `${humanise(k)}: ${fmtNilai(v)}`);
    return { label: labelKey ? str(it[labelKey]) : '—', nilai: 'nilai' in it ? it.nilai : rest.join(' · ') || null };
}

function GenericItems({ items }: { items: ProfilItem[] }) {
    return (
        <InfoList
            items={items.map((raw, i) => {
                const derived = deriveRow(raw);
                const it: ProfilItem = { ...raw, label: derived.label, nilai: derived.nilai };
                const href = pautanUi(str(it.link ?? it.pautan));
                const kod = str(it.kod);
                const value =
                    kod && it.label && /pengesahan/i.test(str(it.label)) ? (
                        <SpatialBadge status={kod} label={str(it.nilai)} />
                    ) : (
                        <span className="inline-flex flex-wrap items-center gap-2">
                            <LinkOrText href={href}>{fmtNilai(it.nilai)}</LinkOrText>
                            {str(it.tahap) && <Badge variant={it.tahap === 'ralat' ? 'danger' : 'warning'}>{str(it.tahap)}</Badge>}
                        </span>
                    );
                return { key: `${str(it.label)}-${i}`, label: str(it.label) || '—', value };
            })}
        />
    );
}

/** One 360° profile section. 'belum_tersedia' sections render a muted placeholder. */
export function ProfilSeksyen({ seksyen, hideTitle }: { seksyen: Seksyen; hideTitle?: boolean }) {
    if (seksyen.status === 'belum_tersedia') {
        return (
            <section className="rounded-lg border border-dashed border-otl-gray-300 bg-bg-washed p-4 sm:p-6 space-y-1" aria-label={seksyen.label}>
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-heading font-semibold text-body-md text-txt-black-500">{seksyen.label}</h3>
                    {seksyen.modul && (
                        <Badge variant="neutral" shape="tag">
                            {seksyen.modul}
                        </Badge>
                    )}
                </div>
                <p className="text-body-sm text-txt-black-500">{seksyen.mesej ?? 'Belum tersedia — modul berkaitan belum diaktifkan.'}</p>
            </section>
        );
    }

    const link = pautanUi(seksyen.link ?? null);
    const body =
        seksyen.status === 'ralat' ? (
            <Alert type="error">Seksyen ini tidak dapat dimuatkan buat masa ini. Cuba lagi kemudian.</Alert>
        ) : seksyen.items.length === 0 ? (
            <p className="text-body-sm text-txt-black-500">Tiada rekod.</p>
        ) : seksyen.key === 'dokumen' ? (
            <DokumenItems items={seksyen.items} />
        ) : seksyen.key === 'jejak_audit' ? (
            <JejakAuditItems items={seksyen.items} />
        ) : seksyen.key === 'log_akses' ? (
            <LogAksesItems items={seksyen.items} />
        ) : (
            <GenericItems items={seksyen.items} />
        );

    return (
        <Card
            title={hideTitle ? undefined : seksyen.label}
            actions={
                link && !link.includes('/kemas-kini') && !link.startsWith('/admin/pro/lot/') ? (
                    <Link href={link} className="text-body-sm font-medium text-primary-600 hover:underline print:hidden">
                        Lihat semua
                    </Link>
                ) : undefined
            }
        >
            <Flags flags={seksyen.flags} />
            {body}
        </Card>
    );
}
