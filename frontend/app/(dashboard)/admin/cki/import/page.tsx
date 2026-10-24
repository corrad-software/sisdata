'use client';

import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { ckiApi, muatTurun, ralat } from '@/lib/cki';
import { Card, Field } from '../_components/common';

type Hasil = Awaited<ReturnType<typeof ckiApi.import>>;

/** Annual bill import from Excel / CSV with row-by-row validation (SRS-DAT-03); dry run first. */
export default function ImportPage() {
    const qc = useQueryClient();
    const [fail, setFail] = useState<File | null>(null);
    const [hasil, setHasil] = useState<Hasil | null>(null);
    const [busy, setBusy] = useState(false);

    async function hantar(dryRun: boolean) {
        if (!fail) return;
        setBusy(true);
        try {
            const form = new FormData();
            form.append('fail', fail);
            if (dryRun) form.append('dry_run', '1');
            const r = await ckiApi.import(form);
            setHasil(r);
            if (!dryRun) {
                toast.success(`${r.diterima} bil diimport.`);
                qc.invalidateQueries({ queryKey: ['cki'] });
            }
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <PageContainer>
            <Card
                title="Import bil cukai tahunan"
                actions={
                    <Button size="sm" variant="outline" onClick={() => muatTurun('/cki/import/templat', {}, 'templat-import-bil-cukai.xlsx').catch((e) => toast.error(ralat(e)))}>
                        Muat turun templat
                    </Button>
                }
            >
                <p className="text-body-sm text-txt-black-500">
                    Kolum: jenis_cukai, no_akaun_cukai, tahun_taksiran, no_bil, amaun_pokok, amaun_denda, tarikh_bil, tarikh_akhir, status_bayaran, tarikh_bayar, no_resit. Akaun mesti telah didaftarkan dalam daftar obligasi.
                </p>
                <Field label="Fail Excel / CSV" htmlFor="i-fail">
                    <input id="i-fail" type="file" accept=".xlsx,.xls,.csv" className="block w-full text-body-sm" onChange={(e) => { setFail(e.target.files?.[0] ?? null); setHasil(null); }} />
                </Field>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" disabled={!fail || busy} onClick={() => hantar(true)}>
                        Semak (tanpa simpan)
                    </Button>
                    <Button disabled={!fail || busy || !hasil || hasil.simpan || hasil.diterima === 0} onClick={() => hantar(false)}>
                        Import
                    </Button>
                </div>
            </Card>
            {hasil && (
                <Card title={hasil.simpan ? 'Keputusan import' : 'Keputusan semakan'}>
                    <p className="text-body-sm">
                        {hasil.jumlah_baris} baris: <strong className="text-txt-success">{hasil.diterima} diterima</strong>, <strong className="text-txt-danger">{hasil.ditolak} ditolak</strong>.
                    </p>
                    {hasil.ralat.length > 0 && (
                        <Table head={<><th>Baris</th><th>Sebab</th></>}>
                            {hasil.ralat.map((r) => (
                                <tr key={r.baris}>
                                    <td>{r.baris}</td>
                                    <td className="break-words">{r.mesej}</td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>
            )}
        </PageContainer>
    );
}
