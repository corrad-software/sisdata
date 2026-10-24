import { api, ApiError } from './api';

/** MOD-AUD (Audit Trail) API helpers — /api/v1/aud. */

export interface AuditFieldChange {
    nama_medan: string;
    nilai_asal: string | null;
    nilai_baharu: string | null;
    sensitif_ditapis: boolean;
}

export interface AuditRecord {
    id: number;
    no_urutan: number;
    sumber: 'dalam_talian' | 'arkib';
    tarikh_masa_utc: string;
    tarikh_masa_tempatan: string;
    id_pengguna: number | null;
    pengecam_pengguna: string | null;
    peranan_semasa: string[];
    id_sesi: string | null;
    alamat_ip: string | null;
    user_agent: string | null;
    jenis_peranti: string;
    lokasi_anggaran: string | null;
    saluran_capaian: string;
    modul: string;
    nama_entiti: string | null;
    id_rekod: string | null;
    jenis_tindakan: string;
    status_transaksi: string;
    sebab_perubahan: string | null;
    id_korelasi: string | null;
    klasifikasi: string | null;
    meta: Record<string, unknown> | null;
    cincangan_sebelum: string;
    cincangan_rekod: string;
    perubahan: AuditFieldChange[];
}

export interface AuditPage {
    data: AuditRecord[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
}

export type AuditFilters = Partial<
    Record<
        | 'id_pengguna'
        | 'pengguna'
        | 'peranan'
        | 'modul'
        | 'nama_entiti'
        | 'id_rekod'
        | 'jenis_tindakan'
        | 'alamat_ip'
        | 'status_transaksi'
        | 'saluran_capaian'
        | 'klasifikasi'
        | 'dari'
        | 'hingga'
        | 'sumber',
        string
    >
>;

export type ExportFormat = 'csv' | 'xlsx' | 'json' | 'pdf';

export interface AuditOptions {
    modul: string[];
    jenis_tindakan: string[];
    status_transaksi: string[];
    saluran_capaian: string[];
    nama_entiti: string[];
}

export interface IntegrityCheck {
    id: number;
    dimulakan_pada: string;
    selesai_pada: string;
    status: 'lulus' | 'gagal';
    bilangan_disemak: number;
    no_urutan_terakhir: number | null;
    no_urutan_rosak: number | null;
    butiran: string | null;
    dicetuskan_oleh: 'jadual' | 'manual';
}

export interface IntegrityStatus {
    rantaian: { dalam_talian: number; arkib: number; no_urutan_terakhir: number | null };
    jadual_cron: string;
    semakan: IntegrityCheck[];
}

export interface RetentionPolicy {
    tempoh_dalam_talian_bulan: number;
    tempoh_arkib_tahun: number | null;
    catatan: string | null;
    minimum_bulan: number;
    dikemas_kini_pada: string | null;
}

export const REPORT_TYPES = {
    sulit_rahsia: 'Capaian data Sulit & Rahsia',
    luar_waktu: 'Aktiviti di luar waktu pejabat',
    kelompok: 'Aktiviti berkelompok luar biasa',
    eksport_besar: 'Eksport data bervolum besar',
    capaian_audit: 'Capaian kepada rekod audit',
    log_masuk_gagal: 'Log masuk gagal & capaian ditolak',
} as const;

export type ReportType = keyof typeof REPORT_TYPES;

export interface BulkActivityRow {
    id_pengguna: number;
    pengecam_pengguna: string | null;
    jam: string;
    bilangan: number;
    tindakan: string;
}

export interface AuditReport {
    jenis: ReportType;
    dari: string;
    hingga: string;
    jumlah: number;
    parameter: Record<string, unknown>;
    rekod: AuditRecord[] | BulkActivityRow[];
}

/** Malay labels for codes stored in the trail. */
export const ACTION_LABELS: Record<string, string> = {
    cipta: 'Cipta',
    kemas_kini: 'Kemas kini',
    padam: 'Padam',
    pulih: 'Pulih',
    lihat: 'Lihat',
    carian: 'Carian',
    eksport: 'Eksport',
    muat_naik: 'Muat naik',
    muat_turun: 'Muat turun',
    pratonton: 'Pratonton',
    cetak: 'Cetak',
    lulus: 'Lulus',
    tolak: 'Tolak',
    log_masuk: 'Log masuk',
    log_keluar: 'Log keluar',
    tukar_status: 'Tukar status',
    tukar_peranan: 'Tukar peranan',
    capaian_ditolak: 'Capaian ditolak',
    capaian_arkib: 'Capaian arkib',
    lihat_kronologi: 'Lihat kronologi',
    lihat_laporan: 'Lihat laporan',
    semak_integriti: 'Semak integriti',
    arkib: 'Arkib',
    jana_laporan: 'Jana laporan',
    jana_kunci_api: 'Jana kunci API',
    arahan_ai: 'Arahan AI',
};

export const CHANNEL_LABELS: Record<string, string> = {
    web: 'Web',
    mudah_alih: 'Mudah alih',
    api: 'API',
    sistem: 'Sistem',
};

export const STATUS_LABELS: Record<string, string> = {
    berjaya: 'Berjaya',
    gagal: 'Gagal',
    ditolak: 'Ditolak',
};

export function label(map: Record<string, string>, code: string | null | undefined): string {
    if (!code) return '—';
    return map[code] ?? code.replace(/_/g, ' ');
}

function query(params: Record<string, string | number | undefined>): string {
    const qs = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') qs.set(key, String(value));
    }
    const s = qs.toString();
    return s ? `?${s}` : '';
}

export function searchAudit(filters: AuditFilters, page: number, perPage = 25) {
    return api.get<AuditPage>(`/aud/rekod${query({ ...filters, page, per_page: perPage })}`);
}

export function fetchAuditRecord(id: number, sumber?: string) {
    return api.get<{ data: AuditRecord }>(`/aud/rekod/${id}${query({ sumber })}`);
}

export function fetchChronology(namaEntiti: string, idRekod: string) {
    return api.get<{ data: AuditRecord[]; meta: { termasuk_arkib: boolean } }>(
        `/aud/kronologi${query({ nama_entiti: namaEntiti, id_rekod: idRekod })}`,
    );
}

export function fetchAuditOptions() {
    return api.get<{ data: AuditOptions }>('/aud/pilihan');
}

export function fetchIntegrity() {
    return api.get<{ data: IntegrityStatus }>('/aud/integriti');
}

export function runIntegrityCheck() {
    return api.post<{ data: { status: 'lulus' | 'gagal'; checked: number; bad: number | null; detail: string | null }; message: string }>(
        '/aud/integriti/semak',
    );
}

export function fetchReport(type: ReportType, dari: string, hingga: string) {
    return api.get<{ data: AuditReport }>(`/aud/laporan/${type}${query({ dari, hingga })}`);
}

export function fetchRetention() {
    return api.get<{ data: RetentionPolicy }>('/aud/retensi');
}

export function updateRetention(payload: { tempoh_dalam_talian_bulan: number; tempoh_arkib_tahun: number | null; catatan: string | null }) {
    return api.put<{ data: RetentionPolicy; message: string }>('/aud/retensi', payload);
}

/**
 * Downloads an export package (ZIP with the data file + metadata.json).
 * Returns the SHA-256 checksum the API computed for the package.
 */
export async function downloadExport(filters: AuditFilters, format: ExportFormat): Promise<{ checksum: string; count: number }> {
    const response = await fetch(`/api/v1/aud/eksport${query({ ...filters, format })}`, {
        credentials: 'include',
        headers: { Accept: 'application/zip, application/json' },
    });

    if (!response.ok) {
        const body = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        throw new ApiError(response.status, body);
    }

    const blob = await response.blob();
    const disposition = response.headers.get('content-disposition') ?? '';
    const filename = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? `pakej-audit.${format}.zip`;

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    return {
        checksum: response.headers.get('x-checksum-sha256') ?? '',
        count: Number(response.headers.get('x-bilangan-rekod') ?? 0),
    };
}
