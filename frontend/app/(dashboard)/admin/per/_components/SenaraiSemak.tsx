'use client';

import { useState } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { InputLabel } from '@/components/ui/InputLabel';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { perApi, PERANAN_DOKUMEN_ASAL, tarikh, type DokumenKes, type ItemSemak, type KesPenuh } from '@/lib/per';
import { ErrorList, inputClass } from './common';

/** SRS-PER-16/22: mandatory checklist per channel, completeness, upload to DMS and physical location. */
export function SenaraiSemak({ kes, onChanged }: { kes: KesPenuh; onChanged: () => void }) {
    const [item, setItem] = useState<ItemSemak | null>(null);
    const docs = new Map(kes.dokumen.map((d) => [d.id, d]));
    const stageName = (kod: string) => (kod === 'tutup' ? 'Tutup kes' : (kes.peringkat.find((p) => p.kod === kod)?.nama ?? kod));
    const terbuka = kes.status !== 'selesai' && kes.status !== 'dibatalkan';

    return (
        <div className="space-y-4">
            {kes.kekurangan_dokumen.length > 0 ? (
                <Alert type="warning">
                    <p className="font-medium">Dokumen wajib belum lengkap ({kes.kekurangan_dokumen.length}):</p>
                    <ErrorList items={kes.kekurangan_dokumen} />
                </Alert>
            ) : (
                <Alert type="success">Semua dokumen wajib saluran ini telah lengkap.</Alert>
            )}

            <ul className="divide-y divide-otl-divider rounded-lg border border-otl-gray-200">
                {kes.senarai_semak.map((i) => (
                    <li key={i.id} className="p-3 sm:p-4 space-y-2">
                        <div className="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-4">
                            <div className="min-w-0 flex-1">
                                <p className="text-body-sm font-medium text-txt-black-900 break-words">{i.nama_dokumen}</p>
                                <p className="text-body-xs text-txt-black-500">
                                    {i.wajib ? 'Wajib' : 'Pilihan'} · diperlukan sebelum: {stageName(i.peringkat_diperlukan)}
                                </p>
                            </div>
                            <div className="flex items-center gap-2 flex-shrink-0">
                                {i.dijana_sistem ? (
                                    <Badge variant={i.dipenuhi ? 'success' : 'neutral'}>{i.dipenuhi ? 'Direkod di peti besi' : 'Dijana semasa tutup kes'}</Badge>
                                ) : (
                                    <Badge variant={i.dipenuhi ? 'success' : 'danger'}>{i.dipenuhi ? 'Lengkap' : 'Belum diterima'}</Badge>
                                )}
                                {!i.dijana_sistem && kes.boleh.dokumen && terbuka && (
                                    <Button size="sm" variant="secondary" onClick={() => setItem(i)}>
                                        Muat naik
                                    </Button>
                                )}
                            </div>
                        </div>
                        {i.dokumen.map((id) => docs.get(id)).filter(Boolean).map((d) => (
                            <DokumenBaris key={d!.id} kes={kes} dok={d!} onChanged={onChanged} />
                        ))}
                    </li>
                ))}
            </ul>

            <UploadModal kes={kes} item={item} onClose={() => setItem(null)} onDone={onChanged} />
        </div>
    );
}

function DokumenBaris({ kes, dok, onChanged }: { kes: KesPenuh; dok: DokumenKes; onChanged: () => void }) {
    const [busy, setBusy] = useState(false);
    async function move(jenis: 'keluar' | 'pulang') {
        const tujuan = jenis === 'keluar' ? window.prompt('Tujuan pengeluaran dokumen asal:') ?? '' : undefined;
        if (jenis === 'keluar' && !tujuan) return;
        setBusy(true);
        try {
            await perApi.pergerakan(kes.id, dok.id, jenis, tujuan);
            toast.success(jenis === 'keluar' ? 'Pengeluaran dokumen direkodkan.' : 'Pemulangan dokumen direkodkan.');
            onChanged();
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Gagal.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="ml-0 sm:ml-4 rounded-md bg-bg-washed p-2 text-body-xs text-txt-black-700 space-y-1">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                {dok.rujukan_dms ? (
                    <Link href={`/admin/dms/${dok.rujukan_dms}`} className="text-txt-primary hover:underline">
                        Lihat dalam DMS
                    </Link>
                ) : (
                    <span>Tiada salinan digital</span>
                )}
                <span>Diterima {tarikh(dok.tarikh_terima)}{dok.penerima ? ` oleh ${dok.penerima.nama}` : ''}</span>
                <span>
                    Lokasi asal: {[dok.kod_peti_besi, dok.no_fail, dok.lokasi_rak].filter(Boolean).join(' / ') || '—'}
                </span>
                {dok.status_dokumen_asal && <Badge variant={dok.status_dokumen_asal === 'dikeluarkan' ? 'warning' : 'neutral'}>{PERANAN_DOKUMEN_ASAL[dok.status_dokumen_asal] ?? dok.status_dokumen_asal}</Badge>}
                {kes.boleh.dokumen && dok.status_dokumen_asal && dok.status_dokumen_asal !== 'tiada' && (
                    <button type="button" disabled={busy} className="text-txt-primary hover:underline disabled:opacity-50" onClick={() => move(dok.status_dokumen_asal === 'dikeluarkan' ? 'pulang' : 'keluar')}>
                        {dok.status_dokumen_asal === 'dikeluarkan' ? 'Rekod pemulangan' : 'Rekod pengeluaran'}
                    </button>
                )}
            </div>
            {dok.pergerakan.length > 0 && (
                <ul className="list-disc pl-4">
                    {dok.pergerakan.map((m, idx) => (
                        <li key={idx}>
                            {m.jenis === 'keluar' ? 'Dikeluarkan' : 'Dipulangkan'} {tarikh(m.tarikh)}
                            {m.pegawai ? ` · ${m.pegawai.nama}` : ''}
                            {m.tujuan ? ` · ${m.tujuan}` : ''}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function UploadModal({ kes, item, onClose, onDone }: { kes: KesPenuh; item: ItemSemak | null; onClose: () => void; onDone: () => void }) {
    const [file, setFile] = useState<File | null>(null);
    const [meta, setMeta] = useState({ tarikh_dokumen: '', kod_peti_besi: '', no_fail: '', lokasi_rak: '', status_dokumen_asal: 'asal_dalam_simpanan' });
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    async function submit() {
        if (!item || !file) return;
        setBusy(true);
        setError(null);
        const form = new FormData();
        form.append('fail', file);
        form.append('id_semak', String(item.id));
        for (const [k, v] of Object.entries(meta)) if (v) form.append(k, v);
        try {
            await perApi.muatNaik(kes.id, form);
            toast.success('Dokumen dimuat naik ke DMS dan direkodkan.');
            setFile(null);
            onDone();
            onClose();
        } catch (e) {
            setError(e instanceof ApiError ? (Object.values(e.errors ?? {})[0]?.[0] ?? e.message) : 'Muat naik gagal.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={item !== null} onClose={onClose}>
            <div className="p-4 sm:p-6 space-y-4">
                <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Muat naik: {item?.nama_dokumen}</h3>
                {error && <Alert type="error">{error}</Alert>}
                <div>
                    <InputLabel htmlFor="per-fail" className="mb-1.5">
                        Fail (disimpan dalam DMS sebagai Sulit)
                    </InputLabel>
                    <input id="per-fail" type="file" className="block w-full text-body-sm" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="per-tarikh" className="mb-1.5">
                            Tarikh dokumen
                        </InputLabel>
                        <input id="per-tarikh" type="date" className={inputClass} value={meta.tarikh_dokumen} onChange={(e) => setMeta({ ...meta, tarikh_dokumen: e.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="per-asal" className="mb-1.5">
                            Status dokumen asal
                        </InputLabel>
                        <select id="per-asal" className={inputClass} value={meta.status_dokumen_asal} onChange={(e) => setMeta({ ...meta, status_dokumen_asal: e.target.value })}>
                            {Object.entries(PERANAN_DOKUMEN_ASAL).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </div>
                    {(
                        [
                            ['kod_peti_besi', 'Kod peti besi'],
                            ['no_fail', 'No. fail'],
                            ['lokasi_rak', 'Lokasi rak'],
                        ] as const
                    ).map(([k, label]) => (
                        <div key={k}>
                            <InputLabel htmlFor={`per-${k}`} className="mb-1.5">
                                {label}
                            </InputLabel>
                            <input id={`per-${k}`} className={inputClass} value={meta[k]} onChange={(e) => setMeta({ ...meta, [k]: e.target.value })} />
                        </div>
                    ))}
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={submit} disabled={!file || busy}>
                        {busy ? 'Memuat naik…' : 'Muat naik'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
