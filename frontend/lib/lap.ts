/*
 * MOD-LAP frontend helpers — Laporan dan Analitik (/api/v1/lap). Every call is
 * authorised and row-scoped by the API itself; hiding here is UI only.
 */
import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

// ---------------------------------------------------------------- types

export type JenisMedan = 'teks' | 'kod' | 'nombor' | 'wang' | 'tarikh' | 'bool' | 'tempoh' | 'bilangan' | 'peratus';

export interface Katalog {
    kod: string;
    nama: string;
    kategori: string;
    kategori_nama: string;
    jenis: 'lap' | 'modul' | 'komposit';
    keterangan: string | null;
    definisi_metrik: string | null;
    sumber_data: string | null;
    pemilik: string | null;
    kebenaran: string | null;
    peranan_dibenarkan: string[];
    set_data: string | null;
    definisi: Definisi | null;
    pautan: string | null;
    bahagian: string[];
    status_pengesahan: 'draf' | 'disahkan';
    disahkan_pada: string | null;
    aktif: boolean;
    boleh_buka: boolean;
}

export interface Penapis {
    medan: string;
    op: string;
    nilai?: string | string[] | null;
}

export interface Agregat {
    fungsi: string;
    medan?: string;
}

export interface Definisi {
    set: string;
    medan?: string[];
    kumpulan?: string[];
    hierarki?: string[];
    agregat?: Agregat[];
    penapis?: Penapis[];
    tempoh?: { medan?: string; dari?: string; hingga?: string; pratetap?: string } | null;
    susunan?: { medan: string; arah: 'asc' | 'desc' }[];
    bandingan?: 'tahun_lepas' | null;
    carta?: 'bar' | 'garis' | 'pai' | 'jadual';
    had?: number;
}

export interface Lajur {
    kunci: string;
    label: string;
    jenis: JenisMedan;
    kumpulan?: boolean;
}

export type Baris = Record<string, unknown> & {
    _drill?: Record<string, string | null>;
    _label?: Record<string, string | null>;
    _pautan?: string | null;
    _id?: string;
};

export interface Hasil {
    mod: 'kumpulan' | 'rekod';
    set: string;
    lajur: Lajur[];
    baris: Baris[];
    jumlah_baris: number;
    bilangan_rekod: number;
    jumlah_keseluruhan: Record<string, number | string> | null;
    kumpulan: string[];
    aras_drill: number;
    drill: Record<string, string | null>;
    boleh_drill: boolean;
    dari_cache: boolean;
    meta: {
        data_dikemas_kini: string | null;
        data_setakat: string | null;
        dijana_pada: string;
        tempoh: { medan: string; dari: string; hingga: string; pratetap: string | null } | null;
        bandingan: string | null;
        penapis_teks: string[];
        skop: Record<string, string[] | null>;
        tahap_klasifikasi: string[];
        klasifikasi_tertinggi: string;
        medan_disembunyikan: string[];
        replika: boolean;
        tempoh_ms: number;
        dipotong: boolean;
    };
}

export interface Laporan {
    tajuk: string;
    kod_laporan: string | null;
    id_definisi: string | null;
    komposit: boolean;
    bahagian: { kod: string | null; tajuk: string; definisi: Definisi; hasil: Hasil }[];
    id_log: string;
    label_cadangan: string | null;
}

export type Sumber = { katalog: string } | { definisi: string } | { adhoc: Definisi; tajuk?: string };

export interface Parameter {
    tempoh?: { dari?: string; hingga?: string; pratetap?: string };
    kod_daerah?: string;
    penapis?: Penapis[];
}

export interface SetData {
    kunci: string;
    label: string;
    kategori: string;
    modul: string;
    tarikh: string | null;
    medan: { kunci: string; label: string; jenis: JenisMedan }[];
}

export interface DefinisiTersimpan {
    id: string;
    nama: string;
    keterangan: string | null;
    kod_laporan: string | null;
    set_data: string;
    set_label: string;
    pertanyaan: Definisi;
    parameter_lalai: Parameter | null;
    status_kongsi: 'peribadi' | 'peranan' | 'semua';
    kongsi_peranan: string[];
    sumber: string;
    pencipta: string | null;
    milik_saya: boolean;
    dikemas_kini: string | null;
}

export interface Jadual {
    id: number;
    nama: string;
    id_definisi: string | null;
    kod_laporan: string | null;
    kekerapan: 'harian' | 'mingguan' | 'bulanan' | 'suku_tahun';
    kekerapan_nama: string;
    masa_jalan: string;
    hari_jalan: number | null;
    format: 'pdf' | 'xlsx' | 'csv';
    parameter: Parameter | null;
    senarai_penerima: { id: number; nama: string }[];
    status_aktif: boolean;
    jalan_berikutnya: string | null;
    jalan_terakhir: string | null;
    status_terakhir: string | null;
}

export interface LogPenjanaan {
    id: string;
    tajuk: string;
    kod_laporan: string | null;
    format: string;
    mod: string;
    mod_nama: string;
    status: string;
    status_nama: string;
    bilangan_rekod: number;
    cap_masa: string;
    tamat_pada: string | null;
    saiz_bait: number | null;
    checksum: string | null;
    klasifikasi: string;
    pengguna: string | null;
    milik_saya: boolean;
    boleh_muat_turun: boolean;
    ralat: string | null;
}

export interface HasilAnalitik {
    id: number;
    jenis: string;
    jenis_nama: string;
    id_lot: string;
    no_lot: string | null;
    kod_daerah: string | null;
    skor: string;
    aras: 'rendah' | 'sederhana' | 'tinggi' | 'kritikal';
    faktor: Record<string, unknown>;
    tarikh_kira: string;
    versi_model: string;
    pautan: string;
}

export interface Cadangan {
    id: number;
    id_lot: string;
    no_lot: string | null;
    kod_daerah: string | null;
    jenis: string;
    jenis_nama: string;
    tindakan: string;
    tindakan_nama: string;
    asas: string;
    status: 'baharu' | 'diterima' | 'ditolak';
    justifikasi: string | null;
    pegawai: string | null;
    tarikh_keputusan: string | null;
    versi_model: string;
    pautan: string;
    dicipta: string | null;
}

export interface Insight {
    id: number;
    tempoh: string;
    skop: Record<string, string[] | null> | null;
    negeri: boolean;
    isu_utama: { tahap: string; teks: string; pautan?: string }[];
    risiko: { tahap: string; teks: string; pautan?: string }[];
    tindakan_segera: { teks: string; pautan?: string }[];
    statistik: Record<string, unknown> | null;
    sumber: string;
    tarikh_jana: string;
}

export interface PapanBi {
    id: number;
    kod: string;
    nama: string;
    keterangan: string | null;
    jenis: 'dashboard' | 'question';
    id_metabase: number;
    kebenaran: string | null;
    parameter_kunci: Record<string, string>;
    aktif: boolean;
    boleh_buka: boolean;
}

export interface Halaman<T> {
    data: T[];
    meta: { jumlah: number; halaman: number; halaman_akhir: number };
}

// ---------------------------------------------------------------- labels

export const KATEGORI_LABEL: Record<string, string> = {
    portfolio: 'Portfolio', kewangan: 'Kewangan', operasi: 'Operasi', risiko: 'Risiko', pematuhan: 'Pematuhan & audit', aset: 'Aset', lembaga: 'Lembaga & berkanun',
};
export const JENIS_LABEL: Record<string, string> = { lap: 'Laporan standard', komposit: 'Laporan gabungan', modul: 'Laporan modul' };
export const OP_LABEL: Record<string, string> = {
    sama: 'sama dengan', tidak_sama: 'tidak sama', dalam: 'salah satu daripada', lebih: 'lebih daripada', kurang: 'kurang daripada',
    lebih_sama: '≥', kurang_sama: '≤', antara: 'antara', mengandungi: 'mengandungi', kosong: 'kosong', tidak_kosong: 'tidak kosong', benar: 'Ya', palsu: 'Tidak',
};
export const FUNGSI_LABEL: Record<string, string> = {
    bilangan: 'Bilangan rekod', jumlah: 'Jumlah', purata: 'Purata', min: 'Minimum', maks: 'Maksimum', bilangan_benar: 'Bilangan "Ya"', peratus_benar: 'Peratus "Ya"',
};
export const ARAS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = { rendah: 'success', sederhana: 'info', tinggi: 'warning', kritikal: 'danger' };
export const ARAS_LABEL: Record<string, string> = { rendah: 'Rendah', sederhana: 'Sederhana', tinggi: 'Tinggi', kritikal: 'Kritikal' };
export const KEKERAPAN_LABEL: Record<string, string> = { harian: 'Harian', mingguan: 'Mingguan', bulanan: 'Bulanan', suku_tahun: 'Suku tahun' };
export const PRATETAP_LABEL: Record<string, string> = {
    hari_ini: 'Hari ini', '30_hari': '30 hari terakhir', bulan_ini: 'Bulan ini', suku_ini: 'Suku tahun ini', tahun_ini: 'Tahun ini', tahun_lepas: 'Tahun lepas', '12_bulan': '12 bulan terakhir',
};

export function opsFor(jenis: JenisMedan): string[] {
    switch (jenis) {
        case 'bool':
            return ['benar', 'palsu'];
        case 'nombor':
        case 'wang':
            return ['sama', 'tidak_sama', 'lebih', 'kurang', 'lebih_sama', 'kurang_sama', 'antara', 'kosong', 'tidak_kosong'];
        case 'tarikh':
            return ['sama', 'lebih', 'kurang', 'antara', 'kosong', 'tidak_kosong'];
        default:
            return ['sama', 'tidak_sama', 'dalam', 'mengandungi', 'kosong', 'tidak_kosong'];
    }
}

// ---------------------------------------------------------------- API

export function qs(params: Record<string, unknown>): string {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v === undefined || v === null || v === '') continue;
        p.set(k, String(v));
    }
    const s = p.toString();
    return s ? `?${s}` : '';
}

export const lapApi = {
    katalog: () => api.get<{ data: Katalog[]; kategori: { kod: string; nama: string }[] }>('/lap/katalog'),
    katalogSatu: (kod: string) => api.get<{ data: Katalog & { bahagian_laporan: { kod: string; nama: string }[]; kamus: MedanKamus[] } }>(`/lap/katalog/${encodeURIComponent(kod)}`),
    kemasKiniKatalog: (kod: string, body: Partial<Katalog>) => api.put<{ data: Katalog }>(`/lap/katalog/${encodeURIComponent(kod)}`, body),
    setData: () => api.get<{ data: SetData[]; pratetap: { kod: string; nama: string }[] }>('/lap/set-data'),
    kamus: () => api.get<{ data: { laporan: KamusLaporan[]; set_data: { kunci: string; label: string; paparan: string; jadual_sumber: string; modul: string; medan: MedanKamus[] }[]; pautan_kamus_pro: string; dokumen: string } }>('/lap/kamus'),
    jana: (body: { sumber: Sumber; parameter?: Parameter; halaman?: number; per_halaman?: number; drill?: Record<string, string | null> }) => api.post<{ data: Laporan }>('/lap/jana', body),
    imej: (body: { tajuk: string; kod_laporan?: string | null; id_definisi?: string | null; bilangan_rekod?: number; parameter?: Parameter }) => api.post('/lap/eksport/imej', body),
    definisi: () => api.get<{ data: DefinisiTersimpan[] }>('/lap/definisi'),
    definisiSatu: (id: string) => api.get<{ data: DefinisiTersimpan }>(`/lap/definisi/${id}`),
    simpanDefinisi: (body: Record<string, unknown>, id?: string) => (id ? api.put<{ data: DefinisiTersimpan }>(`/lap/definisi/${id}`, body) : api.post<{ data: DefinisiTersimpan }>('/lap/definisi', body)),
    padamDefinisi: (id: string) => api.delete(`/lap/definisi/${id}`),
    prompt: (prompt: string) => api.post<{ data: { difahami: boolean; definisi: Definisi | null; penjelasan: string[]; label_cadangan: string } }>('/lap/ai/prompt', { prompt }),
    carian: (q: string, halaman = 1) => api.get<{ data: { tafsiran: string[]; hasil: Hasil | null; mesej?: string } }>(`/lap/carian${qs({ q, halaman })}`),
    jadual: () => api.get<{ data: Jadual[] }>('/lap/jadual'),
    penerima: (q: string) => api.get<{ data: { id: number; name: string; email: string; bahagian: string | null }[] }>(`/lap/jadual/penerima${qs({ q })}`),
    simpanJadual: (body: Record<string, unknown>, id?: number) => (id ? api.put<{ data: Jadual }>(`/lap/jadual/${id}`, body) : api.post<{ data: Jadual }>('/lap/jadual', body)),
    padamJadual: (id: number) => api.delete(`/lap/jadual/${id}`),
    jalanJadual: (id: number) => api.post<{ data: { dihantar: number; ditolak: number; gagal: number } }>(`/lap/jadual/${id}/jalan`),
    log: (p: Record<string, unknown>) => api.get<Halaman<LogPenjanaan> & { meta: { semua: boolean } }>(`/lap/log${qs(p)}`),
    analitik: (p: Record<string, unknown>) => api.get<Halaman<HasilAnalitik> & { label_cadangan: string }>(`/lap/analitik${qs(p)}`),
    ringkasanAnalitik: () => api.get<{ data: { jenis: string; nama: string; aras: Record<string, number>; jumlah: number; dikira_pada: string | null }[]; cadangan_baharu: number; versi_model: string; label_cadangan: string }>('/lap/analitik/ringkasan'),
    jalankanAnalitik: () => api.post<{ data: Record<string, number> }>('/lap/analitik/jalankan', {}),
    cadangan: (p: Record<string, unknown>) => api.get<Halaman<Cadangan> & { label_cadangan: string }>(`/lap/cadangan${qs(p)}`),
    putuskan: (id: number, keputusan: 'diterima' | 'ditolak', justifikasi: string) => api.put(`/lap/cadangan/${id}/keputusan`, { keputusan, justifikasi }),
    insight: () => api.get<{ data: Insight[]; label_cadangan: string }>('/lap/insight'),
    janaInsight: () => api.post('/lap/insight'),
    bi: () => api.get<{ data: PapanBi[]; status: { dikonfigurasi: boolean; hidup: boolean; versi: string | null; url: string } }>('/lap/bi'),
    embed: (kod: string) => api.get<{ data: { url: string; tamat: number; parameter: Record<string, string[]>; nama: string } }>(`/lap/bi/${kod}/embed`),
    simpanBi: (body: Record<string, unknown>, id?: number) => (id ? api.put(`/lap/bi/${id}`, body) : api.post('/lap/bi', body)),
    padamBi: (id: number) => api.delete(`/lap/bi/${id}`),
};

export interface KamusLaporan {
    kod: string;
    nama: string;
    kategori: string;
    jenis: string;
    definisi_metrik: string | null;
    sumber_data: string | null;
    pemilik: string | null;
    status_pengesahan: string;
}

export interface MedanKamus {
    kunci: string;
    label: string;
    jenis: string;
    sumber: string;
    definisi: string | null;
    nama_perniagaan: string | null;
    nilai_dibenarkan: string | null;
    data_owner: string | null;
    data_steward: string | null;
    klasifikasi: string | null;
}

function cookie(name: string): string | null {
    const m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
}

function saveBlob(blob: Blob, name: string) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

/** POST /lap/eksport — downloads the file, or returns the queued log id (202). */
export async function eksport(body: { sumber: Sumber; parameter?: Parameter; format: 'pdf' | 'xlsx' | 'csv'; latar?: boolean }): Promise<{ latar: true; id_log: string } | { latar: false }> {
    if (!cookie('XSRF-TOKEN')) await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    const res = await fetch('/api/v1/lap/eksport', {
        method: 'POST',
        credentials: 'include',
        headers: { Accept: '*/*', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': cookie('XSRF-TOKEN') ?? '' },
        body: JSON.stringify(body),
    });
    const isJson = res.headers.get('content-type')?.includes('application/json');
    if (!res.ok) throw new ApiError(res.status, isJson ? await res.json() : null);
    if (res.status === 202) {
        const j = await res.json();
        return { latar: true, id_log: j.data.id_log };
    }
    const name = /filename="?([^";]+)"?/.exec(res.headers.get('content-disposition') ?? '')?.[1] ?? `laporan.${body.format}`;
    saveBlob(await res.blob(), name);
    return { latar: false };
}

export async function muatTurunLog(id: string): Promise<void> {
    const res = await fetch(`/api/v1/lap/log/${id}/muat-turun`, { credentials: 'include', headers: { Accept: '*/*' } });
    if (!res.ok) throw new ApiError(res.status, res.headers.get('content-type')?.includes('application/json') ? await res.json() : null);
    const name = /filename="?([^";]+)"?/.exec(res.headers.get('content-disposition') ?? '')?.[1] ?? 'laporan';
    saveBlob(await res.blob(), name);
}

// ---------------------------------------------------------------- chart → PNG (SRS-LAP-05)

/**
 * Draws a simple bar / line / pie chart on a canvas and saves it as PNG —
 * no third-party library; same data the on-screen MYDS chart shows.
 */
export function cartaKePng(opts: { tajuk: string; subtajuk?: string; label: string[]; siri: { nama: string; nilai: number[] }[]; jenis: 'bar' | 'garis' | 'pai'; tandaAir?: string }) {
    const W = 1200;
    const H = 700;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const g = c.getContext('2d');
    if (!g) return;
    const warna = ['#2563eb', '#16a34a', '#ea580c', '#9333ea', '#dc2626', '#0891b2', '#ca8a04', '#4b5563'];
    g.fillStyle = '#ffffff';
    g.fillRect(0, 0, W, H);
    g.fillStyle = '#18181b';
    g.font = 'bold 26px Inter, Arial, sans-serif';
    g.fillText(opts.tajuk.slice(0, 80), 40, 50);
    g.font = '16px Inter, Arial, sans-serif';
    g.fillStyle = '#52525b';
    if (opts.subtajuk) g.fillText(opts.subtajuk.slice(0, 140), 40, 78);
    const top = 110;
    const bottom = H - 110;
    const left = 110;
    const right = W - 40;
    if (opts.jenis === 'pai') {
        const vals = opts.siri[0]?.nilai ?? [];
        const total = vals.reduce((a, b) => a + Math.max(0, b), 0) || 1;
        let a0 = -Math.PI / 2;
        const cx = 360;
        const cy = (top + bottom) / 2 + 20;
        const r = 220;
        vals.forEach((v, i) => {
            const a1 = a0 + (Math.max(0, v) / total) * Math.PI * 2;
            g.beginPath();
            g.moveTo(cx, cy);
            g.arc(cx, cy, r, a0, a1);
            g.closePath();
            g.fillStyle = warna[i % warna.length];
            g.fill();
            a0 = a1;
        });
        g.font = '16px Inter, Arial, sans-serif';
        opts.label.slice(0, 14).forEach((l, i) => {
            g.fillStyle = warna[i % warna.length];
            g.fillRect(680, top + 20 + i * 30, 18, 18);
            g.fillStyle = '#18181b';
            g.fillText(`${l} — ${vals[i]?.toLocaleString('ms-MY')} (${(((vals[i] ?? 0) / total) * 100).toFixed(1)}%)`.slice(0, 60), 708, top + 35 + i * 30);
        });
    } else {
        const all = opts.siri.flatMap((s) => s.nilai);
        const max = Math.max(1, ...all);
        const n = Math.max(1, opts.label.length);
        g.strokeStyle = '#e4e4e7';
        g.fillStyle = '#71717a';
        g.font = '13px Inter, Arial, sans-serif';
        for (let i = 0; i <= 4; i++) {
            const y = bottom - ((bottom - top) * i) / 4;
            g.beginPath();
            g.moveTo(left, y);
            g.lineTo(right, y);
            g.stroke();
            g.fillText(((max * i) / 4).toLocaleString('ms-MY', { maximumFractionDigits: 0 }), 20, y + 4);
        }
        const slot = (right - left) / n;
        opts.siri.forEach((s, si) => {
            g.fillStyle = warna[si % warna.length];
            g.strokeStyle = warna[si % warna.length];
            if (opts.jenis === 'bar') {
                const bw = Math.max(4, (slot * 0.7) / opts.siri.length);
                s.nilai.forEach((v, i) => {
                    const h = ((bottom - top) * Math.max(0, v)) / max;
                    g.fillRect(left + i * slot + slot * 0.15 + si * bw, bottom - h, bw - 2, h);
                });
            } else {
                g.lineWidth = 3;
                g.beginPath();
                s.nilai.forEach((v, i) => {
                    const x = left + i * slot + slot / 2;
                    const y = bottom - ((bottom - top) * Math.max(0, v)) / max;
                    if (i === 0) g.moveTo(x, y);
                    else g.lineTo(x, y);
                });
                g.stroke();
            }
        });
        g.fillStyle = '#52525b';
        g.font = '12px Inter, Arial, sans-serif';
        const every = Math.ceil(n / 24);
        opts.label.forEach((l, i) => {
            if (i % every) return;
            g.save();
            g.translate(left + i * slot + slot / 2, bottom + 14);
            g.rotate(-Math.PI / 6);
            g.fillText(l.slice(0, 18), -40, 0);
            g.restore();
        });
        g.font = '14px Inter, Arial, sans-serif';
        opts.siri.forEach((s, si) => {
            g.fillStyle = warna[si % warna.length];
            g.fillRect(left + si * 260, H - 50, 16, 16);
            g.fillStyle = '#18181b';
            g.fillText(s.nama.slice(0, 30), left + si * 260 + 24, H - 37);
        });
    }
    if (opts.tandaAir) {
        g.fillStyle = '#a1a1aa';
        g.font = '12px Inter, Arial, sans-serif';
        g.fillText(opts.tandaAir.slice(0, 160), 40, H - 12);
    }
    c.toBlob((b) => b && saveBlob(b, `${opts.tajuk.toLowerCase().replace(/[^a-z0-9]+/g, '_').slice(0, 60)}.png`), 'image/png');
}

// ---------------------------------------------------------------- hooks & format

const noop = () => () => {};

export function useMe() {
    return useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
}

export function useCan() {
    const { data } = useMe();
    const hydrated = useSyncExternalStore(noop, () => true, () => false);
    const perms = new Set(hydrated ? (data?.permissions ?? []) : []);
    return (...keys: string[]) => keys.some((k) => perms.has(k));
}

export function ralat(e: unknown): string {
    if (e instanceof ApiError) {
        const first = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
        return first ?? e.message;
    }
    return e instanceof Error ? e.message : 'Ralat tidak dijangka.';
}

export function tarikhMasa(v: string | null | undefined): string {
    if (!v) return '—';
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? v : d.toLocaleString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function tarikh(v: string | null | undefined): string {
    if (!v) return '—';
    const [y, m, d] = v.slice(0, 10).split('-');
    return d ? `${d}/${m}/${y}` : v;
}

/** Display a cell by column type (money with 2 decimals, never float-summed here). */
export function papar(jenis: JenisMedan, v: unknown, label?: string | null): string {
    if (label) return label;
    if (v === null || v === undefined || v === '') return '—';
    switch (jenis) {
        case 'wang': {
            const [i, d = '00'] = String(v).split('.');
            const neg = i.startsWith('-');
            return `${neg ? '-' : ''}${i.replace('-', '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${d.padEnd(2, '0').slice(0, 2)}`;
        }
        case 'peratus':
            return `${v}%`;
        case 'bool':
            return v ? 'Ya' : 'Tidak';
        case 'tarikh':
            return tarikh(String(v));
        case 'bilangan':
        case 'nombor':
            return typeof v === 'number' ? v.toLocaleString('ms-MY') : String(v);
        default:
            return String(v).replaceAll('_', ' ');
    }
}

export function nombor(v: unknown): number {
    const n = typeof v === 'number' ? v : Number.parseFloat(String(v ?? 0));
    return Number.isFinite(n) ? n : 0;
}
