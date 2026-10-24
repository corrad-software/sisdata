'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { hkmApi, muatTurun, ralat, tarikh, tarikhMasa, useCan, useRujukan, type Pengeluaran } from '@/lib/hkm';
import { Card, Field, inputClass, ModalBody, Select } from '../../_components/common';

function PulangModal({ issue, onClose }: { issue: Pengeluaran; onClose: () => void }) {
    const { data: ref } = useRujukan();
    const queryClient = useQueryClient();
    const [keadaan, setKeadaan] = useState('baik');
    const [catatan, setCatatan] = useState('');
    const save = useMutation({
        mutationFn: () => hkmApi.pulang(issue.id, { keadaan_dokumen_pulang: keadaan, catatan_pulang: catatan || undefined }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['hkm'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <Modal open onClose={onClose}>
            <ModalBody
                title={`Terima pemulangan — ${issue.no_hak_milik ?? ''}`}
                footer={
                    <>
                        <Button variant="outline" onClick={onClose}>
                            Batal
                        </Button>
                        <Button onClick={() => save.mutate()} disabled={save.isPending}>
                            Rekod pemulangan
                        </Button>
                    </>
                }
            >
                <p className="text-body-sm text-txt-black-500">
                    Dikeluarkan kepada {issue.penerima_nama} pada {tarikhMasa(issue.tarikh_pengeluaran)} · dijangka pulang {tarikh(issue.tarikh_jangka_pulang)}
                </p>
                <Field label="Keadaan dokumen semasa dipulangkan" htmlFor="pl-k">
                    <Select id="pl-k" value={keadaan} onChange={setKeadaan} options={ref?.keadaan_pulang ?? []} />
                </Field>
                <Field label="Catatan" htmlFor="pl-c">
                    <textarea id="pl-c" rows={2} className={inputClass} value={catatan} onChange={(e) => setCatatan(e.target.value)} />
                </Field>
            </ModalBody>
        </Modal>
    );
}

/** SRS-HKM-06/07: issue/return history and the overdue action list. */
function PengeluaranView() {
    const params = useSearchParams();
    const can = useCan();
    const [lewat, setLewat] = useState(params.get('lewat') === '1');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [pulang, setPulang] = useState<Pengeluaran | null>(null);
    const p = { lewat: lewat ? 1 : '', status, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['hkm', 'pengeluaran', p], queryFn: () => hkmApi.pengeluaran(p) });

    return (
        <PageContainer>
            <Card
                title={lewat ? 'Dokumen asal melepasi tarikh jangka pulang' : 'Sejarah pengeluaran dan pemulangan'}
                actions={
                    <>
                        <Button size="sm" variant={lewat ? 'primary' : 'outline'} onClick={() => setLewat((x) => !x)} aria-pressed={lewat}>
                            Senarai tindakan (lewat)
                        </Button>
                        {can('hkm.peti_besi.lihat', 'hkm.pemantauan.lihat') && (
                            <Button size="sm" variant="outline" onClick={() => muatTurun('/hkm/pengeluaran', { lewat: lewat ? 1 : '', status, format: 'xlsx' }, 'pengeluaran.xlsx').catch((e) => toast.error(ralat(e)))}>
                                Eksport Excel
                            </Button>
                        )}
                    </>
                }
            >
                {!lewat && (
                    <div className="max-w-xs">
                        <Field label="Status" htmlFor="pg-st">
                            <Select
                                id="pg-st"
                                value={status}
                                onChange={(x) => {
                                    setStatus(x);
                                    setPage(1);
                                }}
                                options={[
                                    { kod: 'dikeluarkan', nama: 'Belum dipulangkan' },
                                    { kod: 'dipulangkan', nama: 'Telah dipulangkan' },
                                ]}
                                empty="Semua"
                            />
                        </Field>
                    </div>
                )}
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} rekod` : 'Memuatkan…'}
                </p>
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Dokumen</th>
                                <th className="hidden sm:table-cell">Penerima / tujuan</th>
                                <th>Jangka pulang</th>
                                <th className="hidden md:table-cell">Pemulangan</th>
                                <th />
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={5} className="py-6 text-center text-txt-black-500">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : (data?.data ?? []).length === 0 ? (
                            <tr>
                                <td colSpan={5} className="py-6 text-center text-txt-black-500">
                                    {lewat ? 'Tiada dokumen lewat dipulangkan.' : 'Tiada rekod.'}
                                </td>
                            </tr>
                        ) : (
                            data?.data.map((e) => (
                                <tr key={e.id}>
                                    <td>
                                        <Link href={`/admin/hkm/hak-milik/${e.hak_milik_id}?tab=dokumen`} className="font-medium text-txt-primary hover:underline">
                                            {e.no_hak_milik}
                                        </Link>
                                        <p className="text-body-xs text-txt-black-500">
                                            {e.kod_peti_besi} · Fail {e.no_fail ?? '—'}
                                        </p>
                                        <p className="sm:hidden text-body-xs text-txt-black-500">{e.penerima_nama}</p>
                                    </td>
                                    <td className="hidden sm:table-cell max-w-xs">
                                        {e.penerima_nama}
                                        {e.penerima_jawatan && <span className="text-txt-black-500"> ({e.penerima_jawatan})</span>}
                                        <p className="text-body-xs text-txt-black-500 break-words">{e.tujuan_pengeluaran}</p>
                                        <p className="text-body-xs text-txt-black-500">
                                            Dikeluarkan {tarikhMasa(e.tarikh_pengeluaran)} oleh {e.pegawai_keluar ?? '—'}
                                        </p>
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {tarikh(e.tarikh_jangka_pulang)}
                                        {e.lewat && (
                                            <div>
                                                <Badge variant="danger">Lewat {e.hari_lewat} hari</Badge>
                                            </div>
                                        )}
                                    </td>
                                    <td className="hidden md:table-cell">
                                        {e.tarikh_pulang_sebenar ? (
                                            <>
                                                {tarikhMasa(e.tarikh_pulang_sebenar)}
                                                <p className="text-body-xs text-txt-black-500">
                                                    {e.keadaan_dokumen_pulang_nama} · {e.pegawai_terima_pulang}
                                                </p>
                                            </>
                                        ) : (
                                            <Badge variant="warning">Belum dipulangkan</Badge>
                                        )}
                                    </td>
                                    <td className="text-right">
                                        {can('hkm.pengeluaran.kemas_kini') && e.status_pengeluaran === 'dikeluarkan' && (
                                            <Button size="sm" onClick={() => setPulang(e)}>
                                                Terima pulang
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
            {pulang && <PulangModal issue={pulang} onClose={() => setPulang(null)} />}
        </PageContainer>
    );
}

export default function PengeluaranPage() {
    return (
        <Suspense>
            <PengeluaranView />
        </Suspense>
    );
}
