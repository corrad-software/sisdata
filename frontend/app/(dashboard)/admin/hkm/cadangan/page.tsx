'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Alert } from '@/components/ui/Alert';
import { Pagination } from '@/components/ui/Pagination';
import { hkmApi, LABEL_MEDAN, ralat, tarikhMasa, useCan, type Cadangan } from '@/lib/hkm';
import { Card, Field, inputClass, Kosong, ModalBody, Select } from '../_components/common';

function SemakModal({ c, mode, onClose }: { c: Cadangan; mode: 'sahkan' | 'tolak'; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [teks, setTeks] = useState('');
    const [hakMilikId, setHakMilikId] = useState('');
    const save = useMutation({
        mutationFn: () => (mode === 'sahkan' ? hkmApi.sahkanCadangan(c.id, { justifikasi: teks, hak_milik_id: hakMilikId || undefined }) : hkmApi.tolakCadangan(c.id, teks)),
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
                title={mode === 'sahkan' ? 'Sahkan cadangan dan hantar untuk kelulusan' : 'Tolak cadangan'}
                footer={
                    <>
                        <Button variant="outline" onClick={onClose}>
                            Batal
                        </Button>
                        <Button variant={mode === 'tolak' ? 'danger' : 'primary'} onClick={() => save.mutate()} disabled={save.isPending || teks.trim().length < 5}>
                            {mode === 'sahkan' ? 'Sahkan' : 'Tolak'}
                        </Button>
                    </>
                }
            >
                {mode === 'sahkan' && <Alert type="info">Pengesahan mewujudkan permohonan perubahan yang perlu melalui kelulusan dua peringkat dengan MFA. Rekod induk tidak berubah sehingga diluluskan.</Alert>}
                {mode === 'sahkan' && !c.hak_milik_id && (
                    <Field label="ID hak milik (tiada padanan automatik)" htmlFor="sm-h">
                        <input id="sm-h" className={inputClass} value={hakMilikId} onChange={(e) => setHakMilikId(e.target.value)} />
                    </Field>
                )}
                <Field label={mode === 'sahkan' ? 'Justifikasi semakan' : 'Sebab penolakan'} htmlFor="sm-t">
                    <textarea id="sm-t" rows={3} className={inputClass} value={teks} onChange={(e) => setTeks(e.target.value)} />
                </Field>
            </ModalBody>
        </Modal>
    );
}

/** SRS-HKM-18: inbox of change proposals from external integrations — drafts awaiting an officer. */
export default function CadanganPage() {
    const can = useCan();
    const [status, setStatus] = useState('draf');
    const [page, setPage] = useState(1);
    const [semak, setSemak] = useState<{ c: Cadangan; mode: 'sahkan' | 'tolak' } | null>(null);
    const p = { status, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['hkm', 'cadangan', p], queryFn: () => hkmApi.cadangan(p) });

    return (
        <PageContainer>
            <Card title="Peti masuk cadangan perubahan">
                <div className="w-full sm:w-64">
                    <Field label="Status" htmlFor="cd-s">
                        <Select
                            id="cd-s"
                            value={status}
                            onChange={(v) => {
                                setStatus(v);
                                setPage(1);
                            }}
                            options={[{ kod: 'draf', nama: 'Draf — menunggu pengesahan' }, { kod: 'disahkan', nama: 'Disahkan' }, { kod: 'ditolak', nama: 'Ditolak' }, { kod: 'semua', nama: 'Semua' }]}
                        />
                    </Field>
                </div>
                {error && <Alert type="error">{ralat(error)}</Alert>}
                {isLoading ? (
                    <Kosong>Memuatkan…</Kosong>
                ) : (data?.data ?? []).length === 0 ? (
                    <Kosong>Tiada cadangan.</Kosong>
                ) : (
                    <ul className="divide-y divide-otl-divider">
                        {data?.data.map((c) => (
                            <li key={c.id} className="py-4 space-y-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-medium text-body-sm">
                                            {c.jenis_perubahan_nama} ·{' '}
                                            {c.hak_milik_id ? (
                                                <Link className="text-txt-primary hover:underline" href={`/admin/hkm/hak-milik/${c.hak_milik_id}`}>
                                                    {c.no_hak_milik}
                                                </Link>
                                            ) : (
                                                <span>
                                                    {c.no_hak_milik} <Badge variant="warning">Tiada padanan</Badge>
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-body-xs text-txt-black-500">
                                            {c.sumber_nama} · {c.rujukan_luaran} · diterima {tarikhMasa(c.diterima)}
                                        </p>
                                    </div>
                                    <Badge variant={c.status === 'draf' ? 'warning' : c.status === 'disahkan' ? 'success' : 'neutral'}>{c.status_nama}</Badge>
                                </div>
                                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-body-xs">
                                    {Object.entries(c.data_cadangan).map(([k, v]) => (
                                        <div key={k} className="flex gap-2 min-w-0">
                                            <dt className="text-txt-black-500">{LABEL_MEDAN[k] ?? k}:</dt>
                                            <dd className="break-words">{String(v)}</dd>
                                        </div>
                                    ))}
                                </dl>
                                {c.keterangan && <p className="text-body-xs text-txt-black-700">{c.keterangan}</p>}
                                {c.catatan_semakan && (
                                    <p className="text-body-xs text-txt-black-500">
                                        Semakan: {c.catatan_semakan} ({c.penyemak})
                                    </p>
                                )}
                                {c.id_perubahan && (
                                    <Link href={`/admin/hkm/perubahan/${c.id_perubahan}`} className="text-body-xs text-txt-primary hover:underline">
                                        Lihat permohonan perubahan
                                    </Link>
                                )}
                                {c.status === 'draf' && can('hkm.cadangan.kemas_kini') && (
                                    <div className="flex flex-wrap gap-2">
                                        <Button size="sm" onClick={() => setSemak({ c, mode: 'sahkan' })}>
                                            Sahkan
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => setSemak({ c, mode: 'tolak' })}>
                                            Tolak
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
            {semak && <SemakModal c={semak.c} mode={semak.mode} onClose={() => setSemak(null)} />}
        </PageContainer>
    );
}
