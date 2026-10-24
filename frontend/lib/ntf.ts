import { api } from './api';

// MOD-NTF (Notifikasi) API client + shared labels. Backend: app/Modules/Ntf, mounted at /api/v1/ntf.

export type Keutamaan = 'kritikal' | 'tinggi' | 'biasa' | 'maklumat';
export type Saluran = 'dalam_sistem' | 'emel' | 'sms' | 'whatsapp';

export const KEUTAMAAN_LABEL: Record<Keutamaan, string> = {
    kritikal: 'Kritikal',
    tinggi: 'Tinggi',
    biasa: 'Biasa',
    maklumat: 'Maklumat',
};

export const KEUTAMAAN_BADGE: Record<Keutamaan, 'danger' | 'warning' | 'info' | 'neutral'> = {
    kritikal: 'danger',
    tinggi: 'warning',
    biasa: 'info',
    maklumat: 'neutral',
};

export const SALURAN_LABEL: Record<Saluran, string> = {
    dalam_sistem: 'Dalam sistem',
    emel: 'E-mel',
    sms: 'SMS',
    whatsapp: 'WhatsApp',
};

export const STATUS_LABEL: Record<string, string> = {
    beratur: 'Dalam baris gilir',
    ditahan: 'Ditahan (ringkasan)',
    dihantar: 'Dihantar',
    diterima: 'Diterima',
    dibaca: 'Dibaca',
    gagal: 'Gagal (cuba semula)',
    gagal_muktamad: 'Gagal muktamad',
};

export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface Notifikasi {
    id: number;
    mesej_id: number;
    jenis: { kod: string; nama: string };
    modul: string;
    keutamaan: Keutamaan;
    subjek: string;
    kandungan: string;
    pautan: string | null;
    dibaca: boolean;
    dibaca_pada: string | null;
    diarkib: boolean;
    dieskalasi: boolean;
    created_at: string;
}

export interface InboxFilters {
    folder?: 'peti' | 'arkib';
    status?: '' | 'belum_baca' | 'dibaca';
    jenis?: string;
    keutamaan?: string;
    modul?: string;
    dari?: string;
    hingga?: string;
    q?: string;
    page?: number;
}

export type Tindakan = 'baca' | 'belum_baca' | 'arkib' | 'nyaharkib' | 'padam';

function qs(params: Record<string, string | number | undefined>): string {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '' && value !== null) search.set(key, String(value));
    }
    const s = search.toString();
    return s ? `?${s}` : '';
}

export function fetchInbox(filters: InboxFilters) {
    return api.get<{ data: Notifikasi[]; meta: PageMeta }>(`/ntf/notifikasi${qs({ ...filters })}`);
}

export function fetchInboxSummary() {
    return api.get<{ data: { belum_baca: number; terkini: Notifikasi[] } }>('/ntf/notifikasi/ringkas');
}

export function fetchInboxOptions() {
    return api.get<{
        data: {
            jenis: Array<{ value: string; label: string }>;
            modul: string[];
            keutamaan: Array<{ value: Keutamaan; label: string }>;
        };
    }>('/ntf/notifikasi/pilihan');
}

export function actOnNotifications(tindakan: Tindakan, ids: number[] | 'semua') {
    return api.post<{ message: string; data: { dikemas_kini: number; belum_baca: number } }>(
        '/ntf/notifikasi/tindakan',
        ids === 'semua' ? { tindakan, semua: true } : { tindakan, ids },
    );
}

export type NtfAbilities = Record<
    'ntf.jenis.lihat' | 'ntf.jenis.kemas_kini' | 'ntf.pemantauan.lihat' | 'ntf.saluran.kemas_kini' | 'ntf.pengumuman.cipta',
    boolean
>;

export function fetchAbilities() {
    return api.get<{ data: NtfAbilities }>('/ntf/kebenaran');
}

// --- Preferences (SRS-NTF-09/10/20)

export interface Keutamaanku {
    jenis_id: number;
    kod: string;
    nama: string;
    modul: string;
    keutamaan: Keutamaan;
    wajib: boolean;
    boleh_ringkasan: boolean;
    aktif: boolean;
    saluran: Saluran[];
    saluran_lalai: Saluran[];
    ringkasan: boolean;
}

export function fetchPreferences() {
    return api.get<{ data: Keutamaanku[]; meta: { saluran_tersedia: Saluran[] } }>('/ntf/keutamaan');
}

export function savePreferences(items: Array<{ jenis_id: number; aktif: boolean; saluran: Saluran[]; ringkasan: boolean }>) {
    return api.put<{ message: string }>('/ntf/keutamaan', { items });
}

// --- Admin: types & templates (SRS-NTF-03/05)

export interface Jenis {
    id: number;
    kod: string;
    nama: string;
    modul: string;
    keutamaan: Keutamaan;
    wajib: boolean;
    saluran_lalai: Saluran[];
    kekerapan: 'serta_merta' | 'ringkasan';
    tempoh_eskalasi_minit: number | null;
    tempoh_pendiaman_minit: number;
    hari_peringatan: number[] | null;
    medan: string[];
    medan_sensitif: string[];
    aktif: boolean;
    versi_templat?: number | null;
    bilangan_peraturan?: number;
}

export interface Templat {
    id: number;
    versi: number;
    subjek: string;
    kandungan: string;
    aktif: boolean;
    catatan: string | null;
    dicipta_oleh: number | null;
    created_at: string | null;
}

export interface Peraturan {
    id: number;
    sasaran: 'peranan' | 'kebenaran' | 'bahagian' | 'daerah' | 'pengguna';
    nilai: string;
    aktif: boolean;
}

export interface JenisDetail extends Jenis {
    templat: Templat[];
    peraturan: Peraturan[];
    medan_sistem: string[];
    medan_sensitif_global: string[];
}

export const SASARAN_LABEL: Record<Peraturan['sasaran'], string> = {
    peranan: 'Peranan',
    kebenaran: 'Kebenaran',
    bahagian: 'Bahagian',
    daerah: 'Daerah',
    pengguna: 'Pengguna (ID)',
};

export function fetchTypes() {
    return api.get<{ data: Jenis[] }>('/ntf/jenis');
}

export function fetchType(id: number | string) {
    return api.get<{ data: JenisDetail }>(`/ntf/jenis/${id}`);
}

export function updateType(id: number, payload: Partial<Jenis>) {
    return api.put<{ message: string; data: Jenis }>(`/ntf/jenis/${id}`, payload);
}

export function saveTemplate(id: number, payload: { subjek: string; kandungan: string; catatan?: string }) {
    return api.post<{ message: string; data: { id: number; versi: number } }>(`/ntf/jenis/${id}/templat`, payload);
}

export function activateTemplate(id: number, templatId: number) {
    return api.post<{ message: string }>(`/ntf/jenis/${id}/templat/${templatId}/aktif`);
}

export function previewTemplate(id: number, payload: { subjek: string; kandungan: string; data?: Record<string, string> }) {
    return api.post<{ data: { subjek: string; kandungan: string; medan_tidak_dikenali: string[] } }>(
        `/ntf/jenis/${id}/templat/pratonton`,
        payload,
    );
}

export function addRule(id: number, payload: { sasaran: Peraturan['sasaran']; nilai: string }) {
    return api.post<{ message: string }>(`/ntf/jenis/${id}/peraturan`, payload);
}

export function deleteRule(id: number, ruleId: number) {
    return api.delete<{ message: string }>(`/ntf/jenis/${id}/peraturan/${ruleId}`);
}

// --- Monitoring (SRS-NTF-15/23)

export interface Penghantaran {
    id: number;
    mesej_id: number;
    jenis: { kod: string; nama: string };
    subjek: string;
    keutamaan: Keutamaan;
    penerima: { id: number; nama: string } | null;
    saluran: Saluran;
    status: string;
    ringkasan: boolean;
    bilangan_cubaan: number;
    kod_ralat: string | null;
    mesej_ralat: string | null;
    tarikh_beratur: string | null;
    tarikh_hantar: string | null;
    tarikh_terima: string | null;
    tarikh_baca: string | null;
    tarikh_gagal: string | null;
}

export interface Pemantauan {
    tempoh_hari: number;
    baris_gilir: { beratur: number; ditahan_ringkasan: number; menunggu_cuba_semula: number; redis: number | null };
    saluran: Array<{
        saluran: Saluran;
        label: string;
        jumlah: number;
        berjaya: number;
        gagal_muktamad: number;
        dalam_proses: number;
        kadar_kejayaan: number | null;
    }>;
    kegagalan_terkini: Penghantaran[];
    eskalasi: number;
}

export function fetchMonitoring(hari: number) {
    return api.get<{ data: Pemantauan }>(`/ntf/pemantauan?hari=${hari}`);
}

export function fetchDeliveries(filters: { saluran?: string; status?: string; jenis?: string; page?: number }) {
    return api.get<{ data: Penghantaran[]; meta: PageMeta }>(`/ntf/penghantaran${qs(filters)}`);
}

export function retryDelivery(id: number) {
    return api.post<{ message: string }>(`/ntf/penghantaran/${id}/cuba-semula`);
}

// --- Announcements (SRS-NTF-24)

export interface Kumpulan {
    peranan: Array<{ value: string; label: string }>;
    bahagian: Array<{ value: string; label: string }>;
    daerah: Array<{ value: string; label: string }>;
}

export function fetchGroups() {
    return api.get<{ data: Kumpulan }>('/ntf/pengumuman/kumpulan');
}

export function sendAnnouncement(payload: {
    tajuk: string;
    kandungan: string;
    pautan?: string;
    sasaran: Array<{ jenis: 'peranan' | 'bahagian' | 'daerah' | 'pengguna'; nilai: string }>;
}) {
    return api.post<{ message: string; data: { penerima: number; dilangkau: number } }>('/ntf/pengumuman', payload);
}

/** "5 minit lalu" style relative time in Malay. */
export function masaRelatif(iso: string): string {
    const diff = Math.max(0, Date.now() - new Date(iso).getTime());
    const minit = Math.floor(diff / 60000);
    if (minit < 1) return 'Baru sahaja';
    if (minit < 60) return `${minit} minit lalu`;
    const jam = Math.floor(minit / 60);
    if (jam < 24) return `${jam} jam lalu`;
    const hari = Math.floor(jam / 24);
    if (hari < 7) return `${hari} hari lalu`;
    return tarikhMasa(iso);
}

export function tarikhMasa(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('ms-MY', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
