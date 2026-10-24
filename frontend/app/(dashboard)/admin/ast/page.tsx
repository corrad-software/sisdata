'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { LinkButton } from '@/components/ui/Button';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { astApi, ralat, rm, useCan, useRujukan } from '@/lib/ast';
import { Card, Field, KondisiBadge, Kosong, Select } from './_components/common';

/** MOD-AST overview: portfolio counts, condition split, pending work, asset map (SRS-AST-13). */
export default function RingkasanAset() {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [daerah, setDaerah] = useState('');
    const { data: d, error } = useQuery({ queryKey: ['ast', 'ringkasan', daerah], queryFn: () => astApi.ringkasan({ kod_daerah: daerah }) });

    return (
        <PageContainer>
            <div className="flex flex-wrap items-end justify-between gap-4">
                <Field label="Daerah" htmlFor="r-d" className="w-full sm:w-64">
                    <Select id="r-d" value={daerah} onChange={setDaerah} options={ref?.daerah ?? []} empty="Semua daerah dalam skop" />
                </Field>
                <div className="flex flex-wrap gap-2">
                    {can('ast.aset.cipta') && <LinkButton href="/admin/ast/aset/baru">Daftar aset</LinkButton>}
                    {can('ast.lapangan.lihat') && <LinkButton variant="secondary" href="/admin/ast/lapangan">Aplikasi lapangan</LinkButton>}
                </div>
            </div>
            {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
            {!d ? <Kosong>Memuatkan…</Kosong> : (
                <>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard label="Jumlah aset aktif" value={String(d.jumlah_aset)} />
                        {d.jumlah_nilai !== null && <StatCard label="Jumlah nilai semasa" value={rm(d.jumlah_nilai)} />}
                        <StatCard label="Tanpa pemeriksaan melebihi tempoh" value={String(d.tanpa_pemeriksaan)} trend={d.tanpa_pemeriksaan > 0 ? 'down' : 'neutral'} change={d.tanpa_pemeriksaan > 0 ? 'perlu tindakan' : undefined} />
                        {d.jadual_tertunggak !== null && <StatCard label="Penyelenggaraan tertunggak" value={String(d.jadual_tertunggak)} trend={d.jadual_tertunggak > 0 ? 'down' : 'neutral'} change={d.jadual_tertunggak > 0 ? 'eskalasi' : undefined} />}
                        {d.kerja_terbuka !== null && <StatCard label="Kerja penyelenggaraan terbuka" value={String(d.kerja_terbuka)} />}
                        <StatCard label="Menunggu kelulusan perolehan" value={String(d.menunggu_kelulusan)} />
                    </div>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card title="Mengikut status kondisi">
                            <ul className="space-y-2">
                                {d.kondisi.map((k) => (
                                    <li key={k.kod} className="flex items-center justify-between gap-2">
                                        <Link href={`/admin/ast/aset?status_kondisi=${k.kod}`} className="hover:underline"><KondisiBadge kondisi={k.kod} /></Link>
                                        <span className="text-body-sm font-semibold">{k.bilangan}</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                        <Card title="Mengikut kategori">
                            <ul className="space-y-1">
                                {d.kategori.map((k) => (
                                    <li key={k.kod} className="flex items-center justify-between gap-2 text-body-sm">
                                        <Link href={`/admin/ast/aset?id_kategori=${k.kod}`} className="text-txt-primary hover:underline">{k.label}</Link>
                                        <span className="font-semibold">{k.bilangan}</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </div>
                </>
            )}
            {can('ast.peta.lihat') && (
                <Card title="Peta aset fizikal" actions={<Link href="/admin/gis" className="text-body-sm text-txt-primary hover:underline">Buka Modul GIS</Link>}>
                    <PetaSisdata layers={['gis.lot', 'ast.aset_fizikal']} filters={daerah ? { 'ast.aset_fizikal': { kod_daerah: daerah } } : undefined} height={420} label="Peta aset fizikal" />
                </Card>
            )}
        </PageContainer>
    );
}
