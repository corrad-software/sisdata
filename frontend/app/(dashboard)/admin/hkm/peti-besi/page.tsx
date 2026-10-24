'use client';

import { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { hkmApi, muatTurun, ralat, tarikh, useCan, useRujukan, type DaftarPetiBesi } from '@/lib/hkm';
import { Card, Field, inputClass, Kosong, ModalBody, Select, StatusDokumen } from '../_components/common';

/** SRS-HKM-06: issue an original document out of the vault. */
function KeluarModal({ entry, onClose }: { entry: DaftarPetiBesi; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [v, setV] = useState<Record<string, string>>({ status_semasa_dikeluarkan: 'dikeluarkan' });
    const save = useMutation({
        mutationFn: () => hkmApi.keluar(entry.id, v),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['hkm'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const set = (k: string) => (x: string) => setV((p) => ({ ...p, [k]: x }));

    return (
        <Modal open onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <ModalBody
                    title={`Keluarkan dokumen asal — ${entry.no_hak_milik ?? ''}`}
                    footer={
                        <>
                            <Button variant="outline" onClick={onClose}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={save.isPending}>
                                Rekod pengeluaran
                            </Button>
                        </>
                    }
                >
                    <p className="text-body-sm text-txt-black-500">
                        {entry.kod_peti_besi} · Rak {entry.lokasi_rak ?? '—'} · Fail {entry.no_fail ?? '—'}
                    </p>
                    <Field label="Nama penerima" htmlFor="k-nama">
                        <input id="k-nama" className={inputClass} value={v.penerima_nama ?? ''} onChange={(e) => set('penerima_nama')(e.target.value)} required />
                    </Field>
                    <Field label="Jawatan penerima" htmlFor="k-jaw">
                        <input id="k-jaw" className={inputClass} value={v.penerima_jawatan ?? ''} onChange={(e) => set('penerima_jawatan')(e.target.value)} />
                    </Field>
                    <Field label="Tujuan pengeluaran" htmlFor="k-tuj">
                        <textarea id="k-tuj" rows={2} className={inputClass} value={v.tujuan_pengeluaran ?? ''} onChange={(e) => set('tujuan_pengeluaran')(e.target.value)} required />
                    </Field>
                    <Field label="Tarikh dijangka dipulangkan" htmlFor="k-jp">
                        <input id="k-jp" type="date" className={inputClass} value={v.tarikh_jangka_pulang ?? ''} onChange={(e) => set('tarikh_jangka_pulang')(e.target.value)} required />
                    </Field>
                    <Field label="Status semasa dikeluarkan" htmlFor="k-st">
                        <Select
                            id="k-st"
                            value={v.status_semasa_dikeluarkan}
                            onChange={set('status_semasa_dikeluarkan')}
                            options={[
                                { kod: 'dikeluarkan', nama: 'Dikeluarkan' },
                                { kod: 'dalam_proses_pt', nama: 'Dalam proses di Pejabat Tanah' },
                                { kod: 'dipegang_pihak_ketiga', nama: 'Dipegang pihak ketiga' },
                            ]}
                        />
                    </Field>
                </ModalBody>
            </form>
        </Modal>
    );
}

function PetiBesiModal({ onClose }: { onClose: () => void }) {
    const queryClient = useQueryClient();
    const [v, setV] = useState<Record<string, string>>({});
    const save = useMutation({
        mutationFn: () => hkmApi.daftarPetiBesiBaharu({ ...v, kapasiti: v.kapasiti ? Number(v.kapasiti) : undefined }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['hkm'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const fields: Array<[string, string, boolean]> = [
        ['kod_peti_besi', 'Kod peti besi (cth. PB-02)', true],
        ['nama', 'Nama', true],
        ['lokasi_bangunan', 'Bangunan', true],
        ['tingkat', 'Tingkat', false],
        ['bilik', 'Bilik', false],
        ['kapasiti', 'Kapasiti (bilangan dokumen)', false],
    ];
    return (
        <Modal open onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <ModalBody
                    title="Daftar peti besi"
                    footer={
                        <>
                            <Button variant="outline" onClick={onClose}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={save.isPending}>
                                Simpan
                            </Button>
                        </>
                    }
                >
                    {fields.map(([k, label, req]) => (
                        <Field key={k} label={label} htmlFor={`pb-${k}`}>
                            <input id={`pb-${k}`} type={k === 'kapasiti' ? 'number' : 'text'} className={inputClass} value={v[k] ?? ''} onChange={(e) => setV((p) => ({ ...p, [k]: e.target.value }))} required={req} />
                        </Field>
                    ))}
                </ModalBody>
            </form>
        </Modal>
    );
}

function StatusEntri({ entry }: { entry: DaftarPetiBesi }) {
    const { data: ref } = useRujukan();
    const queryClient = useQueryClient();
    const save = useMutation({
        mutationFn: (status: string) => hkmApi.kemasKiniEntri(entry.id, { status_dokumen_asal: status }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['hkm'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <>
            <label htmlFor={`se-${entry.id}`} className="sr-only">
                Tukar status dokumen asal
            </label>
            <select id={`se-${entry.id}`} className={`${inputClass} w-auto`} value="" onChange={(e) => e.target.value && save.mutate(e.target.value)}>
                <option value="">Tukar status…</option>
                {(ref?.status_dokumen ?? [])
                    .filter((s) => s.kod !== 'dikeluarkan' && s.kod !== entry.status_dokumen_asal && !(entry.pengeluaran_terbuka && s.kod === 'dalam_simpanan'))
                    .map((s) => (
                        <option key={s.kod} value={s.kod}>
                            {s.nama}
                        </option>
                    ))}
            </select>
        </>
    );
}

/** SRS-HKM-03/04/05: vaults, vault register (location + original status), printable inventory. */
function DaftarPetiBesiView() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({ q: params.get('q') ?? '' });
    const [q, setQ] = useState(params.get('q') ?? '');
    const [page, setPage] = useState(1);
    const [keluar, setKeluar] = useState<DaftarPetiBesi | null>(null);
    const [baharu, setBaharu] = useState(false);
    const [printing, setPrinting] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => {
            setF((p) => ({ ...p, q }));
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const { data: vaults } = useQuery({ queryKey: ['hkm', 'peti-besi'], queryFn: hkmApi.petiBesi });
    const p = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['hkm', 'daftar', p], queryFn: () => hkmApi.entri(p) });

    async function cetak(format: 'pdf' | 'xlsx') {
        setPrinting(true);
        try {
            await muatTurun('/hkm/eksport/inventori-peti-besi', { ...f, format }, `inventori-peti-besi.${format}`);
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setPrinting(false);
        }
    }

    return (
        <PageContainer>
            <Card title="Peti besi" actions={can('hkm.peti_besi.cipta') && <Button size="sm" onClick={() => setBaharu(true)}>Daftar peti besi</Button>}>
                {(vaults ?? []).length === 0 ? (
                    <Kosong>Tiada peti besi didaftarkan.</Kosong>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        {(vaults ?? []).map((v) => (
                            <button
                                type="button"
                                key={v.id}
                                onClick={() => setF((x) => ({ ...x, peti_besi_id: x.peti_besi_id === String(v.id) ? '' : String(v.id) }))}
                                className={`text-left rounded-md border p-3 ${f.peti_besi_id === String(v.id) ? 'border-primary-600 bg-bg-primary-50' : 'border-otl-gray-200 hover:border-otl-gray-300'}`}
                                aria-pressed={f.peti_besi_id === String(v.id)}
                            >
                                <p className="font-semibold text-body-sm">
                                    {v.kod_peti_besi} — {v.nama}
                                </p>
                                <p className="text-body-xs text-txt-black-500">{v.lokasi}</p>
                                <p className="text-body-xs text-txt-black-500">
                                    {v.bilangan_dokumen ?? 0} dokumen{v.kapasiti ? ` / kapasiti ${v.kapasiti}` : ''} · Penjaga: {v.penjaga ?? '—'}
                                </p>
                            </button>
                        ))}
                    </div>
                )}
            </Card>

            <Card
                title="Daftar peti besi"
                actions={
                    can('hkm.peti_besi.eksport') && (
                        <>
                            <Button size="sm" variant="outline" disabled={printing} onClick={() => cetak('pdf')}>
                                Cetak inventori (PDF)
                            </Button>
                            <Button size="sm" variant="outline" disabled={printing} onClick={() => cetak('xlsx')}>
                                Excel
                            </Button>
                        </>
                    )
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Cari no. hak milik / fail / rak" htmlFor="d-q">
                        <input id="d-q" type="search" className={inputClass} value={q} onChange={(e) => setQ(e.target.value)} />
                    </Field>
                    <Field label="Status dokumen asal" htmlFor="d-st">
                        <Select id="d-st" value={f.status ?? ''} onChange={(x) => setF((p) => ({ ...p, status: x }))} options={ref?.status_dokumen ?? []} empty="Semua" />
                    </Field>
                    <Field label="Peti besi" htmlFor="d-pb">
                        <Select
                            id="d-pb"
                            value={f.peti_besi_id ?? ''}
                            onChange={(x) => setF((p) => ({ ...p, peti_besi_id: x }))}
                            options={(vaults ?? []).map((v) => ({ kod: String(v.id), nama: v.kod_peti_besi }))}
                            empty="Semua"
                        />
                    </Field>
                </div>

                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} dokumen asal` : 'Memuatkan…'}
                </p>

                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Hak milik</th>
                                <th className="hidden sm:table-cell">Lokasi</th>
                                <th>Status</th>
                                <th className="hidden md:table-cell">Masuk simpanan</th>
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
                                    Tiada dokumen asal.
                                </td>
                            </tr>
                        ) : (
                            data?.data.map((d) => (
                                <tr key={d.id}>
                                    <td>
                                        <Link className="font-medium text-txt-primary hover:underline" href={`/admin/hkm/hak-milik/${d.hak_milik_id}?tab=dokumen`}>
                                            {d.no_hak_milik}
                                        </Link>
                                        <p className="text-body-xs text-txt-black-500">{d.jenis_dokumen_asal_nama}</p>
                                        <p className="sm:hidden text-body-xs text-txt-black-500">
                                            {d.kod_peti_besi} · {d.lokasi_rak ?? '—'} · {d.no_fail ?? '—'}
                                        </p>
                                    </td>
                                    <td className="hidden sm:table-cell">
                                        {d.kod_peti_besi}
                                        <p className="text-body-xs text-txt-black-500">
                                            Rak {d.lokasi_rak ?? '—'} · Fail {d.no_fail ?? '—'}
                                            {d.no_kotak_pengikat ? ` · Kotak ${d.no_kotak_pengikat}` : ''}
                                        </p>
                                    </td>
                                    <td>
                                        <StatusDokumen kod={d.status_dokumen_asal} nama={d.status_dokumen_asal_nama} />
                                        {d.pengeluaran_terbuka && (
                                            <p className={`mt-1 text-body-xs ${d.pengeluaran_terbuka.lewat ? 'text-txt-danger' : 'text-txt-black-500'}`}>
                                                {d.pengeluaran_terbuka.penerima_nama} · pulang {tarikh(d.pengeluaran_terbuka.tarikh_jangka_pulang)}
                                                {d.pengeluaran_terbuka.lewat ? ` (lewat ${d.pengeluaran_terbuka.hari_lewat} hari)` : ''}
                                            </p>
                                        )}
                                    </td>
                                    <td className="hidden md:table-cell">{tarikh(d.tarikh_masuk_simpanan)}</td>
                                    <td className="text-right space-y-1">
                                        {can('hkm.pengeluaran.cipta') && d.dalam_simpanan && (
                                            <Button size="sm" onClick={() => setKeluar(d)}>
                                                Keluarkan
                                            </Button>
                                        )}
                                        {can('hkm.peti_besi.kemas_kini') && <StatusEntri entry={d} />}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>

            {keluar && <KeluarModal entry={keluar} onClose={() => setKeluar(null)} />}
            {baharu && <PetiBesiModal onClose={() => setBaharu(false)} />}
        </PageContainer>
    );
}

export default function PetiBesiPage() {
    return (
        <Suspense>
            <DaftarPetiBesiView />
        </Suspense>
    );
}
