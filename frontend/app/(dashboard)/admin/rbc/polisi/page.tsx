'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { rbc } from '@/lib/rbc';
import type { PolicyItem } from '@/lib/rbc';
import { Card, Select } from '../_components/ui';

const GROUPS: Array<{ title: string; prefix: string[] }> = [
    { title: 'Sesi (SRS-RBC-18 – 20)', prefix: ['sesi_'] },
    { title: 'Kata laluan & kunci akaun (SRS-RBC-12, 13)', prefix: ['kata_laluan_', 'kunci_'] },
    { title: 'Kitar hayat akaun & amaran (SRS-RBC-16, 22)', prefix: ['tidak_aktif_', 'amaran_'] },
    { title: 'Pengesahan semula capaian (SRS-RBC-27)', prefix: ['recert_'] },
];

const IP_OPTIONS: Record<string, string> = { tiada: 'Tiada ikatan', subnet: 'Julat rangkaian yang sama (/24)', tepat: 'Alamat IP yang sama' };

function PolicyForm({ items }: { items: PolicyItem[] }) {
    const queryClient = useQueryClient();
    const [values, setValues] = useState<Record<string, number | boolean | string>>(() => Object.fromEntries(items.map((i) => [i.kunci, i.nilai])));
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const save = useMutation({
        mutationFn: () => {
            const changed = Object.fromEntries(items.filter((i) => values[i.kunci] !== i.nilai).map((i) => [i.kunci, values[i.kunci]]));
            return rbc.updatePolicy(changed);
        },
        onSuccess: () => {
            setErrors({});
            queryClient.invalidateQueries({ queryKey: ['rbc', 'polisi'] });
            toast.success('Polisi disimpan dan berkuat kuasa serta-merta.');
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) setErrors(error.errors);
            else toast.error(error instanceof Error ? error.message : 'Gagal menyimpan.');
        },
    });

    function field(item: PolicyItem) {
        const id = `polisi-${item.kunci}`;
        const error = errors[item.kunci]?.[0];
        if (item.jenis === 'bool') {
            return (
                <label htmlFor={id} className="flex items-start gap-3 text-body-sm">
                    <input id={id} type="checkbox" checked={Boolean(values[item.kunci])} onChange={(e) => setValues((v) => ({ ...v, [item.kunci]: e.target.checked }))} className="mt-0.5 rounded border-otl-gray-300 text-primary-600" />
                    <span>{item.label}</span>
                </label>
            );
        }
        if (item.pilihan) {
            return (
                <Select id={id} label={item.label} value={String(values[item.kunci])} onChange={(e) => setValues((v) => ({ ...v, [item.kunci]: e.target.value }))}>
                    {item.pilihan.map((o) => (
                        <option key={o} value={o}>
                            {IP_OPTIONS[o] ?? o}
                        </option>
                    ))}
                </Select>
            );
        }
        return (
            <div>
                <label htmlFor={id} className="block mb-1.5 text-body-sm font-medium text-txt-black-700">
                    {item.label}
                </label>
                <input
                    id={id}
                    type="number"
                    min={item.min ?? undefined}
                    max={item.max ?? undefined}
                    value={String(values[item.kunci])}
                    onChange={(e) => setValues((v) => ({ ...v, [item.kunci]: e.target.value === '' ? '' : Number(e.target.value) }))}
                    aria-describedby={`${id}-help`}
                    className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                />
                <p id={`${id}-help`} className="mt-1 text-body-xs text-txt-black-500">
                    Julat {item.min}–{item.max}; lalai {String(item.lalai)}
                </p>
                {error && <p className="mt-1 text-body-xs text-txt-danger">{error}</p>}
            </div>
        );
    }

    return (
        <>
            {GROUPS.map((g) => (
                <Card key={g.title} title={g.title}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {items.filter((i) => g.prefix.some((p) => i.kunci.startsWith(p))).map((i) => (
                            <div key={i.kunci}>{field(i)}</div>
                        ))}
                    </div>
                </Card>
            ))}
            <div className="flex justify-end">
                <Button onClick={() => save.mutate()} disabled={save.isPending}>
                    Simpan Polisi
                </Button>
            </div>
        </>
    );
}

/** Security policy (stored in the DB — changes need no release). MOD-ADM will later centralise system parameters. */
export default function PolicyPage() {
    const { data, isLoading, error, dataUpdatedAt } = useQuery({ queryKey: ['rbc', 'polisi'], queryFn: rbc.policy });

    return (
        <PageContainer>
            <Alert type="info">Perubahan berkuat kuasa serta-merta tanpa pelepasan kod. Hanya Pentadbir Sistem boleh mengubah polisi ini.</Alert>
            {isLoading ? <p className="text-body-sm text-txt-black-500">Memuatkan…</p> : error ? <p className="text-body-sm text-txt-danger">Polisi tidak dapat dimuatkan.</p> : <PolicyForm key={dataUpdatedAt} items={data?.data ?? []} />}
        </PageContainer>
    );
}
