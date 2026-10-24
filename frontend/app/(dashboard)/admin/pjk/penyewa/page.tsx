'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, useCan, useRujukan } from '@/lib/pjk';
import { Card, Field, inputClass, Kosong, ModalBody, Select, Sintetik } from '../_components/common';

/** SRS-PJK-01 tenant register — identity numbers always masked in the list. */
export default function PenyewaPage() {
    const can = useCan();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const [q, setQ] = useState('');
    const [debounced, setDebounced] = useState('');
    const [page, setPage] = useState(1);
    const [open, setOpen] = useState(false);
    const [f, setF] = useState<Record<string, string>>({ jenis_entiti: 'individu' });

    useEffect(() => {
        const t = setTimeout(() => {
            setDebounced(q);
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'penyewa', debounced, page], queryFn: () => pjkApi.penyewa({ q: debounced, page }) });

    async function save() {
        try {
            const r = await pjkApi.penyewaCipta(f);
            toast.success(r.message ?? 'Didaftarkan.');
            setOpen(false);
            setF({ jenis_entiti: 'individu' });
            qc.invalidateQueries({ queryKey: ['pjk', 'penyewa'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setF((p) => ({ ...p, [k]: e.target.value }));

    return (
        <PageContainer>
            <Card>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label htmlFor="py-q" className="sr-only">
                        Cari penyewa
                    </label>
                    <input id="py-q" type="search" className={`${inputClass} sm:flex-1`} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nama, no. kad pengenalan penuh atau no. syarikat" />
                    {can('pjk.penyewa.cipta') && <Button onClick={() => setOpen(true)}>Daftar penyewa</Button>}
                </div>
                <p className="text-body-sm text-txt-black-500">{error ? ralat(error) : data ? `${data.meta.total} penyewa` : 'Memuatkan…'}</p>
                <Table head={<><th>Penyewa</th><th className="hidden sm:table-cell">No. pengenalan / syarikat</th><th className="hidden md:table-cell">Hubungan</th><th>Perjanjian</th></>}>
                    {isLoading ? (
                        <Kosong colSpan={4}>Memuatkan…</Kosong>
                    ) : (data?.data ?? []).length === 0 ? (
                        <Kosong colSpan={4}>Tiada penyewa.</Kosong>
                    ) : (
                        data?.data.map((p) => (
                            <tr key={p.id}>
                                <td className="max-w-[12rem] sm:max-w-xs">
                                    <Link href={`/admin/pjk/penyewa/${p.id}`} className="font-medium text-txt-primary hover:underline break-words">
                                        {p.nama}
                                    </Link>{' '}
                                    <Sintetik show={p.sintetik} />
                                    <p className="text-body-xs text-txt-black-500">{p.jenis_entiti_nama}</p>
                                </td>
                                <td className="hidden sm:table-cell font-mono text-body-xs">{p.no_pengenalan ?? p.no_pendaftaran_syarikat ?? '—'}</td>
                                <td className="hidden md:table-cell text-body-xs">
                                    {p.telefon ?? '—'}
                                    <br />
                                    {p.emel ?? ''}
                                </td>
                                <td>{p.bil_kontrak ?? 0}</td>
                            </tr>
                        ))
                    )}
                </Table>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>

            <Modal open={open} onClose={() => setOpen(false)}>
                <ModalBody
                    title="Daftar penyewa"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setOpen(false)}>
                                Batal
                            </Button>
                            <Button onClick={save}>Simpan</Button>
                        </>
                    }
                >
                    <p className="text-body-xs text-txt-black-500">Maklumat penyewa diklasifikasikan Sulit.</p>
                    <Field label="Jenis entiti" htmlFor="py-jenis">
                        <Select id="py-jenis" value={f.jenis_entiti} onChange={(v) => setF((p) => ({ ...p, jenis_entiti: v }))} options={ref?.jenis_entiti ?? []} />
                    </Field>
                    <Field label="Nama" htmlFor="py-nama">
                        <input id="py-nama" className={inputClass} value={f.nama ?? ''} onChange={set('nama')} />
                    </Field>
                    {f.jenis_entiti === 'individu' ? (
                        <Field label="No. kad pengenalan" htmlFor="py-ic" help="Format 999999-99-9999">
                            <input id="py-ic" className={inputClass} value={f.no_pengenalan ?? ''} onChange={set('no_pengenalan')} autoComplete="off" />
                        </Field>
                    ) : (
                        <Field label="No. pendaftaran syarikat / pertubuhan" htmlFor="py-ssm">
                            <input id="py-ssm" className={inputClass} value={f.no_pendaftaran_syarikat ?? ''} onChange={set('no_pendaftaran_syarikat')} autoComplete="off" />
                        </Field>
                    )}
                    <Field label="Alamat" htmlFor="py-alamat">
                        <textarea id="py-alamat" rows={2} className={inputClass} value={f.alamat ?? ''} onChange={set('alamat')} />
                    </Field>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Field label="Poskod" htmlFor="py-poskod">
                            <input id="py-poskod" className={inputClass} value={f.poskod ?? ''} onChange={set('poskod')} />
                        </Field>
                        <Field label="Telefon" htmlFor="py-tel">
                            <input id="py-tel" className={inputClass} value={f.telefon ?? ''} onChange={set('telefon')} />
                        </Field>
                        <Field label="E-mel" htmlFor="py-emel">
                            <input id="py-emel" type="email" className={inputClass} value={f.emel ?? ''} onChange={set('emel')} />
                        </Field>
                    </div>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}
