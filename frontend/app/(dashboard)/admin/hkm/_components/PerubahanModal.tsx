'use client';

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { hkmApi, ralat, useRujukan, type HakMilikPenuh, type JenisPerubahan } from '@/lib/hkm';
import { Field, inputClass, ModalBody, Select } from './common';

export interface PerubahanAwal {
    jenis: JenisPerubahan;
    data?: Record<string, string>;
}

const TAJUK: Record<JenisPerubahan, string> = {
    butiran: 'Mohon pindaan butiran hak milik',
    status: 'Mohon perubahan status tanah',
    pemilik: 'Mohon perubahan pemilik berdaftar',
    sekatan: 'Mohon perubahan sekatan kepentingan / bebanan',
    waris: 'Mohon perubahan waris / pemilik bersama',
};

const MEDAN_BUTIRAN: Array<[string, string, string]> = [
    ['no_hak_milik', 'No. hak milik', 'text'],
    ['syarat_nyata', 'Syarat nyata', 'textarea'],
    ['keluasan', 'Keluasan', 'number'],
    ['tarikh_pendaftaran', 'Tarikh pendaftaran', 'date'],
    ['tarikh_tamat_tempoh', 'Tarikh tamat tempoh', 'date'],
    ['pejabat_pendaftaran', 'Pejabat pendaftaran', 'text'],
    ['no_perserahan', 'No. perserahan', 'text'],
    ['tarikh_perserahan', 'Tarikh perserahan', 'date'],
];

/**
 * SRS-HKM-19: every change to a title is submitted for the two-stage approval
 * with MFA in MOD-WFE; it takes effect only after approval.
 */
export function PerubahanModal({ title, awal, onClose }: { title: HakMilikPenuh; awal: PerubahanAwal | null; onClose: () => void }) {
    const { data: ref } = useRujukan();
    const queryClient = useQueryClient();
    const [data, setData] = useState<Record<string, string>>(awal?.data ?? {});
    const [justifikasi, setJustifikasi] = useState('');
    const [noRujukan, setNoRujukan] = useState('');
    const jenis = awal?.jenis ?? 'butiran';

    const set = (k: string) => (v: string) => setData((p) => ({ ...p, [k]: v }));

    const submit = useMutation({
        mutationFn: () => {
            const clean: Record<string, unknown> = {};
            for (const [k, v] of Object.entries(data)) {
                if (v === '') continue;
                clean[k] = ['pecahan_bahagian_pembilang', 'pecahan_bahagian_penyebut', 'id_sekatan', 'id_waris'].includes(k) ? Number(v) : v;
            }
            return hkmApi.mohonPerubahan(title.id, { jenis_perubahan: jenis, data: clean, justifikasi, no_rujukan_dokumen: noRujukan || undefined });
        },
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['hkm'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });

    const input = (k: string, label: string, type = 'text') => (
        <Field key={k} label={label} htmlFor={`p-${k}`}>
            {type === 'textarea' ? (
                <textarea id={`p-${k}`} rows={3} className={inputClass} value={data[k] ?? ''} onChange={(e) => set(k)(e.target.value)} />
            ) : (
                <input id={`p-${k}`} type={type} className={inputClass} value={data[k] ?? ''} onChange={(e) => set(k)(e.target.value)} />
            )}
        </Field>
    );

    const tindakan = data.tindakan ?? '';

    return (
        <Modal open={awal !== null} onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    submit.mutate();
                }}
            >
                <ModalBody
                    title={TAJUK[jenis]}
                    footer={
                        <>
                            <Button variant="outline" onClick={onClose}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submit.isPending || justifikasi.trim().length < 5}>
                                Hantar untuk kelulusan
                            </Button>
                        </>
                    }
                >
                    <Alert type="info">Perubahan ini akan melalui kelulusan dua peringkat dengan pengesahan MFA dan hanya berkuat kuasa selepas diluluskan.</Alert>

                    {jenis === 'butiran' && (
                        <>
                            <Field label="Jenis hak milik" htmlFor="p-jenis">
                                <Select id="p-jenis" value={data.jenis_hak_milik ?? ''} onChange={set('jenis_hak_milik')} options={ref?.jenis_hak_milik ?? []} empty={`Kekal (${title.jenis_hak_milik})`} />
                            </Field>
                            <Field label="Kategori tanah" htmlFor="p-kat">
                                <Select id="p-kat" value={data.kategori_tanah ?? ''} onChange={set('kategori_tanah')} options={ref?.kategori_tanah ?? []} empty="Kekal" />
                            </Field>
                            {MEDAN_BUTIRAN.map(([k, l, t]) => input(k, l, t))}
                            <p className="text-body-xs text-txt-black-500">Isi hanya medan yang hendak dipinda.</p>
                        </>
                    )}

                    {jenis === 'status' && (
                        <>
                            <p className="text-body-sm">
                                Status semasa: <strong>{title.status_semasa_nama}</strong>
                            </p>
                            <Field label="Status baharu" htmlFor="p-status">
                                <Select id="p-status" value={data.status_selepas ?? ''} onChange={set('status_selepas')} options={(ref?.status_tanah ?? []).filter((s) => s.aktif && s.kod !== title.status_semasa)} empty="Pilih…" required />
                            </Field>
                            {input('tarikh_berkuat_kuasa', 'Tarikh berkuat kuasa', 'date')}
                        </>
                    )}

                    {jenis === 'pemilik' && (
                        <>
                            <p className="text-body-sm">
                                Pemilik semasa: <strong>{title.pemilik_berdaftar}</strong>
                            </p>
                            {input('pemilik_baharu', 'Pemilik baharu')}
                            {input('tarikh_pertukaran', 'Tarikh pertukaran', 'date')}
                            {input('sebab_pertukaran', 'Sebab pertukaran (cth. pindah milik, hibah, wakaf, pusaka)', 'textarea')}
                            {input('id_pemerolehan', 'Rujukan kes pemerolehan (jika ada)')}
                        </>
                    )}

                    {jenis === 'sekatan' && (
                        <>
                            <Field label="Tindakan" htmlFor="p-tindakan">
                                <Select
                                    id="p-tindakan"
                                    value={tindakan}
                                    onChange={set('tindakan')}
                                    options={[
                                        { kod: 'tambah', nama: 'Rekod sekatan baharu' },
                                        { kod: 'kemas_kini', nama: 'Kemas kini sekatan' },
                                        { kod: 'lupus', nama: 'Lupus / tarik balik sekatan' },
                                    ]}
                                    empty="Pilih…"
                                    required
                                />
                            </Field>
                            {tindakan && tindakan !== 'tambah' && (
                                <Field label="Sekatan" htmlFor="p-id">
                                    <Select
                                        id="p-id"
                                        value={data.id_sekatan ?? ''}
                                        onChange={set('id_sekatan')}
                                        options={title.sekatan.map((s) => ({ kod: String(s.id), nama: `${s.jenis_sekatan_nama} ${s.no_rujukan_sekatan ?? ''} (${s.status_sekatan_nama})` }))}
                                        empty="Pilih…"
                                        required
                                    />
                                </Field>
                            )}
                            {tindakan !== 'lupus' && (
                                <>
                                    <Field label="Jenis sekatan" htmlFor="p-js">
                                        <Select id="p-js" value={data.jenis_sekatan ?? ''} onChange={set('jenis_sekatan')} options={ref?.jenis_sekatan ?? []} empty={tindakan === 'kemas_kini' ? 'Kekal' : 'Pilih…'} />
                                    </Field>
                                    {input('no_rujukan_sekatan', 'No. rujukan (perserahan / kaveat / gadaian)')}
                                    {input('pihak_terlibat', 'Pihak terlibat')}
                                    {input('tarikh_masuk', 'Tarikh masuk', 'date')}
                                    {input('id_kes_mahkamah', 'Rujukan kes mahkamah (jika ada)')}
                                    {input('keterangan', 'Keterangan', 'textarea')}
                                </>
                            )}
                            {tindakan === 'lupus' && (
                                <>
                                    {input('tarikh_lupus', 'Tarikh lupus', 'date')}
                                    <Field label="Status selepas" htmlFor="p-sl">
                                        <Select
                                            id="p-sl"
                                            value={data.status_sekatan ?? 'dilupuskan'}
                                            onChange={set('status_sekatan')}
                                            options={(ref?.status_sekatan ?? []).filter((s) => s.kod !== 'aktif')}
                                        />
                                    </Field>
                                </>
                            )}
                        </>
                    )}

                    {jenis === 'waris' && (
                        <>
                            <Field label="Tindakan" htmlFor="p-tw">
                                <Select
                                    id="p-tw"
                                    value={tindakan}
                                    onChange={set('tindakan')}
                                    options={[
                                        { kod: 'tambah', nama: 'Tambah waris / pemilik bersama' },
                                        { kod: 'kemas_kini', nama: 'Kemas kini' },
                                        { kod: 'buang', nama: 'Keluarkan' },
                                    ]}
                                    empty="Pilih…"
                                    required
                                />
                            </Field>
                            {tindakan && tindakan !== 'tambah' && (
                                <Field label="Rekod" htmlFor="p-iw">
                                    <Select id="p-iw" value={data.id_waris ?? ''} onChange={set('id_waris')} options={title.waris.map((w) => ({ kod: String(w.id), nama: `${w.nama} (${w.hubungan_nama})` }))} empty="Pilih…" required />
                                </Field>
                            )}
                            {tindakan !== 'buang' && (
                                <>
                                    {input('nama', 'Nama')}
                                    {input('no_pengenalan', 'No. pengenalan')}
                                    <Field label="Hubungan" htmlFor="p-hub">
                                        <Select id="p-hub" value={data.hubungan ?? ''} onChange={set('hubungan')} options={ref?.hubungan ?? []} empty={tindakan === 'kemas_kini' ? 'Kekal' : 'Pilih…'} />
                                    </Field>
                                    <div className="grid grid-cols-2 gap-3">
                                        {input('pecahan_bahagian_pembilang', 'Bahagian (pembilang)', 'number')}
                                        {input('pecahan_bahagian_penyebut', 'Bahagian (penyebut)', 'number')}
                                    </div>
                                    <Field label="Sumber pengesahan" htmlFor="p-sp">
                                        <Select id="p-sp" value={data.sumber_pengesahan ?? ''} onChange={set('sumber_pengesahan')} options={ref?.sumber_pengesahan ?? []} empty="—" />
                                    </Field>
                                    <Field label="Status pengesahan" htmlFor="p-stp">
                                        <Select id="p-stp" value={data.status_pengesahan ?? ''} onChange={set('status_pengesahan')} options={ref?.status_pengesahan_waris ?? []} empty="—" />
                                    </Field>
                                    {input('no_kes_faraid', 'No. kes faraid')}
                                    {input('catatan', 'Catatan', 'textarea')}
                                </>
                            )}
                        </>
                    )}

                    <Field label="Justifikasi (wajib)" htmlFor="p-just">
                        <textarea id="p-just" rows={3} className={inputClass} value={justifikasi} onChange={(e) => setJustifikasi(e.target.value)} required minLength={5} />
                    </Field>
                    <Field label="No. rujukan dokumen sokongan" htmlFor="p-ruj" help="Lampirkan dokumen di tab Dokumen (DMS) dan catat rujukannya di sini.">
                        <input id="p-ruj" className={inputClass} value={noRujukan} onChange={(e) => setNoRujukan(e.target.value)} />
                    </Field>
                </ModalBody>
            </form>
        </Modal>
    );
}
