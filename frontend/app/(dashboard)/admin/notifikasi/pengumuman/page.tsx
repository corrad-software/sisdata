'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { FieldInput } from '@/components/ui/FieldInput';
import { InputLabel } from '@/components/ui/InputLabel';
import { Textarea } from '@/components/ui/Textarea';
import { ApiError } from '@/lib/api';
import { fetchGroups, sendAnnouncement } from '@/lib/ntf';

type Target = { jenis: 'peranan' | 'bahagian' | 'daerah'; nilai: string };

const LABEL: Record<Target['jenis'], string> = { peranan: 'Peranan', bahagian: 'Bahagian', daerah: 'Daerah' };

const selectClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

export default function AnnouncementPage() {
    const { data, error } = useQuery({ queryKey: ['ntf', 'kumpulan'], queryFn: fetchGroups });
    const [tajuk, setTajuk] = useState('');
    const [kandungan, setKandungan] = useState('');
    const [pautan, setPautan] = useState('');
    const [targets, setTargets] = useState<Target[]>([]);
    const [jenis, setJenis] = useState<Target['jenis']>('peranan');
    const [nilai, setNilai] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const send = useMutation({
        mutationFn: () => sendAnnouncement({ tajuk, kandungan, pautan: pautan || undefined, sasaran: targets }),
        onSuccess: (res) => {
            toast.success(res.message);
            setTajuk('');
            setKandungan('');
            setPautan('');
            setTargets([]);
            setErrors({});
        },
        onError: (e) => {
            if (e instanceof ApiError && e.errors) setErrors(e.errors);
            toast.error(e instanceof ApiError && e.status === 429 ? 'Had pengumuman sejam telah dicapai.' : 'Pengumuman tidak dihantar.');
        },
    });

    if (error instanceof ApiError && error.status === 403) {
        return (
            <PageContainer>
                <Alert type="error">Anda tidak mempunyai kebenaran untuk menghantar pengumuman.</Alert>
            </PageContainer>
        );
    }

    const options = data?.data[jenis] ?? [];

    function addTarget(value: string) {
        const v = value.trim();
        if (!v || targets.some((t) => t.jenis === jenis && t.nilai === v)) return;
        setTargets((ts) => [...ts, { jenis, nilai: v }]);
        setNilai('');
    }

    return (
        <PageContainer>
            <form
                onSubmit={(e: FormEvent) => {
                    e.preventDefault();
                    send.mutate();
                }}
                className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-5"
            >
                <Alert type="info">
                    Jangan masukkan maklumat Sulit atau Rahsia. Pengumuman hanya memuatkan ringkasan; gunakan pautan ke rekod dalam sistem untuk butiran.
                </Alert>

                <FieldInput label="Tajuk" name="tajuk" value={tajuk} onChange={setTajuk} required error={errors.tajuk?.[0]} />
                <Textarea label="Kandungan" name="kandungan" value={kandungan} onChange={setKandungan} rows={5} maxLength={2000} required error={errors.kandungan?.[0]} />
                <FieldInput
                    label="Pautan dalam sistem (pilihan)"
                    name="pautan"
                    value={pautan}
                    onChange={setPautan}
                    placeholder="/admin/…"
                    help="Laluan dalaman sahaja, bermula dengan /"
                    error={errors.pautan?.[0]}
                />

                <fieldset className="space-y-3">
                    <legend className="text-body-sm font-medium text-txt-black-900">
                        Kumpulan penerima <span className="text-danger-600">*</span>
                    </legend>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="sm:w-40">
                            <InputLabel htmlFor="jenis-sasaran" className="mb-1.5">
                                Mengikut
                            </InputLabel>
                            <select id="jenis-sasaran" className={selectClass} value={jenis} onChange={(e) => setJenis(e.target.value as Target['jenis'])}>
                                {(Object.keys(LABEL) as Target['jenis'][]).map((k) => (
                                    <option key={k} value={k}>
                                        {LABEL[k]}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex-1">
                            <InputLabel htmlFor="nilai-sasaran" className="mb-1.5">
                                Kod {LABEL[jenis].toLowerCase()}
                            </InputLabel>
                            <input
                                id="nilai-sasaran"
                                list="pilihan-sasaran"
                                className={selectClass}
                                value={nilai}
                                onChange={(e) => setNilai(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addTarget(nilai);
                                    }
                                }}
                                placeholder="Pilih atau taip kod"
                            />
                            <datalist id="pilihan-sasaran">
                                {options.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </datalist>
                        </div>
                        <Button variant="secondary" onClick={() => addTarget(nilai)} disabled={!nilai.trim()}>
                            Tambah kumpulan
                        </Button>
                    </div>

                    <div className="flex flex-wrap gap-2" aria-live="polite">
                        {targets.length === 0 && <span className="text-body-xs text-txt-black-500">Belum ada kumpulan dipilih.</span>}
                        {targets.map((t) => (
                            <span key={`${t.jenis}:${t.nilai}`} className="inline-flex items-center gap-1">
                                <Badge variant="info">
                                    {LABEL[t.jenis]}: {t.nilai}
                                </Badge>
                                <button
                                    type="button"
                                    className="text-body-xs text-danger-600 hover:underline"
                                    onClick={() => setTargets((ts) => ts.filter((x) => x !== t))}
                                    aria-label={`Buang ${LABEL[t.jenis]} ${t.nilai}`}
                                >
                                    ✕
                                </button>
                            </span>
                        ))}
                    </div>
                    {errors.sasaran && <p className="text-body-xs text-danger-600">{errors.sasaran[0]}</p>}
                </fieldset>

                <div className="flex justify-end">
                    <Button type="submit" disabled={send.isPending || !tajuk || !kandungan || targets.length === 0}>
                        Hantar pengumuman
                    </Button>
                </div>
            </form>
        </PageContainer>
    );
}
