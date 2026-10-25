'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { pwsApi, ralat, tarikhMasa, useCan, type Kandungan } from '@/lib/pws';
import { AiBadge, Card, Field, inputClass, Memuat, StatusKandunganBadge } from '../../_components/common';

type Borang = Pick<Kandungan, 'tajuk_bm' | 'tajuk_bi' | 'kandungan_bm' | 'kandungan_bi' | 'ringkasan' | 'kemajuan_peratus' | 'lokasi'>;

/** One content item: edit while draft, submit for WFE approval, withdraw after publication. */
export default function PwsKandunganSatuPage() {
    const { id } = useParams<{ id: string }>();
    const kid = Number(id);
    const { data, error, isLoading } = useQuery({ queryKey: ['pws', 'kandungan', kid], queryFn: () => pwsApi.satuKandungan(kid).then((r) => r.data) });

    if (error) {
        return (
            <PageContainer>
                <Alert type="error">{ralat(error)}</Alert>
            </PageContainer>
        );
    }
    if (isLoading || !data) {
        return (
            <PageContainer>
                <Memuat />
            </PageContainer>
        );
    }

    return <Penyunting key={`${data.id}-${data.dikemas_kini}`} data={data} />;
}

function Penyunting({ data }: { data: Kandungan }) {
    const kid = data.id;
    const qc = useQueryClient();
    const can = useCan();
    const [form, setForm] = useState<Borang>({
        tajuk_bm: data.tajuk_bm,
        tajuk_bi: data.tajuk_bi ?? '',
        kandungan_bm: data.kandungan_bm ?? '',
        kandungan_bi: data.kandungan_bi ?? '',
        ringkasan: data.ringkasan ?? '',
        kemajuan_peratus: data.kemajuan_peratus ?? null,
        lokasi: data.lokasi ?? '',
    });
    const [msg, setMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [sebab, setSebab] = useState('');

    const done = (text: string) => {
        setMsg({ type: 'success', text });
        qc.invalidateQueries({ queryKey: ['pws', 'kandungan'] });
    };
    const fail = (e: unknown) => setMsg({ type: 'error', text: ralat(e) });
    const save = useMutation({ mutationFn: () => pwsApi.kemasKiniKandungan(kid, form), onSuccess: (r) => done(r.message), onError: fail });
    const submit = useMutation({ mutationFn: () => pwsApi.hantarKandungan(kid), onSuccess: (r) => done(r.message), onError: fail });
    const withdraw = useMutation({ mutationFn: () => pwsApi.tarikKandungan(kid, sebab), onSuccess: (r) => done(r.message), onError: fail });

    const editable = ['draf', 'ditolak', 'ditarik'].includes(data.status) && can('pws.kandungan.cipta');
    const set = (k: keyof Borang, v: string | number | null) => setForm({ ...form, [k]: v });

    return (
        <PageContainer>
            <Card
                title={data.tajuk_bm}
                actions={
                    <>
                        <StatusKandunganBadge s={data.status} />
                        {data.dijana_ai && <AiBadge />}
                    </>
                }
            >
                <dl className="grid grid-cols-1 gap-x-6 gap-y-2 text-body-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-txt-black-500">Jenis</dt>
                        <dd>{data.jenis_nama}</dd>
                    </div>
                    <div>
                        <dt className="text-txt-black-500">Diterbitkan</dt>
                        <dd>{tarikhMasa(data.tarikh_terbit)}</dd>
                    </div>
                    <div>
                        <dt className="text-txt-black-500">Slug portal</dt>
                        <dd className="break-all">{data.slug}</dd>
                    </div>
                    {data.dijana_ai && (
                        <div className="sm:col-span-3">
                            <dt className="text-txt-black-500">Arahan AI ({data.model_ai})</dt>
                            <dd className="whitespace-pre-wrap break-words">{data.prompt_ai}</dd>
                        </div>
                    )}
                    {data.catatan_semakan && (
                        <div className="sm:col-span-3">
                            <dt className="text-txt-black-500">Catatan penyemak</dt>
                            <dd className="whitespace-pre-wrap break-words">{data.catatan_semakan}</dd>
                        </div>
                    )}
                </dl>
                {msg && <Alert type={msg.type}>{msg.text}</Alert>}
                {data.status === 'dalam_semakan' && <Alert type="info">Dalam semakan pegawai penyemak melalui Workflow. Kandungan dikunci sehingga keputusan dibuat.</Alert>}
            </Card>

            <Card title="Kandungan (BM / EN)">
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Field label="Tajuk (BM)" htmlFor="e-tajuk-bm">
                        <input id="e-tajuk-bm" className={inputClass} value={form.tajuk_bm} onChange={(e) => set('tajuk_bm', e.target.value)} disabled={!editable} maxLength={250} />
                    </Field>
                    <Field label="Tajuk (EN)" htmlFor="e-tajuk-bi">
                        <input id="e-tajuk-bi" className={inputClass} value={form.tajuk_bi ?? ''} onChange={(e) => set('tajuk_bi', e.target.value)} disabled={!editable} maxLength={250} />
                    </Field>
                    <Field label="Kandungan (BM)" htmlFor="e-isi-bm">
                        <textarea id="e-isi-bm" rows={10} className={inputClass} value={form.kandungan_bm ?? ''} onChange={(e) => set('kandungan_bm', e.target.value)} disabled={!editable} />
                    </Field>
                    <Field label="Kandungan (EN)" htmlFor="e-isi-bi">
                        <textarea id="e-isi-bi" rows={10} className={inputClass} value={form.kandungan_bi ?? ''} onChange={(e) => set('kandungan_bi', e.target.value)} disabled={!editable} />
                    </Field>
                    {data.jenis === 'projek' && (
                        <>
                            <Field label="Kemajuan (%)" htmlFor="e-kemajuan">
                                <input
                                    id="e-kemajuan"
                                    type="number"
                                    min={0}
                                    max={100}
                                    className={inputClass}
                                    value={form.kemajuan_peratus ?? ''}
                                    onChange={(e) => set('kemajuan_peratus', e.target.value === '' ? null : Number(e.target.value))}
                                    disabled={!editable}
                                />
                            </Field>
                            <Field label="Lokasi" htmlFor="e-lokasi">
                                <input id="e-lokasi" className={inputClass} value={form.lokasi ?? ''} onChange={(e) => set('lokasi', e.target.value)} disabled={!editable} maxLength={200} />
                            </Field>
                        </>
                    )}
                </div>
                {editable && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => save.mutate()} disabled={save.isPending}>
                            Simpan
                        </Button>
                        <Button onClick={() => submit.mutate()} disabled={submit.isPending}>
                            Hantar untuk semakan &amp; kelulusan
                        </Button>
                    </div>
                )}
            </Card>

            {data.status === 'diterbitkan' && can('pws.kandungan.kemas_kini') && (
                <Card title="Tarik balik daripada portal">
                    <Field label="Sebab" htmlFor="e-sebab">
                        <input id="e-sebab" className={inputClass} value={sebab} onChange={(e) => setSebab(e.target.value)} maxLength={1000} />
                    </Field>
                    <Button variant="danger" onClick={() => withdraw.mutate()} disabled={withdraw.isPending || sebab.trim().length < 5}>
                        Tarik balik
                    </Button>
                </Card>
            )}
        </PageContainer>
    );
}
