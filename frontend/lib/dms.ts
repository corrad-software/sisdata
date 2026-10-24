import { api, ApiError } from './api';

/* MOD-DMS client (SRS-DMS-18: every function goes through /api/v1/dms). */

export type Klasifikasi = 'awam' | 'dalaman' | 'sulit' | 'rahsia';
export type StatusDokumen = 'aktif' | 'tamat' | 'diarkib' | 'dilupus';
export type ModPratonton = 'pdf' | 'imej' | 'teks' | 'tiada';

export interface KategoriRingkas {
    id: number;
    kod: string;
    nama: string;
}

export interface DokumenRingkas {
    id: string;
    no_rujukan: string;
    tajuk: string;
    nama_fail: string;
    sambungan: string;
    jenis_mime: string;
    saiz_bait: number;
    kategori: KategoriRingkas;
    modul_sumber: string;
    jenis_entiti_induk: string | null;
    id_rekod_induk: string | null;
    klasifikasi: Klasifikasi;
    status: StatusDokumen;
    versi_semasa: number;
    tahun: number | null;
    kod_daerah: string | null;
    kod_mukim: string | null;
    tarikh_dokumen: string | null;
    tarikh_luput: string | null;
    kata_kunci: string[];
    status_indeks: 'menunggu' | 'selesai' | 'gagal' | 'tiada';
    pratonton: ModPratonton;
    petikan: string | null;
    dicipta_pada: string;
}

export interface DokumenPenuh extends DokumenRingkas {
    metadata: Record<string, string | number | boolean | null>;
    cincangan_fail: string;
    kaedah_indeks: 'teks' | 'ocr' | null;
    tarikh_arkib: string | null;
    tarikh_lupus: string | null;
    pencipta: { id: number; nama: string } | null;
    pemilik_proses: { id: number; nama: string } | null;
    induk: { jenis: string; id: string; label: string; pautan: string | null } | null;
    tanda_air: boolean;
    boleh: {
        kemas_kini: boolean;
        versi_baharu: boolean;
        padam: boolean;
        arkib: boolean;
        mohon_lupus: boolean;
        urus_kebenaran: boolean;
        muat_turun: boolean;
    };
}

export interface Versi {
    id: number;
    no_versi: number;
    nama_fail: string;
    jenis_mime: string;
    saiz_bait: number;
    cincangan_fail: string;
    algoritma: string;
    catatan: string | null;
    dimuat_naik_oleh: { id: number; nama: string } | null;
    dimuat_naik_pada: string;
    tersedia: boolean;
    metadata: Record<string, unknown>;
}

export interface PerbandinganVersi {
    dari: Versi;
    ke: Versi;
    kandungan_sama: boolean;
    medan: { medan: string; dari: string | null; ke: string | null; berubah: boolean }[];
}

export interface MedanMetadata {
    id: number;
    kategori_id: number | null;
    kod: string;
    label: string;
    jenis: 'teks' | 'nombor' | 'tarikh' | 'senarai' | 'ya_tidak';
    pilihan: string[] | null;
    wajib: boolean;
    aktif: boolean;
    susunan: number;
}

export interface Kategori extends KategoriRingkas {
    keterangan: string | null;
    jenis_fail_dibenarkan: string[] | null;
    had_saiz_mb: number | null;
    tempoh_retensi_bulan: number | null;
    tempoh_arkib_bulan: number | null;
    tindakan_selepas_arkib: 'lupus' | 'semak' | 'kekal';
    tempoh_amaran_luput_hari: number | null;
    klasifikasi_lalai: Klasifikasi;
    aktif: boolean;
    medan: MedanMetadata[];
}

export interface KonfigurasiMuatNaik {
    senarai_putih: string[];
    sambungan: string[];
    had_saiz_mb: { pejabat: number; spatial: number };
    kumpulan: Record<string, 'pejabat' | 'spatial'>;
    had_transaksi_mb: number;
    had_fail_kelompok: number;
}

export interface Halaman<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
}

export interface KeputusanFail {
    indeks: number;
    nama_fail: string;
    status: 'diterima' | 'ditolak';
    sebab?: string;
    mesej?: string;
    dokumen?: DokumenRingkas;
}

export interface Kuota {
    kuota_bait: number;
    guna_bait: number;
    peratus: number;
    amaran_peratus: number;
    amaran: boolean;
    ikut_modul: { kod: string; bait: number; bilangan: number }[];
    ikut_kategori: { kod: string; nama: string; bait: number; bilangan: number }[];
}

export interface Kuarantin {
    id: number;
    nama_fail: string;
    saiz_bait: number;
    cincangan_fail: string;
    nama_ancaman: string | null;
    induk: string | null;
    pemuat_naik: string | null;
    dikuarantin_pada: string;
    dimusnahkan_pada: string | null;
}

export interface Parameter {
    kunci: string;
    jenis: string;
    label: string;
    kritikal: boolean;
    nilai: unknown;
    lalai: unknown;
}

export interface Kebenaran {
    id: number;
    jenis_penerima: 'pengguna' | 'peranan';
    penerima: string;
    label: string;
    tamat_pada: string | null;
    diberi_pada: string;
}

export interface Pelupusan {
    id: number;
    dokumen: { id: string; no_rujukan: string; tajuk: string; status: StatusDokumen };
    justifikasi: string;
    status: string;
    id_aliran_kerja: string | null;
    pemohon: string | null;
    dimohon_pada: string;
    tarikh_keputusan: string | null;
    catatan_keputusan: string | null;
}

export const KLASIFIKASI_LABEL: Record<Klasifikasi, string> = {
    awam: 'Awam',
    dalaman: 'Dalaman',
    sulit: 'Sulit',
    rahsia: 'Rahsia',
};

export const STATUS_LABEL: Record<StatusDokumen, string> = {
    aktif: 'Aktif',
    tamat: 'Tamat',
    diarkib: 'Diarkib',
    dilupus: 'Dilupus',
};

export function saizMesra(bait: number): string {
    if (bait < 1024) return `${bait} B`;
    if (bait < 1024 ** 2) return `${(bait / 1024).toFixed(1)} KB`;
    if (bait < 1024 ** 3) return `${(bait / 1024 ** 2).toFixed(1)} MB`;
    return `${(bait / 1024 ** 3).toFixed(2)} GB`;
}

export function tarikhMesra(iso: string | null | undefined, withTime = false): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return withTime
        ? d.toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' })
        : d.toLocaleDateString('ms-MY', { dateStyle: 'medium' });
}

export type CarianDokumen = Partial<{
    q: string;
    kategori: string;
    klasifikasi: string;
    status: string;
    kod_daerah: string;
    kod_mukim: string;
    tahun: string;
    modul_sumber: string;
    tarikh_dari: string;
    tarikh_hingga: string;
    page: number;
}>;

export function cariDokumen(params: CarianDokumen) {
    const qs = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== '' && v !== null) qs.set(k, String(v));
    });
    return api.get<Halaman<DokumenRingkas>>(`/dms/dokumen?${qs.toString()}`);
}

export const dmsApi = {
    dokumen: (id: string) => api.get<{ data: DokumenPenuh }>(`/dms/dokumen/${id}`),
    kemasKini: (id: string, body: Record<string, unknown>) => api.put<{ data: DokumenPenuh }>(`/dms/dokumen/${id}`, body),
    padam: (id: string) => api.delete<{ message: string }>(`/dms/dokumen/${id}`),
    teks: (id: string) => api.get<{ data: { status_indeks: string; kaedah: string | null; teks: string | null } }>(`/dms/dokumen/${id}/teks`),
    indeksSemula: (id: string) => api.post<{ message: string }>(`/dms/dokumen/${id}/indeks-semula`),
    versi: (id: string) => api.get<{ data: Versi[] }>(`/dms/dokumen/${id}/versi`),
    banding: (id: string, dari: number, ke: number) =>
        api.get<{ data: PerbandinganVersi }>(`/dms/dokumen/${id}/versi/banding?dari=${dari}&ke=${ke}`),
    pautan: (id: string, jenis: 'muat_turun' | 'pratonton' | 'cetak', versi?: number) =>
        api.post<{ data: { url: string; tamat_pada: string } }>(`/dms/dokumen/${id}/pautan`, { jenis, versi }),
    arkib: (id: string) => api.post<{ message: string }>(`/dms/dokumen/${id}/arkib`),
    mohonLupus: (id: string, justifikasi: string) =>
        api.post<{ message: string }>(`/dms/dokumen/${id}/pelupusan`, { justifikasi }),
    kebenaran: (id: string) => api.get<{ data: Kebenaran[] }>(`/dms/dokumen/${id}/kebenaran`),
    tambahKebenaran: (id: string, body: { jenis_penerima: string; penerima: string; tamat_pada?: string }) =>
        api.post<{ data: Kebenaran }>(`/dms/dokumen/${id}/kebenaran`, body),
    buangKebenaran: (id: string, grant: number) => api.delete<{ message: string }>(`/dms/dokumen/${id}/kebenaran/${grant}`),
    rekod: (jenis: string, id: string | number) => api.get<{ data: DokumenRingkas[] }>(`/dms/rekod/${jenis}/${id}/dokumen`),
    kategori: (semua = false) => api.get<{ data: Kategori[] }>(`/dms/kategori${semua ? '?semua=1' : ''}`),
    simpanKategori: (body: Partial<Kategori>, id?: number) =>
        id ? api.put<{ data: Kategori }>(`/dms/kategori/${id}`, body) : api.post<{ data: Kategori }>('/dms/kategori', body),
    simpanMedan: (body: Partial<MedanMetadata>, id?: number) =>
        id ? api.put<{ data: MedanMetadata }>(`/dms/medan/${id}`, body) : api.post<{ data: MedanMetadata }>('/dms/medan', body),
    konfigurasiMuatNaik: () => api.get<{ data: KonfigurasiMuatNaik }>('/dms/konfigurasi/muat-naik'),
    parameter: () => api.get<{ data: Parameter[] }>('/dms/konfigurasi'),
    kuota: () => api.get<{ data: Kuota }>('/dms/kuota'),
    kuarantin: () => api.get<{ data: Kuarantin[] }>('/dms/kuarantin'),
    musnahKuarantin: (id: number) => api.delete<{ message: string }>(`/dms/kuarantin/${id}`),
    pelupusan: () => api.get<{ data: Pelupusan[] }>('/dms/pelupusan'),
};

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * One file per request so each file gets its own progress bar and its own
 * accept/reject result (SRS-DMS-02); `id_transaksi` lets the server enforce
 * the cumulative per-transaction limit across those requests (SRS-DMS-06).
 */
export async function muatNaikFail(
    path: string,
    file: File,
    fields: Record<string, string | string[] | Record<string, string> | undefined>,
    onProgress: (fraction: number) => void,
    fieldName = 'fail[]',
): Promise<KeputusanFail> {
    if (!getCookie('XSRF-TOKEN')) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    }
    const form = new FormData();
    form.append(fieldName, file);
    Object.entries(fields).forEach(([key, value]) => {
        if (value === undefined || value === '') return;
        if (Array.isArray(value)) value.forEach((v) => form.append(`${key}[]`, v));
        else if (typeof value === 'object') Object.entries(value).forEach(([k, v]) => v !== '' && form.append(`${key}[${k}]`, v));
        else form.append(key, value);
    });

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', `/api/v1${path}`);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        const xsrf = getCookie('XSRF-TOKEN');
        if (xsrf) xhr.setRequestHeader('X-XSRF-TOKEN', xsrf);
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
        xhr.onerror = () => reject(new ApiError(0, { message: 'Sambungan terputus semasa muat naik.' }));
        xhr.onload = () => {
            let body: { keputusan?: KeputusanFail[]; message?: string; sebab?: string; errors?: Record<string, string[]> } | null = null;
            try {
                body = JSON.parse(xhr.responseText);
            } catch {
                body = null;
            }
            const result = body?.keputusan?.[0];
            if (result) {
                resolve(result);
            } else if (xhr.status >= 200 && xhr.status < 300) {
                resolve({ indeks: 0, nama_fail: file.name, status: 'diterima' });
            } else if (body?.sebab) {
                resolve({ indeks: 0, nama_fail: file.name, status: 'ditolak', sebab: body.sebab, mesej: body.message });
            } else if (xhr.status === 413) {
                resolve({ indeks: 0, nama_fail: file.name, status: 'ditolak', sebab: 'terlalu_besar', mesej: `Fail "${file.name}" melebihi had saiz pelayan.` });
            } else {
                reject(new ApiError(xhr.status, body));
            }
        };
        xhr.send(form);
    });
}
