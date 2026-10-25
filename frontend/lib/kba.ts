/*
 * MOD-KBA frontend helpers: API client, types and hooks for the knowledge
 * base (/admin/kba/*). Every call is authorised by the API itself and every
 * passage is filtered per user at retrieval; hiding here is UI only.
 */
import { useQuery } from '@tanstack/react-query';
import { useCallback, useMemo, useSyncExternalStore } from 'react';
import { api } from '@/lib/api';
import type { User } from '@/lib/types';

// ---------------------------------------------------------------- types

export type Klasifikasi = 'awam' | 'dalaman' | 'sulit' | 'rahsia';
export type Penilaian = 'membantu' | 'tidak_membantu' | 'sumber_salah';

export interface Petikan {
    no: number;
    id_segmen: number;
    id_sumber: number;
    dokumen: string;
    versi: string;
    seksyen: string | null;
    laluan?: string | null;
    petikan?: string;
    pautan: string;
    klasifikasi?: Klasifikasi;
}

export interface Jawapan {
    id_log: number;
    id_perbualan: string | null;
    status: 'berjaya' | 'tiada_sumber' | 'ditolak';
    cukup_sumber: boolean;
    jawapan: string;
    mod: 'ekstraktif' | 'generatif' | null;
    model: string | null;
    bahasa: 'ms' | 'en';
    sumber: Petikan[];
}

export interface MesejSejarah {
    id_log: number;
    soalan: string;
    jawapan: string | null;
    status: Jawapan['status'];
    cukup_sumber: boolean;
    mod: Jawapan['mod'];
    model: string | null;
    sumber: Petikan[];
    cap_masa: string;
    maklum_balas: Penilaian | null;
}

export interface Perbualan {
    id: string;
    tajuk: string;
    log_count: number;
    created_at: string;
    updated_at: string;
}

export interface HasilCarian {
    id_segmen: number;
    id_sumber: number;
    dokumen: string;
    versi: string;
    jenis_dokumen: string;
    modul: string | null;
    seksyen: string | null;
    laluan: string | null;
    petikan: string;
    skor: number;
    pautan: string;
    klasifikasi: Klasifikasi;
}

export interface SegmenPaparan {
    id: number;
    urutan: number;
    seksyen: string | null;
    laluan: string | null;
    kandungan: string;
    klasifikasi: Klasifikasi;
    lapuk: boolean;
    no_versi: number;
}

export interface SumberKandungan {
    id: number;
    tajuk: string;
    versi: string;
    jenis_dokumen: string;
    asal: string;
    modul: string | null;
    klasifikasi: Klasifikasi;
    tarikh_indeks_akhir: string | null;
    pautan_dms: string | null;
    pautan_artikel: string | null;
    segmen_fokus: number | null;
    segmen: SegmenPaparan[];
}

export interface Sumber {
    id: number;
    kod: string;
    asal: 'baseline' | 'dms' | 'muat_naik' | 'artikel';
    tajuk: string;
    jenis_dokumen: string;
    versi: string;
    modul: string | null;
    klasifikasi: Klasifikasi;
    senarai_peranan_dibenarkan: string[];
    kod_daerah: string | null;
    format: string;
    status_indeks: string;
    mesej_indeks: string | null;
    bilangan_segmen: number;
    tarikh_indeks_akhir: string | null;
    aktif: boolean;
    id_dokumen_dms: string | null;
    laluan_fail: string | null;
    pautan: string;
}

export interface StatusIndeks {
    sumber: Record<string, number>;
    sumber_aktif: number;
    segmen_aktif: number;
    segmen_berembedding: number;
    segmen_mengandungi_arahan: number;
    segmen_lapuk: number;
    model_embedding: string;
    model_embedding_segmen: string[];
    model_llm: string | null;
    llm_tersedia: boolean;
    mod_jawapan: 'ekstraktif' | 'generatif';
}

export interface Pemetaan {
    id?: number;
    klasifikasi: 'dalaman' | 'sulit' | 'rahsia';
    peranan: string;
    jenis_capaian: 'baca' | 'semak' | 'terbit';
    tarikh_berkuat_kuasa: string;
}

export interface Artikel {
    id: number;
    tajuk: string;
    kategori: string | null;
    modul: string | null;
    status: 'draf' | 'dalam_semakan' | 'diterbitkan' | 'lapuk';
    versi: number;
    versi_terbit: number | null;
    klasifikasi: Klasifikasi;
    senarai_peranan_dibenarkan: string[];
    id_pemilik: number | null;
    id_penyemak: number | null;
    tarikh_terbit: string | null;
    tarikh_semakan_akhir: string | null;
    tarikh_semakan_seterusnya: string | null;
    sumber_asal: 'manual' | 'tiket' | 'ai';
    id_tiket_asal: string | null;
    pautan_tiket: string | null;
    versi_sistem_dirujuk: string | null;
    perlu_semakan: boolean;
    sebab_perlu_semakan: string | null;
    catatan_semakan: string | null;
    updated_at: string | null;
    kandungan?: string;
    pautan_sumber?: string | null;
}

export interface VersiArtikel {
    id: number;
    nombor_versi: number;
    tajuk: string;
    kandungan: string;
    id_pengubah: number | null;
    sebab_perubahan: string | null;
    cap_masa: string;
}

export interface Keluaran {
    id: number;
    versi: string;
    catatan: string | null;
    bilangan_artikel_ditanda: number;
    created_at: string;
}

export interface Jurang {
    soalan: string;
    bilangan: number;
    tiada_sumber: number;
    maklum_balas_negatif: number;
    terakhir: string;
    contoh: string;
}

export interface Analitik {
    tempoh: { dari: string; hingga: string };
    soalan_kerap: { soalan: string; bilangan: number; kadar_memuaskan: number; modul: string | null; terakhir: string }[];
    jurang: Jurang[];
    sumber_paling_dirujuk: { id: number; tajuk: string; jenis_dokumen: string; asal: string; bilangan: number }[];
    sumber_tidak_dirujuk: { id: number; tajuk: string; jenis_dokumen: string; asal: string }[];
    mengikut_modul: { modul: string; bilangan: number; tiada_sumber: number }[];
    mengikut_peranan: { peranan: string; bilangan: number; tiada_sumber: number }[];
    infrastruktur: {
        ringkasan: { prompt: number; token: number; purata_ms: number; generatif: number; ekstraktif: number; ditolak: number };
        model: { model: string; bilangan: number; purata_ms: number }[];
    };
    artikel: { status: Record<string, number>; melepasi_tarikh_semakan: number; dari_tiket: Record<string, number> };
    maklum_balas: { penilaian: Penilaian; status_tindakan: string; bilangan: number }[];
    liputan: {
        id: number;
        tajuk: string;
        asal: string;
        jenis_dokumen: string;
        versi: string;
        klasifikasi: Klasifikasi;
        status_indeks: string;
        bilangan_segmen: number;
        tarikh_indeks_akhir: string | null;
        aktif: boolean;
        mesej_indeks: string | null;
    }[];
}

export interface MaklumBalas {
    id: number;
    id_log_prompt: number;
    penilaian: Penilaian;
    ulasan: string | null;
    cap_masa: string;
    status_tindakan: 'baharu' | 'dalam_tindakan' | 'selesai' | 'ditolak';
    catatan_tindakan: string | null;
    log: { id: number; prompt: string; jawapan: string | null; status: string; senarai_sumber_dipetik: Petikan[]; cap_masa: string } | null;
}

export interface LogPrompt {
    id: number;
    id_pengguna: number | null;
    nama_pengguna: string | null;
    peranan: string[];
    alamat_ip: string | null;
    saluran: string;
    prompt: string;
    status: Jawapan['status'];
    mod_jawapan: Jawapan['mod'];
    model_digunakan: string | null;
    model_embedding: string | null;
    bilangan_token: number;
    masa_respons_ms: number;
    cap_masa: string;
    senarai_sumber_dipetik: Omit<Petikan, 'pautan'>[];
    senarai_id_segmen_dicapai: number[];
    sebab: string | null;
}

export interface LogPromptPenuh extends LogPrompt {
    jawapan: string | null;
    segmen_dicapai: { id: number; id_sumber: number; dokumen: string; nombor_seksyen: string | null; tajuk_seksyen: string | null; klasifikasi: Klasifikasi; status: string }[];
}

export interface SokonganItem {
    id_segmen: number;
    dokumen: string;
    versi: string;
    seksyen: string | null;
    laluan: string | null;
    ringkasan: string;
    pautan: string;
}

export interface SokonganModul {
    modul: string;
    reka_bentuk: SokonganItem[];
    integrasi: SokonganItem[];
    parameter: { kunci: string; pautan: string }[];
    sejarah_masalah: { id: number; tajuk: string; sumber_asal: string; id_tiket_asal: string | null; pautan_tiket: string | null; tarikh_terbit: string | null; versi_sistem: string | null; pautan: string }[];
    soalan_tanpa_jawapan: { soalan: string; bilangan: number }[];
}

// ---------------------------------------------------------------- labels

export const KLASIFIKASI_LABEL: Record<Klasifikasi, string> = { awam: 'Awam', dalaman: 'Dalaman', sulit: 'Sulit', rahsia: 'Rahsia' };
export const PENILAIAN_LABEL: Record<Penilaian, string> = { membantu: 'Membantu', tidak_membantu: 'Tidak membantu', sumber_salah: 'Sumber salah' };
export const STATUS_ARTIKEL_LABEL: Record<Artikel['status'], string> = { draf: 'Draf', dalam_semakan: 'Dalam semakan', diterbitkan: 'Diterbitkan', lapuk: 'Lapuk' };
export const STATUS_INDEKS_LABEL: Record<string, string> = { menunggu: 'Menunggu', sedang: 'Sedang diindeks', selesai: 'Selesai', gagal: 'Gagal', tiada_teks: 'Tiada teks' };
export const ASAL_LABEL: Record<string, string> = { baseline: 'Baseline projek', dms: 'DMS', muat_naik: 'Muat naik', artikel: 'Artikel' };
export const STATUS_TINDAKAN_LABEL: Record<MaklumBalas['status_tindakan'], string> = { baharu: 'Baharu', dalam_tindakan: 'Dalam tindakan', selesai: 'Selesai', ditolak: 'Ditolak' };
export const JENIS_DOKUMEN: { value: string; label: string }[] = [
    { value: 'URS', label: 'URS' },
    { value: 'BRS', label: 'BRS' },
    { value: 'SRS', label: 'SRS' },
    { value: 'SDS', label: 'SDS' },
    { value: 'reka_bentuk', label: 'Reka bentuk sistem' },
    { value: 'rajah_aliran', label: 'Rajah aliran' },
    { value: 'manual_pengguna', label: 'Manual pengguna' },
    { value: 'manual_pentadbir', label: 'Manual pentadbir' },
    { value: 'manual_operasi', label: 'Manual operasi' },
    { value: 'manual_teknikal', label: 'Manual teknikal' },
    { value: 'SOP', label: 'SOP' },
    { value: 'nota_keluaran', label: 'Nota keluaran' },
    { value: 'artikel_tiket', label: 'Artikel penyelesaian tiket' },
    { value: 'artikel', label: 'Artikel' },
    { value: 'dokumen_dms', label: 'Dokumen DMS' },
    { value: 'lain', label: 'Lain-lain' },
];
export const jenisLabel = (v: string) => JENIS_DOKUMEN.find((j) => j.value === v)?.label ?? v;

export function tarikhMasa(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? iso : d.toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' });
}

// ---------------------------------------------------------------- API

const qs = (params: Record<string, string | number | boolean | null | undefined>) => {
    const s = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '' && v !== false) s.set(k, String(v));
    });
    const str = s.toString();
    return str ? `?${str}` : '';
};

export const kbaApi = {
    tanya: (soalan: string, id_perbualan?: string | null) => api.post<{ data: Jawapan }>('/kba/tanya', { soalan, id_perbualan: id_perbualan ?? null }),
    statusAi: () => api.get<{ data: { mod_jawapan: string; model_llm: string | null; llm_tersedia: boolean; model_embedding: string } }>('/kba/status-ai'),
    carian: (p: Record<string, string>) => api.get<{ data: HasilCarian[] }>(`/kba/carian${qs(p)}`),
    kandungan: (id: number | string, segmen?: string | null) => api.get<{ data: SumberKandungan }>(`/kba/sumber/${id}/kandungan${qs({ segmen })}`),
    perbualan: () => api.get<{ data: Perbualan[] }>('/kba/perbualan'),
    perbualanSatu: (id: string) => api.get<{ data: { id: string; tajuk: string; mesej: MesejSejarah[] } }>(`/kba/perbualan/${id}`),
    sembunyi: (id: string) => api.delete<{ message: string }>(`/kba/perbualan/${id}`),
    maklumBalas: (idLog: number, penilaian: Penilaian, ulasan?: string) => api.post<{ message: string }>(`/kba/log/${idLog}/maklum-balas`, { penilaian, ulasan: ulasan || null }),

    sumber: (p: Record<string, string> = {}) => api.get<{ data: Sumber[] }>(`/kba/sumber${qs(p)}`),
    statusIndeks: () => api.get<{ data: StatusIndeks }>('/kba/status-indeks'),
    muatNaik: (fd: FormData) => api.postForm<{ data: Sumber; message: string }>('/kba/sumber', fd),
    daftarDms: (body: { id_dokumen_dms: string; jenis_dokumen: string; modul?: string }) => api.post<{ data: Sumber; message: string }>('/kba/sumber/dms', body),
    kemasKiniSumber: (id: number, body: Partial<Pick<Sumber, 'tajuk' | 'jenis_dokumen' | 'modul' | 'klasifikasi' | 'senarai_peranan_dibenarkan' | 'aktif'>>) =>
        api.put<{ data: Sumber; message: string }>(`/kba/sumber/${id}`, body),
    indeksSemula: (id: number) => api.post<{ data: Sumber; message: string }>(`/kba/sumber/${id}/indeks-semula`),
    indeksSemuaSemula: () => api.post<{ message: string }>('/kba/indeks-semula'),
    pemetaan: () => api.get<{ data: Pemetaan[] }>('/kba/pemetaan'),
    simpanPemetaan: (pemetaan: Pemetaan[]) => api.put<{ data: Pemetaan[] }>('/kba/pemetaan', { pemetaan }),

    artikel: (p: Record<string, string | boolean> = {}) => api.get<{ data: Artikel[] }>(`/kba/artikel${qs(p)}`),
    artikelSatu: (id: number | string) => api.get<{ data: Artikel }>(`/kba/artikel/${id}`),
    ciptaArtikel: (body: Partial<Artikel> & { sebab_perubahan?: string }) => api.post<{ data: Artikel; message: string }>('/kba/artikel', body),
    kemasKiniArtikel: (id: number, body: Partial<Artikel> & { sebab_perubahan?: string }) => api.put<{ data: Artikel; message: string }>(`/kba/artikel/${id}`, body),
    hantarArtikel: (id: number) => api.post<{ data: Artikel; message: string }>(`/kba/artikel/${id}/hantar`),
    lapukArtikel: (id: number) => api.post<{ data: Artikel; message: string }>(`/kba/artikel/${id}/lapuk`),
    versiArtikel: (id: number) => api.get<{ data: VersiArtikel[] }>(`/kba/artikel/${id}/versi`),
    keluaran: () => api.get<{ data: Keluaran[] }>('/kba/keluaran'),
    simpanKeluaran: (versi: string, catatan?: string) => api.post<{ data: Keluaran; message: string }>('/kba/keluaran', { versi, catatan: catatan || null }),

    analitik: (p: Record<string, string> = {}) => api.get<{ data: Analitik }>(`/kba/analitik${qs(p)}`),
    jurang: (p: Record<string, string> = {}) => api.get<{ data: Jurang[] }>(`/kba/laporan/jurang${qs(p)}`),
    logPrompt: (p: Record<string, string | number>) =>
        api.get<{ data: LogPrompt[]; meta: { jumlah: number; halaman: number; halaman_akhir: number } }>(`/kba/log-prompt${qs(p)}`),
    logPromptSatu: (id: number) => api.get<{ data: LogPromptPenuh }>(`/kba/log-prompt/${id}`),
    senaraiMaklumBalas: (p: Record<string, string> = {}) => api.get<{ data: MaklumBalas[] }>(`/kba/maklum-balas${qs(p)}`),
    kemasKiniMaklumBalas: (id: number, status_tindakan: string, catatan_tindakan?: string) =>
        api.put<{ data: MaklumBalas; message: string }>(`/kba/maklum-balas/${id}`, { status_tindakan, catatan_tindakan: catatan_tindakan || null }),
    sokongan: () => api.get<{ data: { modul: string; segmen: number; artikel: number }[] }>('/kba/sokongan'),
    sokonganModul: (modul: string) => api.get<{ data: SokonganModul }>(`/kba/sokongan/${modul}`),
    eksportUrl: (format: 'markdown' | 'html') => `/api/v1/kba/eksport?format=${format}`,
};

// ---------------------------------------------------------------- hooks

export function useMe() {
    return useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
}

const noopSubscribe = () => () => {};

/** UI hiding only — the API enforces every permission itself. */
export function useCan() {
    const { data } = useMe();
    const hydrated = useSyncExternalStore(noopSubscribe, () => true, () => false);
    const perms = useMemo(() => new Set(hydrated ? (data?.permissions ?? []) : []), [hydrated, data]);
    return useCallback((...keys: string[]) => keys.some((k) => perms.has(k)), [perms]);
}

/**
 * Split an answer into text and clickable [n] citation markers.
 */
export function pecahPetikan(teks: string): Array<{ jenis: 'teks'; nilai: string } | { jenis: 'petikan'; no: number }> {
    const out: Array<{ jenis: 'teks'; nilai: string } | { jenis: 'petikan'; no: number }> = [];
    const re = /\[(\d{1,2})\]/g;
    let last = 0;
    let m: RegExpExecArray | null;
    while ((m = re.exec(teks)) !== null) {
        if (m.index > last) out.push({ jenis: 'teks', nilai: teks.slice(last, m.index) });
        out.push({ jenis: 'petikan', no: Number(m[1]) });
        last = m.index + m[0].length;
    }
    if (last < teks.length) out.push({ jenis: 'teks', nilai: teks.slice(last) });
    return out;
}
