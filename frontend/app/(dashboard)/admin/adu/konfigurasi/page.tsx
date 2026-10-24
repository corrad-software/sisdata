'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { aduApi, ralat, tarikh, tarikhMasa, useCan, useRujukan, type Konfigurasi } from '@/lib/adu';
import { Card, Field, inputClass, ModalBody, Select } from '../_components/common';

type Sla = Konfigurasi['sla'][number];

const PENERIMA: Record<string, string> = { pegawai: 'Pegawai', penyelia: 'Penyelia', ketua_bahagian: 'Ketua bahagian' };

function SlaModal({ sla, onClose }: { sla: Sla; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [v, setV] = useState({ tempoh_respons: String(sla.tempoh_respons), tempoh_penyelesaian: String(sla.tempoh_penyelesaian), unit_masa: sla.unit_masa, tarikh_berkuat_kuasa: new Date().toISOString().slice(0, 10), laluan: JSON.stringify(sla.laluan_eskalasi, null, 2) });
    const save = useMutation({
        mutationFn: () =>
            aduApi.simpanKonfigurasi(
                'sla',
                {
                    kategori: sla.kategori,
                    subkategori: sla.subkategori,
                    tempoh_respons: Number(v.tempoh_respons),
                    tempoh_penyelesaian: Number(v.tempoh_penyelesaian),
                    unit_masa: v.unit_masa,
                    tarikh_berkuat_kuasa: v.tarikh_berkuat_kuasa,
                    laluan_eskalasi: JSON.parse(v.laluan) as unknown,
                },
                sla.id,
            ),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['adu', 'konfigurasi'] });
            onClose();
        },
        onError: (e) => toast.error(e instanceof SyntaxError ? 'Laluan eskalasi bukan JSON yang sah.' : ralat(e)),
    });
    return (
        <Modal open onClose={onClose}>
            <ModalBody
                title={`SLA — ${sla.kategori_nama ?? sla.kategori}`}
                footer={
                    <>
                        <Button variant="outline" onClick={onClose}>
                            Batal
                        </Button>
                        <Button onClick={() => save.mutate()} disabled={save.isPending}>
                            Simpan versi baharu
                        </Button>
                    </>
                }
            >
                <p className="text-body-xs text-txt-black-500">SLA yang telah berkuat kuasa tidak diubah; perubahan disimpan sebagai versi baharu mengikut tarikh berkuat kuasa. Aduan sedia ada kekal dengan SLA asalnya.</p>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Tempoh respons" htmlFor="sla-r">
                        <input id="sla-r" type="number" min={1} className={inputClass} value={v.tempoh_respons} onChange={(e) => setV((x) => ({ ...x, tempoh_respons: e.target.value }))} />
                    </Field>
                    <Field label="Tempoh penyelesaian" htmlFor="sla-s">
                        <input id="sla-s" type="number" min={1} className={inputClass} value={v.tempoh_penyelesaian} onChange={(e) => setV((x) => ({ ...x, tempoh_penyelesaian: e.target.value }))} />
                    </Field>
                    <Field label="Unit" htmlFor="sla-u">
                        <Select
                            id="sla-u"
                            value={v.unit_masa}
                            onChange={(u) => setV((x) => ({ ...x, unit_masa: u }))}
                            options={[
                                { kod: 'hari_bekerja', nama: 'Hari bekerja' },
                                { kod: 'jam_bekerja', nama: 'Jam bekerja' },
                            ]}
                        />
                    </Field>
                    <Field label="Berkuat kuasa" htmlFor="sla-t">
                        <input id="sla-t" type="date" className={inputClass} value={v.tarikh_berkuat_kuasa} onChange={(e) => setV((x) => ({ ...x, tarikh_berkuat_kuasa: e.target.value }))} />
                    </Field>
                </div>
                <Field label="Laluan eskalasi (JSON)" htmlFor="sla-l" help='Setiap peringkat: {"peringkat":1,"sasaran":"respons|selesai","peratus":100,"penerima":["pegawai","penyelia","ketua_bahagian","peranan:<kod>"]}'>
                    <textarea id="sla-l" rows={8} className={`${inputClass} font-mono text-body-xs`} value={v.laluan} onChange={(e) => setV((x) => ({ ...x, laluan: e.target.value }))} />
                </Field>
            </ModalBody>
        </Modal>
    );
}

export default function KonfigurasiPage() {
    const can = useCan();
    const queryClient = useQueryClient();
    const { data: ref } = useRujukan();
    const { data, error } = useQuery({ queryKey: ['adu', 'konfigurasi'], queryFn: () => aduApi.konfigurasi().then((r) => r.data) });
    const outbox = useQuery({ queryKey: ['adu', 'konfigurasi', 'outbox'], queryFn: () => aduApi.outbox(), enabled: can('adu.konfigurasi.kemas_kini') });
    const [sla, setSla] = useState<Sla | null>(null);
    const [sub, setSub] = useState({ kod_kategori: '', kod: '', nama: '' });
    const [tugas, setTugas] = useState({ kategori: '', kod_daerah: '', kod_peranan: '', keutamaan: '100' });
    const boleh = can('adu.konfigurasi.kemas_kini');
    const done = (r: { message: string }) => {
        toast.success(r.message);
        queryClient.invalidateQueries({ queryKey: ['adu'] });
    };
    const simpanSub = useMutation({ mutationFn: () => aduApi.simpanKonfigurasi('subkategori', sub), onSuccess: (r) => (done(r), setSub({ kod_kategori: '', kod: '', nama: '' })), onError: (e) => toast.error(ralat(e)) });
    const simpanTugas = useMutation({
        mutationFn: () => aduApi.simpanKonfigurasi('penugasan', { kategori: tugas.kategori || null, kod_daerah: tugas.kod_daerah || null, kod_peranan: tugas.kod_peranan, keutamaan: Number(tugas.keutamaan) }),
        onSuccess: done,
        onError: (e) => toast.error(ralat(e)),
    });
    const importPusat = useMutation({ mutationFn: () => aduApi.importPusat(), onSuccess: (r) => done({ message: `${r.message} Baharu: ${r.data.diterima}, sedia ada: ${r.data.sedia_ada}, gagal: ${r.data.gagal}.` }), onError: (e) => toast.error(ralat(e)) });
    const ulang = useMutation({ mutationFn: () => aduApi.ulangCuba(), onSuccess: (r) => done({ message: `${r.message} Berjaya: ${r.data.berjaya}, gagal: ${r.data.gagal}.` }), onError: (e) => toast.error(ralat(e)) });
    const namaKat = (k: string | null) => (k ? (ref?.kategori.find((c) => c.kod === k)?.nama ?? k) : 'Semua');

    return (
        <PageContainer>
            {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
            <Card title="SLA dan laluan eskalasi mengikut kategori">
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Kategori</th>
                                <th>Respons / selesai</th>
                                <th className="hidden md:table-cell">Eskalasi</th>
                                <th className="hidden sm:table-cell">Berkuat kuasa</th>
                                <th />
                            </>
                        }
                    >
                        {(data?.sla ?? []).map((s) => (
                            <tr key={s.id}>
                                <td>
                                    {s.kategori_nama}
                                    {s.subkategori && <span className="text-txt-black-500"> › {s.subkategori}</span>}
                                </td>
                                <td className="whitespace-nowrap">
                                    {s.tempoh_respons} / {s.tempoh_penyelesaian} {s.unit_masa === 'jam_bekerja' ? 'jam' : 'hari'} bekerja
                                </td>
                                <td className="hidden md:table-cell text-body-xs">
                                    {s.laluan_eskalasi.map((e) => (
                                        <div key={e.peringkat}>
                                            P{e.peringkat}: {e.peratus}% {e.sasaran} → {e.penerima.map((p) => PENERIMA[p] ?? p).join(', ')}
                                        </div>
                                    ))}
                                </td>
                                <td className="hidden sm:table-cell">{tarikh(s.tarikh_berkuat_kuasa)}</td>
                                <td className="text-right">
                                    {boleh && (
                                        <Button size="sm" variant="outline" onClick={() => setSla(s)}>
                                            Ubah
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </div>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card title="Subkategori aduan">
                    <ul className="text-body-sm space-y-1 max-h-64 overflow-y-auto">
                        {(data?.subkategori ?? []).map((s) => (
                            <li key={s.id}>
                                <span className="text-txt-black-500">{namaKat(s.kod_kategori)} ›</span> {s.nama} {!s.aktif && <Badge>Tidak aktif</Badge>}
                            </li>
                        ))}
                    </ul>
                    {boleh && (
                        <form
                            className="grid grid-cols-1 sm:grid-cols-3 gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                simpanSub.mutate();
                            }}
                        >
                            <Select id="sk-k" value={sub.kod_kategori} onChange={(v) => setSub((x) => ({ ...x, kod_kategori: v }))} options={ref?.kategori ?? []} empty="Kategori" required />
                            <input aria-label="Kod" placeholder="KOD" className={inputClass} value={sub.kod} onChange={(e) => setSub((x) => ({ ...x, kod: e.target.value.toUpperCase() }))} required />
                            <input aria-label="Nama" placeholder="Nama" className={inputClass} value={sub.nama} onChange={(e) => setSub((x) => ({ ...x, nama: e.target.value }))} required />
                            <Button type="submit" size="sm" className="sm:col-span-3" disabled={simpanSub.isPending}>
                                Tambah subkategori
                            </Button>
                        </form>
                    )}
                </Card>

                <Card title="Peraturan triaj">
                    <ul className="text-body-sm space-y-2">
                        {(data?.triaj ?? []).map((t) => (
                            <li key={t.id} className="break-words">
                                {t.jenis_peraturan === 'kata_kunci' ? (
                                    <>
                                        <span className="font-medium">{namaKat(t.kategori_cadangan)}</span>
                                        {t.keutamaan_cadangan && <span className="text-txt-black-500"> ({t.keutamaan_cadangan})</span>}: {(t.kata_kunci ?? []).join(', ')}
                                    </>
                                ) : (
                                    <>
                                        <span className="font-medium">Semakan aduan berulang</span>: {t.tempoh_semakan_berulang} hari, jejari {t.jejari_meter ?? 0} m
                                    </>
                                )}
                                {!t.status_aktif && <Badge>Tidak aktif</Badge>}
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <Card title="Peraturan penugasan automatik">
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Kategori</th>
                                <th>Daerah</th>
                                <th>Kepada</th>
                                <th>Keutamaan</th>
                            </>
                        }
                    >
                        {(data?.penugasan ?? []).map((p) => (
                            <tr key={p.id}>
                                <td>{namaKat(p.kategori)}</td>
                                <td>{p.kod_daerah ? (ref?.daerah.find((d) => d.kod === p.kod_daerah)?.nama ?? p.kod_daerah) : 'Semua'}</td>
                                <td>{p.pegawai?.name ?? (p.kod_peranan ? `Peranan: ${p.kod_peranan}` : '—')}</td>
                                <td>{p.keutamaan}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
                {boleh && (
                    <form
                        className="grid grid-cols-1 sm:grid-cols-4 gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            simpanTugas.mutate();
                        }}
                    >
                        <Select id="pt-k" value={tugas.kategori} onChange={(v) => setTugas((x) => ({ ...x, kategori: v }))} options={ref?.kategori ?? []} empty="Semua kategori" />
                        <Select id="pt-d" value={tugas.kod_daerah} onChange={(v) => setTugas((x) => ({ ...x, kod_daerah: v }))} options={ref?.daerah ?? []} empty="Semua daerah" />
                        <input aria-label="Kod peranan" placeholder="Kod peranan, cth. pegawai_operasi" className={inputClass} value={tugas.kod_peranan} onChange={(e) => setTugas((x) => ({ ...x, kod_peranan: e.target.value }))} required />
                        <input aria-label="Keutamaan" type="number" min={0} className={inputClass} value={tugas.keutamaan} onChange={(e) => setTugas((x) => ({ ...x, keutamaan: e.target.value }))} />
                        <Button type="submit" size="sm" className="sm:col-span-4" disabled={simpanTugas.isPending}>
                            Tambah peraturan (pegawai dalam skop dengan beban paling rendah dipilih)
                        </Button>
                    </form>
                )}
            </Card>

            {boleh && (
                <Card
                    title="Sistem Aduan berpusat MAIS"
                    actions={
                        <>
                            <Button size="sm" variant="outline" onClick={() => importPusat.mutate()} disabled={importPusat.isPending}>
                                Import aduan baharu
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => ulang.mutate()} disabled={ulang.isPending}>
                                Ulang cuba penyaluran status
                            </Button>
                        </>
                    }
                >
                    <p className="text-body-xs text-txt-black-500">Adapter semasa: tiruan (mock) — akses sebenar belum diberikan. Status aduan yang berasal dari sistem berpusat disalurkan semula secara automatik.</p>
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>ID luaran</th>
                                    <th>Status aduan</th>
                                    <th>Penyaluran</th>
                                    <th className="hidden sm:table-cell">Masa</th>
                                </>
                            }
                        >
                            {(outbox.data?.data ?? []).map((o) => (
                                <tr key={o.id}>
                                    <td>{o.id_aduan_sistem_luaran}</td>
                                    <td>{o.status_aduan}</td>
                                    <td>
                                        <Badge variant={o.status === 'berjaya' ? 'success' : o.status === 'gagal' ? 'danger' : 'warning'}>{o.status}</Badge> <span className="text-body-xs text-txt-black-500">×{o.cubaan}</span>
                                        {o.ralat_terakhir && <p className="text-body-xs text-txt-danger break-words">{o.ralat_terakhir}</p>}
                                    </td>
                                    <td className="hidden sm:table-cell">{tarikhMasa(o.dihantar_pada ?? o.dicipta)}</td>
                                </tr>
                            ))}
                        </Table>
                    </div>
                </Card>
            )}

            {sla && <SlaModal sla={sla} onClose={() => setSla(null)} />}
        </PageContainer>
    );
}
