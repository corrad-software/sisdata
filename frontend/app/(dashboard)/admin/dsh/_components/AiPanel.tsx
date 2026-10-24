'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { inputClass } from './PenapisBar';
import { dshApi, errorMessage, type Aras, type JawapanAi, type Penapis } from '@/lib/dsh';
import { KpiWidget } from './Widgets';

/**
 * Natural-language prompt (SRS-DSH-15/16): ask in Malay or English, get a
 * number, chart or table — or change the personal layout. Processed by the
 * local engine; answers are always limited to the user's own scope and every
 * prompt is audited (SRS-DSH-17/18).
 */
export function AiPanel({ aras, p }: { aras: Aras; p: Penapis }) {
    const [prompt, setPrompt] = useState('');
    const [answer, setAnswer] = useState<JawapanAi | null>(null);
    const qc = useQueryClient();
    const ask = useMutation({
        mutationFn: (text: string) => dshApi.prompt(text, aras, p),
        onSuccess: (a) => {
            setAnswer(a);
            if (a.jawapan.jenis === 'susun_atur') qc.invalidateQueries({ queryKey: ['dsh', 'dashboard'] });
        },
    });

    return (
        <section aria-labelledby="ai-tajuk" className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card">
            <h2 id="ai-tajuk" className="text-body-sm font-semibold text-txt-black-900">
                Tanya dashboard
            </h2>
            <p className="mt-0.5 text-body-xs text-txt-black-500">
                Cth. “Berapa jumlah tanah di Petaling?”, “Tunjuk trend kadar kutipan”, “Tambah widget aduan terbuka”. Diproses dalam persekitaran MAIS sahaja; setiap pertanyaan direkod dalam jejak audit.
            </p>
            <form
                className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (prompt.trim().length >= 2) ask.mutate(prompt.trim());
                }}
            >
                <label htmlFor="ai-prompt" className="sr-only">
                    Soalan kepada dashboard
                </label>
                <textarea id="ai-prompt" rows={2} className={`${inputClass} flex-1`} value={prompt} maxLength={1000} onChange={(e) => setPrompt(e.target.value)} placeholder="Taip soalan anda…" />
                <Button type="submit" disabled={ask.isPending || prompt.trim().length < 2}>
                    {ask.isPending ? 'Memproses…' : 'Tanya'}
                </Button>
            </form>

            {ask.isError && (
                <Alert type="error" className="mt-3">
                    {errorMessage(ask.error)}
                </Alert>
            )}

            {answer && (
                <div className="mt-4 space-y-3" aria-live="polite">
                    {answer.jawapan.nota.map((n, i) => (
                        <Alert key={i} type="warning">
                            {n}
                        </Alert>
                    ))}
                    <p className="text-body-sm text-txt-black-900">{answer.jawapan.teks}</p>
                    {answer.jawapan.cadangan && (
                        <p className="text-body-xs text-txt-black-500">Indikator tersedia: {answer.jawapan.cadangan.join(', ')}</p>
                    )}
                    {answer.jawapan.jenis !== 'jadual' && answer.jawapan.kpi.length > 0 && (
                        <div className="grid gap-3 md:grid-cols-2">
                            {answer.jawapan.kpi.map((k) => (
                                <KpiWidget key={k.key} k={k} p={{ ...p, ...(answer.pertanyaan.penapis as Penapis) }} visual={answer.jawapan.jenis === 'carta' ? (answer.jawapan.visual === 'stat' ? 'line' : (answer.jawapan.visual as never)) : 'stat'} />
                            ))}
                        </div>
                    )}
                    {answer.jawapan.jadual && (
                        <div className="overflow-x-auto rounded-lg border border-otl-gray-200">
                            <table className="min-w-full divide-y divide-otl-gray-200 text-left text-body-sm">
                                <thead className="bg-bg-washed">
                                    <tr>
                                        {answer.jawapan.jadual.lajur.map((l) => (
                                            <th key={l.kod} className="whitespace-nowrap px-3 py-2 font-semibold text-txt-black-700">
                                                {l.label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-otl-gray-200">
                                    {answer.jawapan.jadual.baris.map((r, i) => (
                                        <tr key={i}>
                                            {answer.jawapan.jadual!.lajur.map((l, j) => (
                                                <td key={l.kod} className="px-3 py-2">
                                                    {j === 0 && r.pautan ? (
                                                        <Link href={r.pautan} className="text-txt-primary hover:underline">
                                                            {String(r.sel[l.kod] ?? '—')}
                                                        </Link>
                                                    ) : (
                                                        String(r.sel[l.kod] ?? '—')
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <p className="px-3 py-2 text-body-xs text-txt-black-500">{answer.jawapan.jadual.jumlah} rekod (10 pertama dipaparkan)</p>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}
