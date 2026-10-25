'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Modal } from '@/components/ui/Modal';
import { KEKERAPAN_LABEL, lapApi, ralat, tarikhMasa, type Jadual } from '@/lib/lap';
import { Card, Field, inputClass, Kosong } from '../_components/common';

/** SRS-LAP-06 scheduled report distribution through MOD-NTF (e-mel / dalam sistem). */
export default function JadualPage() {
    const qc = useQueryClient();
    const [borang, setBorang] = useState<Jadual | 'baharu' | null>(null);
    const q = useQuery({ queryKey: ['lap', 'jadual'], queryFn: () => lapApi.jadual().then((r) => r.data) });
    const padam = useMutation({ mutationFn: (id: number) => lapApi.padamJadual(id), onSuccess: () => qc.invalidateQueries({ queryKey: ['lap', 'jadual'] }), onError: (e) => toast.error(ralat(e)) });
    const jalan = useMutation({
        mutationFn: (id: number) => lapApi.jalanJadual(id).then((r) => r.data),
        onSuccess: (r) => {
            toast.success(`Dihantar: ${r.dihantar}, ditolak: ${r.ditolak}, gagal: ${r.gagal}`);
            qc.invalidateQueries({ queryKey: ['lap', 'jadual'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <PageContainer>
            <Card title="Jadual penjanaan laporan" actions={<Button onClick={() => setBorang('baharu')}>+ Jadual baharu</Button>}>
                <p className="text-body-sm text-txt-black-500">
                    Laporan dijana automatik mengikut jadual. Setiap penerima menerima salinan mengikut kebenaran dan skop capaian <strong>mereka sendiri</strong>; e-mel hanya membawa pautan, bukan kandungan laporan.
                </p>
                {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}
                {q.data && q.data.length === 0 && <Kosong>Tiada jadual.</Kosong>}
                {q.data && q.data.length > 0 && (
                    <Table head={<><th>Nama</th><th>Laporan</th><th>Kekerapan</th><th>Format</th><th>Penerima</th><th>Seterusnya</th><th>Terakhir</th><th>Tindakan</th></>}>
                        {q.data.map((j) => (
                            <tr key={j.id}>
                                <td className="font-medium">
                                    {j.nama} {!j.status_aktif && <Badge variant="neutral">Tidak aktif</Badge>}
                                </td>
                                <td className="font-mono text-body-xs">{j.kod_laporan ?? 'Definisi tersimpan'}</td>
                                <td>
                                    {j.kekerapan_nama} {j.masa_jalan}
                                </td>
                                <td>{j.format.toUpperCase()}</td>
                                <td>{j.senarai_penerima.map((p) => p.nama).join(', ')}</td>
                                <td className="whitespace-nowrap">{tarikhMasa(j.jalan_berikutnya)}</td>
                                <td className="whitespace-nowrap">
                                    {tarikhMasa(j.jalan_terakhir)} {j.status_terakhir && <Badge variant={j.status_terakhir === 'berjaya' ? 'success' : j.status_terakhir === 'separa' ? 'warning' : 'danger'}>{j.status_terakhir}</Badge>}
                                </td>
                                <td className="whitespace-nowrap space-x-2">
                                    <Button size="sm" variant="outline" onClick={() => setBorang(j)}>
                                        Ubah
                                    </Button>
                                    <Button size="sm" variant="secondary" onClick={() => jalan.mutate(j.id)} disabled={jalan.isPending}>
                                        Jalan sekarang
                                    </Button>
                                    <Button size="sm" variant="danger" onClick={() => confirm('Padam jadual ini?') && padam.mutate(j.id)}>
                                        Padam
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            {borang && <BorangJadual jadual={borang === 'baharu' ? null : borang} onClose={() => setBorang(null)} />}
        </PageContainer>
    );
}

function BorangJadual({ jadual, onClose }: { jadual: Jadual | null; onClose: () => void }) {
    const qc = useQueryClient();
    const katalog = useQuery({ queryKey: ['lap', 'katalog'], queryFn: () => lapApi.katalog() });
    const definisi = useQuery({ queryKey: ['lap', 'definisi'], queryFn: () => lapApi.definisi().then((r) => r.data) });
    const [nama, setNama] = useState(jadual?.nama ?? '');
    const [sumber, setSumber] = useState(jadual?.id_definisi ? `d:${jadual.id_definisi}` : jadual?.kod_laporan ? `k:${jadual.kod_laporan}` : '');
    const [kekerapan, setKekerapan] = useState(jadual?.kekerapan ?? 'bulanan');
    const [masa, setMasa] = useState(jadual?.masa_jalan ?? '07:00');
    const [hari, setHari] = useState(String(jadual?.hari_jalan ?? 1));
    const [format, setFormat] = useState(jadual?.format ?? 'pdf');
    const [penerima, setPenerima] = useState(jadual?.senarai_penerima ?? []);
    const [aktif, setAktif] = useState(jadual?.status_aktif ?? true);
    const [cari, setCari] = useState('');
    const calon = useQuery({ queryKey: ['lap', 'penerima', cari], queryFn: () => lapApi.penerima(cari).then((r) => r.data), enabled: cari.length >= 2 });

    const simpan = useMutation({
        mutationFn: () =>
            lapApi.simpanJadual(
                {
                    nama, kekerapan, masa_jalan: masa, hari_jalan: kekerapan === 'harian' ? null : Number(hari), format, status_aktif: aktif,
                    senarai_penerima: penerima.map((p) => p.id),
                    id_definisi: sumber.startsWith('d:') ? sumber.slice(2) : null,
                    kod_laporan: sumber.startsWith('k:') ? sumber.slice(2) : null,
                },
                jadual?.id,
            ),
        onSuccess: () => {
            toast.success('Jadual disimpan.');
            qc.invalidateQueries({ queryKey: ['lap', 'jadual'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-4">
                <h3 className="font-heading font-semibold text-body-lg">{jadual ? 'Ubah jadual' : 'Jadual baharu'}</h3>
                <Field label="Nama" htmlFor="jnama">
                    <input id="jnama" className={inputClass} value={nama} onChange={(e) => setNama(e.target.value)} />
                </Field>
                <Field label="Laporan" htmlFor="jsumber">
                    <select id="jsumber" className={inputClass} value={sumber} onChange={(e) => setSumber(e.target.value)}>
                        <option value="">Pilih laporan…</option>
                        <optgroup label="Laporan standard">
                            {(katalog.data?.data ?? []).filter((k) => k.jenis !== 'modul' && k.boleh_buka).map((k) => (
                                <option key={k.kod} value={`k:${k.kod}`}>
                                    {k.kod} — {k.nama}
                                </option>
                            ))}
                        </optgroup>
                        <optgroup label="Laporan tersimpan">
                            {(definisi.data ?? []).map((d) => (
                                <option key={d.id} value={`d:${d.id}`}>
                                    {d.nama}
                                </option>
                            ))}
                        </optgroup>
                    </select>
                </Field>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <Field label="Kekerapan" htmlFor="jkek">
                        <select id="jkek" className={inputClass} value={kekerapan} onChange={(e) => setKekerapan(e.target.value as Jadual['kekerapan'])}>
                            {Object.entries(KEKERAPAN_LABEL).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Masa" htmlFor="jmasa">
                        <input id="jmasa" type="time" className={inputClass} value={masa} onChange={(e) => setMasa(e.target.value)} />
                    </Field>
                    {kekerapan !== 'harian' && (
                        <Field label={kekerapan === 'mingguan' ? 'Hari (1=Isnin)' : 'Hari bulan'} htmlFor="jhari">
                            <input id="jhari" type="number" min={1} max={kekerapan === 'mingguan' ? 7 : 28} className={inputClass} value={hari} onChange={(e) => setHari(e.target.value)} />
                        </Field>
                    )}
                    <Field label="Format" htmlFor="jfmt">
                        <select id="jfmt" className={inputClass} value={format} onChange={(e) => setFormat(e.target.value as Jadual['format'])}>
                            <option value="pdf">PDF</option>
                            <option value="xlsx">Excel</option>
                            <option value="csv">CSV</option>
                        </select>
                    </Field>
                </div>
                <Field label="Penerima (pengguna SISDATA)" htmlFor="jcari" help="Taip sekurang-kurangnya 2 aksara nama atau e-mel.">
                    <input id="jcari" className={inputClass} value={cari} onChange={(e) => setCari(e.target.value)} />
                </Field>
                {calon.data && cari.length >= 2 && (
                    <ul className="max-h-40 overflow-y-auto border border-otl-gray-200 rounded-md divide-y divide-otl-gray-200">
                        {calon.data.map((u) => (
                            <li key={u.id}>
                                <button type="button" className="w-full text-left px-3 py-2 text-body-sm hover:bg-bg-washed" onClick={() => !penerima.some((p) => p.id === u.id) && setPenerima([...penerima, { id: u.id, nama: u.name }])}>
                                    {u.name} <span className="text-txt-black-500">({u.email})</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="flex flex-wrap gap-2">
                    {penerima.map((p) => (
                        <button key={p.id} type="button" onClick={() => setPenerima(penerima.filter((x) => x.id !== p.id))} className="rounded-full bg-bg-washed border border-otl-gray-200 px-3 py-1 text-body-xs" aria-label={`Buang ${p.nama}`}>
                            {p.nama} ✕
                        </button>
                    ))}
                </div>
                <label className="flex items-center gap-2 text-body-sm">
                    <input type="checkbox" checked={aktif} onChange={(e) => setAktif(e.target.checked)} /> Aktif
                </label>
                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={() => simpan.mutate()} disabled={!nama || !sumber || penerima.length === 0 || simpan.isPending}>
                        Simpan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
