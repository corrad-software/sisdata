'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, tarikh, useCan, useRujukan, type Permohonan } from '@/lib/pjk';
import { Card, Field, inputClass, Kosong, ModalBody, Select, Status } from '../_components/common';

/** SRS-PJK-06: portal and counter applications in one queue → contract draft → same WFE approval. */
export default function PermohonanPage() {
    const can = useCan();
    const router = useRouter();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const [status, setStatus] = useState('');
    const [sumber, setSumber] = useState('');
    const [page, setPage] = useState(1);
    const [sel, setSel] = useState<Permohonan | null>(null);
    const [f, setF] = useState<Record<string, string>>({});
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'permohonan', status, sumber, page], queryFn: () => pjkApi.permohonan({ status, sumber, page }) });

    async function sediakan(hantar: boolean) {
        if (!sel) return;
        try {
            const r = await pjkApi.permohonanKontrak(sel.id, { ...f, hantar });
            toast.success(r.message ?? 'Kontrak disediakan.');
            router.push(`/admin/pjk/kontrak/${r.data.id}`);
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function tolak(p: Permohonan) {
        const catatan = window.prompt('Sebab penolakan');
        if (!catatan) return;
        try {
            await pjkApi.permohonanTolak(p.id, catatan);
            toast.success('Permohonan ditolak.');
            qc.invalidateQueries({ queryKey: ['pjk', 'permohonan'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl">
                    <Field label="Status" htmlFor="pm-status">
                        <Select id="pm-status" value={status} onChange={setStatus} options={ref?.status_permohonan ?? []} empty="Semua" />
                    </Field>
                    <Field label="Sumber" htmlFor="pm-sumber">
                        <Select id="pm-sumber" value={sumber} onChange={setSumber} options={[{ kod: 'portal', nama: 'Portal Hartanah Wakaf' }, { kod: 'kaunter', nama: 'Kaunter' }]} empty="Semua" />
                    </Field>
                </div>
                <p className="text-body-sm text-txt-black-500">{error ? ralat(error) : data ? `${data.meta.total} permohonan` : 'Memuatkan…'}</p>
                <Table head={<><th>Permohonan</th><th className="hidden md:table-cell">Premis</th><th className="hidden sm:table-cell">Pemohon</th><th>Status</th><th /></>}>
                    {isLoading ? (
                        <Kosong colSpan={5}>Memuatkan…</Kosong>
                    ) : (data?.data ?? []).length === 0 ? (
                        <Kosong colSpan={5}>Tiada permohonan.</Kosong>
                    ) : (
                        data?.data.map((p) => (
                            <tr key={p.id}>
                                <td>
                                    {p.no_permohonan}
                                    <p className="text-body-xs text-txt-black-500">
                                        {p.sumber === 'portal' ? `Portal · ${p.rujukan_portal}` : 'Kaunter'} · {tarikh(p.dicipta)}
                                    </p>
                                </td>
                                <td className="hidden md:table-cell">{p.premis ? `${p.premis.kod_premis} · ${p.premis.nama_premis}` : '—'}</td>
                                <td className="hidden sm:table-cell text-body-xs">
                                    {p.pemohon?.nama ?? (p.penyewa_id ? `Penyewa #${p.penyewa_id}` : '—')}
                                    <br />
                                    {p.pemohon?.no_pengenalan ?? ''}
                                </td>
                                <td>
                                    <Status kod={p.status} nama={p.status_nama} />
                                </td>
                                <td className="whitespace-nowrap">
                                    {p.kontrak_id ? (
                                        <Link href={`/admin/pjk/kontrak/${p.kontrak_id}`} className="text-body-xs text-txt-primary hover:underline">
                                            Lihat kontrak
                                        </Link>
                                    ) : (
                                        can('pjk.permohonan.kemas_kini') &&
                                        ['diterima', 'dalam_semakan'].includes(p.status) && (
                                            <div className="flex flex-wrap gap-1">
                                                <Button size="sm" onClick={() => { setSel(p); setF({ kadar: p.kadar_dicadang.nilai ?? '' }); }}>
                                                    Sediakan kontrak
                                                </Button>
                                                <Button size="sm" variant="outline" onClick={() => tolak(p)}>
                                                    Tolak
                                                </Button>
                                            </div>
                                        )
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>

            <Modal open={sel !== null} onClose={() => setSel(null)}>
                <ModalBody
                    title={`Sediakan kontrak — ${sel?.no_permohonan ?? ''}`}
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => sediakan(false)}>
                                Simpan draf
                            </Button>
                            <Button onClick={() => sediakan(true)}>Simpan & hantar kelulusan</Button>
                        </>
                    }
                >
                    <p className="text-body-sm text-txt-black-500">
                        Tempoh dicadang {sel?.tempoh_bulan} bulan mulai {tarikh(sel?.tarikh_mula_dicadang)}. Rekod penyewa diwujudkan daripada maklumat pemohon jika belum wujud.
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Field label="Kadar sebulan (RM)" htmlFor="pk-kadar">
                            <input id="pk-kadar" inputMode="decimal" className={inputClass} value={f.kadar ?? ''} onChange={(e) => setF((p) => ({ ...p, kadar: e.target.value }))} />
                        </Field>
                        <Field label="Deposit (RM)" htmlFor="pk-deposit" help="Lalai: 2 bulan sewa">
                            <input id="pk-deposit" inputMode="decimal" className={inputClass} value={f.deposit ?? ''} onChange={(e) => setF((p) => ({ ...p, deposit: e.target.value }))} />
                        </Field>
                        <Field label="Tarikh mula" htmlFor="pk-mula">
                            <input id="pk-mula" type="date" className={inputClass} value={f.tarikh_mula ?? ''} onChange={(e) => setF((p) => ({ ...p, tarikh_mula: e.target.value }))} />
                        </Field>
                        <Field label="Tarikh tamat" htmlFor="pk-tamat">
                            <input id="pk-tamat" type="date" className={inputClass} value={f.tarikh_tamat ?? ''} onChange={(e) => setF((p) => ({ ...p, tarikh_tamat: e.target.value }))} />
                        </Field>
                    </div>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}
