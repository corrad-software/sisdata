'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { pjkApi, ralat, useCan } from '@/lib/pjk';
import { Card, Field, inputClass, Kosong } from '../_components/common';

/** SRS-PJK-09: letter templates with merge fields; saving creates a new version (old versions kept). */
export default function TemplatPage() {
    const can = useCan();
    const qc = useQueryClient();
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'templat'], queryFn: () => pjkApi.templat() });
    const [edit, setEdit] = useState<Record<number, { tajuk: string; kandungan: string }>>({});

    async function save(id: number) {
        const e = edit[id];
        if (!e) return;
        try {
            const r = await pjkApi.templatKemasKini(id, e);
            toast.success(r.message);
            setEdit((p) => {
                const n = { ...p };
                delete n[id];
                return n;
            });
            qc.invalidateQueries({ queryKey: ['pjk', 'templat'] });
        } catch (err) {
            toast.error(ralat(err));
        }
    }

    const medan = data?.medan ?? [];

    return (
        <PageContainer>
            <Card title="Medan gabungan">
                <p className="text-body-sm text-txt-black-700 break-words">{medan.map((m) => `{{${m}}}`).join('  ')}</p>
            </Card>
            {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
            {isLoading && <Kosong>Memuatkan…</Kosong>}
            {data?.data.map((t) => {
                const e = edit[t.id];
                return (
                    <Card
                        key={t.id}
                        title={`${t.jenis_surat_nama} — v${t.versi}`}
                        actions={
                            can('pjk.templat.kemas_kini') ? (
                                e ? (
                                    <>
                                        <Button size="sm" variant="secondary" onClick={() => setEdit((p) => { const n = { ...p }; delete n[t.id]; return n; })}>
                                            Batal
                                        </Button>
                                        <Button size="sm" onClick={() => save(t.id)}>
                                            Simpan versi baharu
                                        </Button>
                                    </>
                                ) : (
                                    <Button size="sm" variant="outline" onClick={() => setEdit((p) => ({ ...p, [t.id]: { tajuk: t.tajuk, kandungan: t.kandungan } }))}>
                                        Sunting
                                    </Button>
                                )
                            ) : undefined
                        }
                    >
                        {e ? (
                            <div className="space-y-3">
                                <Field label="Tajuk" htmlFor={`t-tajuk-${t.id}`}>
                                    <input id={`t-tajuk-${t.id}`} className={inputClass} value={e.tajuk} onChange={(ev) => setEdit((p) => ({ ...p, [t.id]: { ...e, tajuk: ev.target.value } }))} />
                                </Field>
                                <Field label="Kandungan" htmlFor={`t-isi-${t.id}`}>
                                    <textarea id={`t-isi-${t.id}`} rows={12} className={`${inputClass} font-mono`} value={e.kandungan} onChange={(ev) => setEdit((p) => ({ ...p, [t.id]: { ...e, kandungan: ev.target.value } }))} />
                                </Field>
                            </div>
                        ) : (
                            <>
                                <p className="font-medium text-body-sm">{t.tajuk}</p>
                                <pre className="whitespace-pre-wrap break-words text-body-xs text-txt-black-700">{t.kandungan}</pre>
                            </>
                        )}
                    </Card>
                );
            })}
        </PageContainer>
    );
}
