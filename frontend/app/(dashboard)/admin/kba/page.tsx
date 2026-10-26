'use client';

import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ApiError } from '@/lib/api';
import { kbaApi, PENILAIAN_LABEL, tarikhMasa, type Jawapan, type MesejSejarah, type Penilaian } from '@/lib/kba';
import { inputClass, ralat, SenaraiPetikan, TeksBerpetikan } from './_components/common';

type Mesej = MesejSejarah & { sementara?: boolean };

const dariJawapan = (soalan: string, j: Jawapan): Mesej => ({
    id_log: j.id_log,
    soalan,
    jawapan: j.jawapan,
    status: j.status,
    cukup_sumber: j.cukup_sumber,
    mod: j.mod,
    model: j.model,
    sumber: j.sumber,
    cap_masa: new Date().toISOString(),
    maklum_balas: null,
});

const CONTOH = ['Bagaimanakah kandungan ditapis mengikut kebenaran pengguna?', 'Berapakah had saiz fail yang boleh dimuat naik ke DMS?', 'How are AI prompts audited?'];

function MaklumBalasBar({ m, onDone }: { m: Mesej; onDone: (p: Penilaian) => void }) {
    const [buka, setBuka] = useState<Penilaian | null>(null);
    const [ulasan, setUlasan] = useState('');
    const [mesej, setMesej] = useState<string | null>(null);
    const hantar = useMutation({
        mutationFn: (p: Penilaian) => kbaApi.maklumBalas(m.id_log, p, ulasan),
        onSuccess: (r, p) => {
            setMesej(r.message);
            setBuka(null);
            onDone(p);
        },
        onError: (e) => setMesej(ralat(e)),
    });

    if (m.maklum_balas) {
        return <p className="text-body-xs text-txt-black-500">Maklum balas anda: {PENILAIAN_LABEL[m.maklum_balas]}</p>;
    }
    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2 text-body-xs">
                <span className="text-txt-black-500">Adakah jawapan ini membantu?</span>
                <Button size="sm" variant="secondary" onClick={() => hantar.mutate('membantu')} disabled={hantar.isPending}>
                    Membantu
                </Button>
                <Button size="sm" variant="secondary" onClick={() => setBuka('tidak_membantu')}>
                    Tidak membantu
                </Button>
                <Button size="sm" variant="secondary" onClick={() => setBuka('sumber_salah')}>
                    Sumber salah
                </Button>
            </div>
            {buka && (
                <div className="flex flex-col gap-2 sm:flex-row">
                    <label htmlFor={`ulasan-${m.id_log}`} className="sr-only">
                        Ulasan
                    </label>
                    <input
                        id={`ulasan-${m.id_log}`}
                        className={inputClass}
                        placeholder="Ulasan (pilihan) — apa yang kurang tepat?"
                        value={ulasan}
                        maxLength={2000}
                        onChange={(e) => setUlasan(e.target.value)}
                    />
                    <Button size="sm" onClick={() => hantar.mutate(buka)} disabled={hantar.isPending}>
                        Hantar
                    </Button>
                </div>
            )}
            {mesej && <p className="text-body-xs text-txt-black-500">{mesej}</p>}
        </div>
    );
}

function Gelembung({ m, onFeedback }: { m: Mesej; onFeedback: (p: Penilaian) => void }) {
    return (
        <div className="space-y-3">
            <div className="flex justify-end">
                <div className="max-w-[85%] rounded-lg bg-primary-600 px-4 py-2 text-body-sm text-white break-words">{m.soalan}</div>
            </div>
            <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 space-y-3">
                {m.sementara ? (
                    <p className="text-body-sm text-txt-black-500">Mencari dalam sumber yang diindeks…</p>
                ) : m.status === 'tiada_sumber' ? (
                    <Alert type="warning">{m.jawapan}</Alert>
                ) : m.status === 'ditolak' ? (
                    <Alert type="error">{m.jawapan}</Alert>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={m.mod === 'generatif' ? 'info' : 'neutral'} shape="tag">
                                {m.mod === 'generatif' ? `Dijana model tempatan${m.model ? ` (${m.model})` : ''}` : 'Petikan sumber (ekstraktif)'}
                            </Badge>
                            <span className="text-body-xs text-txt-black-500">{tarikhMasa(m.cap_masa)}</span>
                        </div>
                        <TeksBerpetikan teks={m.jawapan ?? ''} sumber={m.sumber} />
                        <div className="border-t border-otl-divider pt-3">
                            <p className="mb-2 text-body-xs font-semibold uppercase tracking-wide text-txt-black-500">Sumber dipetik</p>
                            <SenaraiPetikan sumber={m.sumber} />
                        </div>
                    </>
                )}
                {!m.sementara && m.status !== 'ditolak' && <MaklumBalasBar m={m} onDone={onFeedback} />}
            </div>
        </div>
    );
}

/** SRS-KBA-03/04/05/17: ask in BM or English, grounded answers with clickable citations, history and feedback. */
export default function TanyaPage() {
    const qc = useQueryClient();
    const [soalan, setSoalan] = useState('');
    const [idPerbualan, setIdPerbualan] = useState<string | null>(null);
    const [mesej, setMesej] = useState<Mesej[]>([]);
    const [ralatMesej, setRalatMesej] = useState<string | null>(null);
    const bawah = useRef<HTMLDivElement>(null);

    const { data: status } = useQuery({ queryKey: ['kba', 'status-ai'], queryFn: () => kbaApi.statusAi().then((r) => r.data), staleTime: 60_000 });
    const { data: sejarah } = useQuery({ queryKey: ['kba', 'perbualan'], queryFn: () => kbaApi.perbualan().then((r) => r.data) });

    useEffect(() => {
        bawah.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [mesej.length]);

    const tanya = useMutation({
        mutationFn: (q: string) => kbaApi.tanya(q, idPerbualan).then((r) => r.data),
        onMutate: (q) => {
            setRalatMesej(null);
            setMesej((prev) => [...prev, { ...dariJawapan(q, { id_log: -Date.now(), id_perbualan: null, status: 'berjaya', cukup_sumber: false, jawapan: '', mod: null, model: null, bahasa: 'ms', sumber: [] }), sementara: true }]);
        },
        onSuccess: (j, q) => {
            setMesej((prev) => [...prev.filter((m) => !m.sementara), dariJawapan(q, j)]);
            if (j.id_perbualan) setIdPerbualan(j.id_perbualan);
            qc.invalidateQueries({ queryKey: ['kba', 'perbualan'] });
        },
        onError: (e, q) => {
            setMesej((prev) => prev.filter((m) => !m.sementara));
            if (e instanceof ApiError && e.status === 429) {
                setMesej((prev) => [...prev, { ...dariJawapan(q, { id_log: -Date.now(), id_perbualan: null, status: 'ditolak', cukup_sumber: false, jawapan: e.message, mod: null, model: null, bahasa: 'ms', sumber: [] }) }]);
            } else {
                setRalatMesej(ralat(e));
            }
        },
    });

    const buka = async (id: string) => {
        const r = await kbaApi.perbualanSatu(id);
        setIdPerbualan(id);
        setMesej(r.data.mesej);
    };
    const sembunyi = useMutation({
        mutationFn: (id: string) => kbaApi.sembunyi(id),
        onSuccess: (_r, id) => {
            if (id === idPerbualan) {
                setIdPerbualan(null);
                setMesej([]);
            }
            qc.invalidateQueries({ queryKey: ['kba', 'perbualan'] });
        },
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const q = soalan.trim();
        if (q.length < 2 || tanya.isPending) return;
        setSoalan('');
        tanya.mutate(q);
    };

    return (
        <PageContainer>
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <aside className="order-2 space-y-3 lg:order-1" data-panduan="kba-sisi">
                    <div className="flex items-center justify-between">
                        <h3 className="font-heading text-body-md font-semibold text-txt-black-900">Sejarah perbualan</h3>
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => {
                                setIdPerbualan(null);
                                setMesej([]);
                            }}
                        >
                            Baharu
                        </Button>
                    </div>
                    <ul className="max-h-[28rem] space-y-1 overflow-y-auto rounded-lg border border-otl-gray-200 bg-bg-white p-2">
                        {(sejarah ?? []).length === 0 && <li className="px-2 py-3 text-body-xs text-txt-black-500">Belum ada perbualan.</li>}
                        {(sejarah ?? []).map((p) => (
                            <li key={p.id} className={`group flex items-start gap-2 rounded-md px-2 py-2 ${p.id === idPerbualan ? 'bg-bg-primary-50' : 'hover:bg-bg-washed'}`}>
                                <button type="button" className="min-w-0 flex-1 text-left" onClick={() => buka(p.id)}>
                                    <span className="block truncate text-body-sm text-txt-black-900">{p.tajuk}</span>
                                    <span className="block text-body-xs text-txt-black-500">
                                        {p.log_count} soalan · {tarikhMasa(p.updated_at)}
                                    </span>
                                </button>
                                <button type="button" aria-label="Buang daripada sejarah" className="text-body-xs text-txt-black-500 hover:text-txt-danger" onClick={() => sembunyi.mutate(p.id)}>
                                    ✕
                                </button>
                            </li>
                        ))}
                    </ul>
                    {status && (
                        <p className="text-body-xs text-txt-black-500">
                            Mod jawapan: <strong>{status.mod_jawapan === 'generatif' ? 'Generatif (model tempatan)' : 'Ekstraktif (petikan sahaja)'}</strong>
                            {status.model_llm ? ` · ${status.model_llm}${status.llm_tersedia ? '' : ' (tidak tersedia)'}` : ''} · embedding {status.model_embedding}. Semua model berjalan dalam
                            persekitaran MAIS — tiada AI awam.
                        </p>
                    )}
                </aside>

                <div className="order-1 min-w-0 space-y-4 lg:order-2">
                    {mesej.length === 0 && (
                        <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-6 space-y-3">
                            <h3 className="font-heading text-body-lg font-semibold text-txt-black-900">Tanya Pangkalan Pengetahuan SISDATA</h3>
                            <p className="text-body-sm text-txt-black-700">
                                Tanya dalam Bahasa Melayu atau Bahasa Inggeris — soalan operasi, capaian atau aliran proses. Jawapan hanya berdasarkan sumber yang anda dibenarkan baca, dengan
                                petikan yang boleh diklik. Jika tiada sumber mencukupi, sistem akan menyatakannya.
                            </p>
                            <div className="flex flex-wrap gap-2" data-panduan="kba-contoh">
                                {CONTOH.map((c) => (
                                    <button key={c} type="button" className="rounded-full border border-otl-gray-300 px-3 py-1 text-body-xs text-txt-black-700 hover:bg-bg-washed" onClick={() => setSoalan(c)}>
                                        {c}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                    {mesej.map((m) => (
                        <Gelembung key={m.id_log} m={m} onFeedback={(p) => setMesej((prev) => prev.map((x) => (x.id_log === m.id_log ? { ...x, maklum_balas: p } : x)))} />
                    ))}
                    <div ref={bawah} />
                    {ralatMesej && <Alert type="error">{ralatMesej}</Alert>}
                    <form onSubmit={submit} data-panduan="kba-soalan" className="sticky bottom-0 flex flex-col gap-2 rounded-lg border border-otl-gray-200 bg-bg-white p-3 shadow-card sm:flex-row">
                        <label htmlFor="soalan" className="sr-only">
                            Soalan
                        </label>
                        <textarea
                            id="soalan"
                            rows={2}
                            maxLength={1000}
                            className={`${inputClass} resize-none`}
                            placeholder="Tulis soalan anda…"
                            value={soalan}
                            onChange={(e) => setSoalan(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) submit(e);
                            }}
                        />
                        <Button type="submit" disabled={tanya.isPending || soalan.trim().length < 2} className="sm:self-end">
                            {tanya.isPending ? 'Mencari…' : 'Tanya'}
                        </Button>
                    </form>
                </div>
            </div>
        </PageContainer>
    );
}
