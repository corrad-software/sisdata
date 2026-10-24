'use client';

import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Stepper } from '@/components/ui/Stepper';
import { Button } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { ApiError } from '@/lib/api';
import { perApi, useDaerah, useMukim, useSaluran, type KesPenuh, type SaluranPenuh } from '@/lib/per';
import { warnaLatar } from '@/lib/legend-pemerolehan';
import { BorangSaluran } from '../_components/BorangSaluran';
import { PihakForm, type PihakInput } from '../_components/PihakForm';
import { SenaraiSemak } from '../_components/SenaraiSemak';
import { cardClass, ErrorList, inputClass, SaluranIkon } from '../_components/common';

const STEPS = ['Saluran', 'Borang saluran', 'Pihak berkaitan', 'Senarai semak'];

export default function KesBaharuPage() {
    return (
        <Suspense>
            <Wizard />
        </Suspense>
    );
}

/** New case wizard: channel → channel form → parties → checklist (SRS-PER-01/16/23). */
function Wizard() {
    const router = useRouter();
    const params = useSearchParams();
    const lotDariProfil = params.get('lot');
    const { data: channels, isLoading } = useSaluran();
    const [step, setStep] = useState(1);
    const [saluran, setSaluran] = useState<SaluranPenuh | null>(null);
    const [umum, setUmum] = useState({ tajuk: '', daerah: '', kod_mukim: '', no_lot: '', tarikh_mula_kes: new Date().toISOString().slice(0, 10), nilai_pasaran: '', nilai_pemerolehan: '', sumber_dana: '', rujukan_penilaian_jpph: '', catatan: '' });
    const [butiran, setButiran] = useState<Record<string, string | null>>({});
    const [pihak, setPihak] = useState<PihakInput[]>([]);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [kes, setKes] = useState<KesPenuh | null>(null);
    const { data: daerah } = useDaerah();
    const { data: mukim } = useMukim(umum.daerah || undefined);
    const { data: kesTerkini, refetch } = useQuery({ queryKey: ['per', 'kes', kes?.id], queryFn: () => perApi.kes(kes!.id).then((r) => r.data), enabled: kes !== null, initialData: kes ?? undefined });

    async function simpan() {
        if (!saluran) return;
        setBusy(true);
        setErrors({});
        setMessage(null);
        const body = {
            kod_saluran: saluran.kod,
            tajuk: umum.tajuk,
            ...(lotDariProfil ? { lot_id: lotDariProfil } : { kod_mukim: umum.kod_mukim, no_lot: umum.no_lot }),
            tarikh_mula_kes: umum.tarikh_mula_kes,
            ...Object.fromEntries(['nilai_pasaran', 'nilai_pemerolehan', 'sumber_dana', 'rujukan_penilaian_jpph', 'catatan'].map((k) => [k, umum[k as keyof typeof umum] || undefined])),
            butiran: Object.fromEntries(Object.entries(butiran).filter(([, v]) => v !== '' && v !== null)),
            pihak,
        };
        try {
            const res = await perApi.cipta(body);
            setKes(res.data);
            toast.success(res.message);
            setStep(4);
        } catch (e) {
            if (e instanceof ApiError) {
                setErrors(e.errors ?? {});
                setMessage(e.message);
                if (e.errors && Object.keys(e.errors).some((k) => ['tajuk', 'kod_mukim', 'no_lot', 'lot_id', 'tarikh_mula_kes'].includes(k) || k.startsWith('butiran') || Object.keys(butiran).includes(k))) setStep(2);
            } else {
                setMessage('Kes tidak dapat disimpan.');
            }
        } finally {
            setBusy(false);
        }
    }

    const err = (k: string) => errors[k]?.[0];

    return (
        <PageContainer>
            <div className={cardClass}>
                <Stepper steps={STEPS} current={step} />
            </div>

            {message && (
                <Alert type="error">
                    <p>{message}</p>
                    <ErrorList items={Object.values(errors).flat()} />
                </Alert>
            )}

            {step === 1 && (
                <div className={`${cardClass} space-y-4`}>
                    <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Pilih saluran pemerolehan</h3>
                    {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        {channels?.filter((c) => c.status_aktif).map((c) => (
                            <button
                                key={c.kod}
                                type="button"
                                onClick={() => {
                                    setSaluran(c);
                                    setButiran({});
                                    setPihak([]);
                                    setStep(2);
                                }}
                                className={`text-left rounded-lg border-2 p-4 hover:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary ${saluran?.kod === c.kod ? '' : 'border-otl-gray-200'}`}
                                style={saluran?.kod === c.kod ? { borderColor: c.warna } : undefined}
                            >
                                <div className="flex items-start gap-3">
                                    <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg" style={{ color: c.warna, backgroundColor: warnaLatar(c.warna) }}>
                                        <SaluranIkon ikon={c.ikon} className="h-6 w-6" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="font-medium text-body-sm text-txt-black-900">{c.nama}</p>
                                        <p className="text-body-xs text-txt-black-500">
                                            Lampiran H {c.rujukan_lampiran_h} · {c.nama_bahagian} · sasaran {c.tempoh_sasaran_hari} hari
                                        </p>
                                        {c.keterangan && <p className="mt-1 text-body-xs text-txt-black-700 line-clamp-2">{c.keterangan}</p>}
                                    </div>
                                </div>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {step === 2 && saluran && (
                <div className={`${cardClass} space-y-6`}>
                    <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{saluran.nama}</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="tajuk" className="mb-1.5">
                                Tajuk kes
                            </InputLabel>
                            <input id="tajuk" className={inputClass} value={umum.tajuk} onChange={(e) => setUmum({ ...umum, tajuk: e.target.value })} />
                            {err('tajuk') && <p className="mt-1 text-body-xs text-txt-danger">{err('tajuk')}</p>}
                        </div>
                        {lotDariProfil ? (
                            <div className="sm:col-span-2">
                                <Badge variant="info">Lot dipilih daripada Profil Hartanah</Badge>
                            </div>
                        ) : (
                            <>
                                <div>
                                    <InputLabel htmlFor="daerah" className="mb-1.5">
                                        Daerah
                                    </InputLabel>
                                    <select id="daerah" className={inputClass} value={umum.daerah} onChange={(e) => setUmum({ ...umum, daerah: e.target.value, kod_mukim: '' })}>
                                        <option value="">— Pilih —</option>
                                        {daerah?.map((d) => (
                                            <option key={d.kod} value={d.kod}>
                                                {d.nama}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <InputLabel htmlFor="mukim" className="mb-1.5">
                                        Mukim
                                    </InputLabel>
                                    <select id="mukim" className={inputClass} value={umum.kod_mukim} disabled={!umum.daerah} onChange={(e) => setUmum({ ...umum, kod_mukim: e.target.value })}>
                                        <option value="">— Pilih —</option>
                                        {mukim?.map((m) => (
                                            <option key={m.kod} value={m.kod}>
                                                {m.nama}
                                            </option>
                                        ))}
                                    </select>
                                    {err('kod_mukim') && <p className="mt-1 text-body-xs text-txt-danger">{err('kod_mukim')}</p>}
                                </div>
                                <div>
                                    <InputLabel htmlFor="no_lot" className="mb-1.5">
                                        No. lot
                                    </InputLabel>
                                    <input id="no_lot" className={inputClass} value={umum.no_lot} onChange={(e) => setUmum({ ...umum, no_lot: e.target.value })} placeholder="cth. PT 12345" />
                                    <p className="mt-1 text-body-xs text-txt-black-500">Jika lot belum didaftarkan, kes dipautkan sebagai lot dijangka dan dipindahkan secara automatik apabila lot didaftarkan.</p>
                                    {(err('no_lot') || err('lot_id')) && <p className="mt-1 text-body-xs text-txt-danger">{err('no_lot') ?? err('lot_id')}</p>}
                                </div>
                            </>
                        )}
                        <div>
                            <InputLabel htmlFor="mula" className="mb-1.5">
                                Tarikh mula kes
                            </InputLabel>
                            <input id="mula" type="date" className={inputClass} value={umum.tarikh_mula_kes} onChange={(e) => setUmum({ ...umum, tarikh_mula_kes: e.target.value })} />
                        </div>
                    </div>

                    <fieldset className="space-y-3">
                        <legend className="font-heading font-semibold text-body-md text-txt-black-900">Nilai pemerolehan</legend>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {(
                                [
                                    ['nilai_pasaran', 'Nilai pasaran (RM)', 'number'],
                                    ['nilai_pemerolehan', 'Nilai pemerolehan (RM)', 'number'],
                                    ['sumber_dana', 'Sumber dana', 'text'],
                                    ['rujukan_penilaian_jpph', 'Rujukan penilaian JPPH', 'text'],
                                ] as const
                            ).map(([k, label, type]) => (
                                <div key={k}>
                                    <InputLabel htmlFor={k} className="mb-1.5">
                                        {label}
                                    </InputLabel>
                                    <input id={k} type={type} min={type === 'number' ? 0 : undefined} step={type === 'number' ? '0.01' : undefined} className={inputClass} value={umum[k]} onChange={(e) => setUmum({ ...umum, [k]: e.target.value })} />
                                </div>
                            ))}
                        </div>
                    </fieldset>

                    <BorangSaluran saluran={saluran} values={butiran} onChange={(k, v) => setButiran((b) => ({ ...b, [k]: v }))} errors={errors} />

                    <div className="flex justify-between gap-2">
                        <Button variant="secondary" onClick={() => setStep(1)}>
                            Kembali
                        </Button>
                        <Button onClick={() => setStep(3)} disabled={!umum.tajuk.trim() || (!lotDariProfil && (!umum.kod_mukim || !umum.no_lot.trim()))}>
                            Seterusnya
                        </Button>
                    </div>
                </div>
            )}

            {step === 3 && saluran && (
                <div className={`${cardClass} space-y-4`}>
                    <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Pihak berkaitan</h3>
                    <p className="text-body-sm text-txt-black-500">Nama dan no. pengenalan ialah maklumat peribadi (Sulit) — dikaburkan kepada pengguna yang tidak dibenarkan.</p>
                    {pihak.length > 0 && (
                        <ul className="divide-y divide-otl-divider rounded-lg border border-otl-gray-200">
                            {pihak.map((p, i) => (
                                <li key={i} className="flex items-center justify-between gap-3 p-3 text-body-sm">
                                    <span className="min-w-0 break-words">
                                        <strong>{saluran.peranan_pihak.find((r) => r.kod === p.peranan_pihak)?.label}</strong> · {p.nama}
                                        {p.hubungan ? ` (${p.hubungan})` : ''}
                                    </span>
                                    <Button size="sm" variant="outline" onClick={() => setPihak((x) => x.filter((_, j) => j !== i))}>
                                        Buang
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <PihakForm roles={saluran.peranan_pihak} onAdd={(p) => setPihak((x) => [...x, p])} />
                    <div className="flex justify-between gap-2">
                        <Button variant="secondary" onClick={() => setStep(2)}>
                            Kembali
                        </Button>
                        <Button onClick={simpan} disabled={busy}>
                            {busy ? 'Menyimpan…' : 'Simpan kes & teruskan'}
                        </Button>
                    </div>
                </div>
            )}

            {step === 4 && kesTerkini && (
                <div className={`${cardClass} space-y-4`}>
                    <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Senarai semak dokumen — {kesTerkini.no_kes}</h3>
                    <p className="text-body-sm text-txt-black-500">Kes telah didaftarkan. Muat naik dokumen sokongan wajib sekarang atau kemudian dari halaman kes.</p>
                    <SenaraiSemak kes={kesTerkini} onChanged={() => refetch()} />
                    <div className="flex justify-end">
                        <Button onClick={() => router.push(`/admin/per/kes/${kesTerkini.id}`)}>Ke halaman kes</Button>
                    </div>
                </div>
            )}
        </PageContainer>
    );
}
