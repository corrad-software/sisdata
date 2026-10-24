'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { astApi, ralat, rm, tarikh, useCan, type Kontraktor } from '@/lib/ast';
import { Card, Field, inputClass, Kosong, ModalBody } from '../_components/common';

const KOSONG = { nama_syarikat: '', no_pendaftaran: '', no_telefon: '', emel: '', tarikh_tamat_pendaftaran: '', id_pengguna: '', status_aktif: true };

/** Maintenance contractors: company register, linked contractor account (limited access), work and cost totals. */
export default function SenaraiKontraktor() {
    const can = useCan();
    const qc = useQueryClient();
    const [semua, setSemua] = useState(false);
    const { data, error } = useQuery({ queryKey: ['ast', 'kontraktor', semua], queryFn: () => astApi.kontraktor({ semua }) });
    const [edit, setEdit] = useState<{ id?: number; f: typeof KOSONG } | null>(null);
    const simpan = useMutation({
        mutationFn: () => astApi.simpanKontraktor({ ...edit!.f, id_pengguna: edit!.f.id_pengguna ? Number(edit!.f.id_pengguna) : null, tarikh_tamat_pendaftaran: edit!.f.tarikh_tamat_pendaftaran || null } as Partial<Kontraktor>, edit!.id),
        onSuccess: (r) => { toast.success(r.message); setEdit(null); qc.invalidateQueries({ queryKey: ['ast', 'kontraktor'] }); },
        onError: (e) => toast.error(ralat(e)),
    });
    const ubah = (c: Kontraktor) => setEdit({ id: c.id, f: { nama_syarikat: c.nama_syarikat, no_pendaftaran: c.no_pendaftaran ?? '', no_telefon: c.no_telefon ?? '', emel: c.emel ?? '', tarikh_tamat_pendaftaran: c.tarikh_tamat_pendaftaran ?? '', id_pengguna: c.id_pengguna ? String(c.id_pengguna) : '', status_aktif: c.status_aktif } });

    return (
        <PageContainer>
            <Card title={`Kontraktor penyelenggaraan (${data?.length ?? 0})`} actions={<>
                <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={semua} onChange={(e) => setSemua(e.target.checked)} /> Termasuk tidak aktif</label>
                {can('ast.kontraktor.kemas_kini') && <Button size="sm" onClick={() => setEdit({ f: KOSONG })}>Tambah kontraktor</Button>}
            </>}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {data && data.length === 0 ? <Kosong>Tiada kontraktor.</Kosong> : (
                    <Table head={<><th>Syarikat</th><th>No. pendaftaran</th><th>Hubungan</th><th>Pendaftaran tamat</th><th>Kerja (siap)</th><th>Jumlah kos</th><th>Akaun</th><th></th></>}>
                        {(data ?? []).map((c) => (
                            <tr key={c.id}>
                                <td>{c.nama_syarikat} {!c.status_aktif && <Badge>Tidak aktif</Badge>}</td>
                                <td>{c.no_pendaftaran ?? '—'}</td>
                                <td className="text-body-xs">{c.no_telefon ?? ''}{c.emel ? <span className="block">{c.emel}</span> : null}</td>
                                <td className="whitespace-nowrap">{tarikh(c.tarikh_tamat_pendaftaran)}</td>
                                <td>{c.bilangan_kerja ?? 0} ({c.kerja_siap ?? 0})</td>
                                <td className="whitespace-nowrap">{rm(c.jumlah_kos)}</td>
                                <td>{c.id_pengguna ? <Badge variant="info">Dipautkan</Badge> : '—'}</td>
                                <td>{can('ast.kontraktor.kemas_kini') && <Button size="sm" variant="secondary" onClick={() => ubah(c)}>Ubah</Button>}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            <Modal open={!!edit} onClose={() => setEdit(null)}>
                {edit && (
                    <ModalBody title={edit.id ? 'Ubah kontraktor' : 'Kontraktor baharu'} footer={<><Button variant="secondary" onClick={() => setEdit(null)}>Batal</Button><Button disabled={!edit.f.nama_syarikat || simpan.isPending} onClick={() => simpan.mutate()}>Simpan</Button></>}>
                        {([['nama_syarikat', 'Nama syarikat'], ['no_pendaftaran', 'No. pendaftaran (SSM / CIDB)'], ['no_telefon', 'No. telefon'], ['emel', 'E-mel']] as const).map(([k, l]) => (
                            <Field key={k} label={l} htmlFor={`c-${k}`}><input id={`c-${k}`} className={inputClass} value={edit.f[k]} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, [k]: e.target.value } })} /></Field>
                        ))}
                        <Field label="Tarikh tamat pendaftaran" htmlFor="c-t"><input id="c-t" type="date" className={inputClass} value={edit.f.tarikh_tamat_pendaftaran} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, tarikh_tamat_pendaftaran: e.target.value } })} /></Field>
                        <Field label="ID akaun pengguna kontraktor" htmlFor="c-u" help="Akaun dengan kebenaran ast.kerja.kemas_kini — hanya melihat kerja kontraktor ini."><input id="c-u" inputMode="numeric" className={inputClass} value={edit.f.id_pengguna} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, id_pengguna: e.target.value } })} /></Field>
                        <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={edit.f.status_aktif} onChange={(e) => setEdit({ ...edit, f: { ...edit.f, status_aktif: e.target.checked } })} /> Aktif</label>
                    </ModalBody>
                )}
            </Modal>
        </PageContainer>
    );
}
