'use client';

import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { ckiApi, MEDAN_LABEL, ralat, tarikh, useCan, useRujukan, type BilDetail, type Transaksi } from '@/lib/cki';
import { Field, inputClass, InfoGrid, Select, Status } from '../../_components/common';

async function jalan(fn: () => Promise<{ message?: string }>, done: () => void) {
    try {
        const r = await fn();
        toast.success(r.message ?? 'Berjaya.');
        done();
    } catch (e) {
        toast.error(ralat(e));
    }
}

/** SRS-CKI-11..14: request payment for a bill (approval by value threshold through WFE). */
export function MohonBayaran({ bil }: { bil: BilDetail }) {
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const [kaedah, setKaedah] = useState('eft');
    const [amaun, setAmaun] = useState(bil.baki.nilai ?? '');
    const [catatan, setCatatan] = useState('');
    const aktif = bil.transaksi.some((t) => ['menunggu_kelulusan', 'diluluskan', 'dihantar_grp', 'dibayar'].includes(t.status_transaksi));
    if (bil.status_bayaran === 'dibayar' || aktif) return null;

    return (
        <form
            className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end"
            onSubmit={(e) => {
                e.preventDefault();
                jalan(() => ckiApi.mohon(bil.id, { kaedah_bayaran: kaedah, amaun, catatan }), () => qc.invalidateQueries({ queryKey: ['cki', 'bil', bil.id] }));
            }}
        >
            <Field label="Kaedah bayaran" htmlFor="p-kaedah">
                <Select id="p-kaedah" value={kaedah} onChange={setKaedah} options={ref?.kaedah_bayaran ?? []} />
            </Field>
            <Field label="Amaun (RM)" htmlFor="p-amaun">
                <input id="p-amaun" inputMode="decimal" className={inputClass} value={amaun} onChange={(e) => setAmaun(e.target.value)} />
            </Field>
            <Field label="Catatan" htmlFor="p-catatan">
                <input id="p-catatan" className={inputClass} value={catatan} onChange={(e) => setCatatan(e.target.value)} />
            </Field>
            <Button type="submit">Mohon bayaran</Button>
        </form>
    );
}

function RekodForm({ t, onDone }: { t: Transaksi; onDone: () => void }) {
    const [f, setF] = useState<Record<string, string>>({ tarikh_bayaran: new Date().toISOString().slice(0, 10) });
    const fields = t.kaedah_bayaran === 'bank_draf' ? ['no_bank_draf', 'bank_penerbit'] : t.medan_wajib;
    return (
        <form
            className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end"
            onSubmit={(e) => {
                e.preventDefault();
                jalan(() => ckiApi.rekod(t.id, f), onDone);
            }}
        >
            {fields.map((k) => (
                <Field key={k} label={MEDAN_LABEL[k] ?? k} htmlFor={`r-${t.id}-${k}`}>
                    <input id={`r-${t.id}-${k}`} className={inputClass} value={f[k] ?? ''} onChange={(e) => setF((p) => ({ ...p, [k]: e.target.value }))} required />
                </Field>
            ))}
            <Field label="Tarikh bayaran" htmlFor={`r-${t.id}-tarikh`}>
                <input id={`r-${t.id}-tarikh`} type="date" className={inputClass} value={f.tarikh_bayaran} onChange={(e) => setF((p) => ({ ...p, tarikh_bayaran: e.target.value }))} />
            </Field>
            <Button type="submit" size="sm">
                Rekod bayaran ({t.kaedah_bayaran_nama})
            </Button>
        </form>
    );
}

function ResitForm({ t, onDone }: { t: Transaksi; onDone: () => void }) {
    const [no, setNo] = useState(t.no_resit ?? '');
    const [fail, setFail] = useState<File | null>(null);
    return (
        <form
            className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
            onSubmit={(e) => {
                e.preventDefault();
                if (!fail) return;
                const form = new FormData();
                form.append('no_resit', no);
                form.append('fail', fail);
                jalan(() => ckiApi.resit(t.id, form), onDone);
            }}
        >
            <Field label="No. resit" htmlFor={`s-${t.id}-no`}>
                <input id={`s-${t.id}-no`} className={inputClass} value={no} onChange={(e) => setNo(e.target.value)} required />
            </Field>
            <Field label="Fail resit / bukti bayaran" htmlFor={`s-${t.id}-fail`}>
                <input id={`s-${t.id}-fail`} type="file" accept=".pdf,.jpg,.jpeg,.png" className="block w-full text-body-sm" onChange={(e) => setFail(e.target.files?.[0] ?? null)} required />
            </Field>
            <Button type="submit" size="sm" variant="outline">
                Muat naik resit
            </Button>
        </form>
    );
}

/** One payment transaction with the actions its status allows. */
export function TransaksiKad({ t, bilId, resitAda }: { t: Transaksi; bilId: string; resitAda: boolean }) {
    const qc = useQueryClient();
    const can = useCan();
    const refresh = () => qc.invalidateQueries({ queryKey: ['cki', 'bil', bilId] });
    const boleh = can('cki.bayaran.cipta');

    return (
        <div className="rounded-lg border border-otl-gray-200 p-4 space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{t.no_permohonan}</span>
                <Status kod={t.status_transaksi} nama={t.status_transaksi_nama} />
                {t.kelulusan && <Status kod={t.kelulusan.status} nama={`Kelulusan: ${t.kelulusan.peringkat_nama} (${t.kelulusan.status})`} />}
            </div>
            <InfoGrid
                items={[
                    ['Kaedah', t.kaedah_bayaran_nama],
                    ['Amaun', t.amaun.rm],
                    ['Tarikh bayaran', tarikh(t.tarikh_bayaran)],
                    ['No. resit', t.no_resit],
                    ['Rujukan EFT / bank draf / FPX', [t.no_rujukan_eft, t.no_bank_draf && `${t.no_bank_draf} (${t.bank_penerbit ?? '—'})`, t.rujukan_fpx].filter(Boolean).join(' · ') || '—'],
                    ['Kaunter', t.nama_kaunter],
                    ['GRP MAIS', t.rujukan_grp ? `${t.rujukan_grp} (${t.status_grp ?? '—'})` : '—'],
                ]}
            />
            {boleh && t.status_transaksi === 'diluluskan' && (
                <div className="space-y-3">
                    {['fpx', 'eft', 'bank_draf'].includes(t.kaedah_bayaran) && (
                        <Button size="sm" onClick={() => jalan(() => ckiApi.laksana(t.id), refresh)}>
                            {t.kaedah_bayaran === 'fpx' ? 'Bayar terus melalui API berpusat' : 'Hantar permohonan ke GRP MAIS'}
                        </Button>
                    )}
                    <RekodForm t={t} onDone={refresh} />
                </div>
            )}
            {boleh && t.status_transaksi === 'dihantar_grp' && (
                <Button size="sm" variant="outline" onClick={() => jalan(() => ckiApi.semakGrp(t.id), refresh)}>
                    Semak status GRP
                </Button>
            )}
            {boleh && t.status_transaksi === 'dibayar' && (
                <div className="space-y-3">
                    <ResitForm t={t} onDone={refresh} />
                    <Button size="sm" disabled={!resitAda} onClick={() => jalan(() => ckiApi.selesai(t.id), refresh)}>
                        Tandakan selesai
                    </Button>
                    {!resitAda && <p className="text-body-xs text-txt-black-500">Transaksi hanya boleh diselesaikan selepas sekurang-kurangnya satu resit atau bukti bayaran direkodkan.</p>}
                </div>
            )}
        </div>
    );
}
