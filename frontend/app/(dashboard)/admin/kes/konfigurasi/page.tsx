'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { kesApi, tarikh, useCan } from '@/lib/kes';
import { BorangModal, type Medan } from '../_components/BorangModal';
import { Card, RalatAkses } from '../_components/common';

type Borang = { title: string; jenis: 'jenis' | 'status' | 'panel'; id?: number; fields: Medan[] } | null;

const YA_TIDAK = [
    { kod: '1', nama: 'Ya' },
    { kod: '0', nama: 'Tidak' },
];

/** SRS-KES-01/07: case types and status stages without code changes; panel law-firm registry. */
export default function KonfigurasiPage() {
    const qc = useQueryClient();
    const can = useCan();
    const ubah = can('kes.konfigurasi.kemas_kini');
    const { data, error } = useQuery({ queryKey: ['kes', 'konfigurasi'], queryFn: () => kesApi.konfigurasi().then((r) => r.data) });
    const [borang, setBorang] = useState<Borang>(null);

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card
                    title="Jenis kes"
                    actions={
                        ubah && (
                            <Button size="sm" onClick={() => setBorang({ title: 'Jenis kes baharu', jenis: 'jenis', fields: [{ name: 'kod', label: 'Kod (huruf kecil, _)', required: true }, { name: 'nama', label: 'Nama', required: true }, { name: 'susunan', label: 'Susunan' }] })}>
                                Tambah
                            </Button>
                        )
                    }
                >
                    <Table head={<><th>Kod</th><th>Nama</th><th>Status</th>{ubah && <th className="sr-only">Tindakan</th>}</>}>
                        {(data?.jenis ?? []).map((j) => (
                            <tr key={j.id}>
                                <td className="font-mono text-body-xs">{j.kod}</td>
                                <td>{j.nama}</td>
                                <td>{j.aktif ? <Badge variant="success">Aktif</Badge> : <Badge variant="neutral">Tidak aktif</Badge>}</td>
                                {ubah && (
                                    <td className="text-right">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                setBorang({
                                                    title: `Kemas kini ${j.kod}`,
                                                    jenis: 'jenis',
                                                    id: j.id,
                                                    fields: [
                                                        { name: 'nama', label: 'Nama', required: true, initial: j.nama },
                                                        { name: 'susunan', label: 'Susunan', initial: String(j.susunan) },
                                                        { name: 'aktif', label: 'Aktif', type: 'select', options: YA_TIDAK, initial: j.aktif ? '1' : '0' },
                                                    ],
                                                })
                                            }
                                        >
                                            Ubah
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </Table>
                </Card>
                <Card
                    title="Peringkat status kes"
                    actions={
                        ubah && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    setBorang({
                                        title: 'Peringkat status baharu',
                                        jenis: 'status',
                                        fields: [
                                            { name: 'kod', label: 'Kod', required: true },
                                            { name: 'nama', label: 'Nama', required: true },
                                            { name: 'susunan', label: 'Susunan' },
                                            { name: 'terminal', label: 'Menamatkan kes (status lot dipulihkan)', type: 'select', options: YA_TIDAK, initial: '0' },
                                        ],
                                    })
                                }
                            >
                                Tambah
                            </Button>
                        )
                    }
                >
                    <Table head={<><th>Kod</th><th>Nama</th><th>Jenis</th></>}>
                        {(data?.status ?? []).map((s) => (
                            <tr key={s.id}>
                                <td className="font-mono text-body-xs">{s.kod}</td>
                                <td>{s.nama}</td>
                                <td>{s.terminal ? <Badge variant="success">Penamat</Badge> : <Badge variant="info">Aktif</Badge>}</td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            </div>
            <Card
                title="Panel peguam MAIS"
                actions={
                    ubah && (
                        <Button
                            size="sm"
                            onClick={() =>
                                setBorang({
                                    title: 'Daftar panel peguam',
                                    jenis: 'panel',
                                    fields: [
                                        { name: 'nama_firma', label: 'Nama firma', required: true },
                                        { name: 'no_pendaftaran', label: 'No. pendaftaran' },
                                        { name: 'pegawai_penghubung', label: 'Pegawai penghubung' },
                                        { name: 'no_telefon', label: 'Telefon' },
                                        { name: 'emel', label: 'E-mel' },
                                        { name: 'tarikh_lantikan', label: 'Tarikh lantikan', type: 'date' },
                                        { name: 'tarikh_tamat', label: 'Tarikh tamat', type: 'date' },
                                        { name: 'id_pengguna', label: 'ID akaun pengguna firma (capaian terhad kepada kes ditugaskan)' },
                                    ],
                                })
                            }
                        >
                            Daftar panel
                        </Button>
                    )
                }
            >
                <div className="overflow-x-auto">
                    <Table head={<><th>Firma</th><th className="hidden sm:table-cell">Penghubung</th><th className="hidden md:table-cell">Tempoh lantikan</th><th>Status</th></>}>
                        {(data?.panel ?? []).map((p) => (
                            <tr key={p.id}>
                                <td>
                                    {p.nama_firma}
                                    <p className="text-body-xs text-txt-black-500">{p.no_pendaftaran}</p>
                                    {p.sintetik && <Badge variant="neutral">Sintetik</Badge>}
                                </td>
                                <td className="hidden sm:table-cell text-body-xs">{[p.pegawai_penghubung, p.no_telefon, p.emel].filter(Boolean).join(' · ')}</td>
                                <td className="hidden md:table-cell whitespace-nowrap">
                                    {tarikh(p.tarikh_lantikan)} – {tarikh(p.tarikh_tamat)}
                                </td>
                                <td>
                                    <Badge variant={p.status === 'aktif' ? 'success' : 'neutral'}>{p.status}</Badge>
                                    {p.id_pengguna && <p className="text-body-xs text-txt-black-500">Akaun #{p.id_pengguna}</p>}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </div>
            </Card>
            {borang && (
                <BorangModal
                    open
                    title={borang.title}
                    fields={borang.fields}
                    onClose={() => setBorang(null)}
                    onSubmit={async (v) => {
                        const body: Record<string, unknown> = { ...v };
                        for (const k of ['aktif', 'terminal']) if (k in body) body[k] = body[k] === '1';
                        const r = await kesApi.simpanKonfigurasi(borang.jenis, body, borang.id);
                        toast.success(r.message);
                        qc.invalidateQueries({ queryKey: ['kes'] });
                    }}
                />
            )}
        </PageContainer>
    );
}
