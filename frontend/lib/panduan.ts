import { api } from './api';
import type { TetapanPanduan } from './types';

/*
 * SRS-GEN-08 — interactive first-time help ("panduan interaktif").
 *
 * Each tour is a list of steps that point at stable `data-panduan="…"`
 * attributes in the UI. At run time <PanduanInteraktif /> keeps a step only
 * when its element is on screen AND the user holds the step's permission,
 * so every RBAC role automatically gets a tour tailored to what it can see.
 * Bump a tour's `versi` to show it again to everyone.
 */

export interface KonteksPanduan {
    nama: string;
    peranan: string[];
    can: (...permissions: string[]) => boolean;
    /** Labels of the sidebar modules this user can open (read from the DOM). */
    modul: string[];
}

export interface LangkahPanduan {
    /** `data-panduan` key(s); the first one present on screen is used. Omit for a centred bubble. */
    sasaran?: string | string[];
    tajuk: string;
    teks: string;
    /** If no target is on screen (e.g. the off-canvas sidebar on a phone), show the step centred instead of dropping it. */
    tengahJikaTiada?: boolean;
    /** Keep the step only if the user holds any of these permissions. */
    kebenaran?: string[];
    side?: 'top' | 'right' | 'bottom' | 'left';
    align?: 'start' | 'center' | 'end';
}

export interface Panduan {
    kunci: string;
    versi: string;
    /** Shown in the user menu ("Ulang panduan halaman ini"). */
    nama: string;
    padan: (pathname: string) => boolean;
    /** Wait (up to a few seconds) for any of these targets before starting — content that loads async. */
    tunggu?: string[];
    langkah: (k: KonteksPanduan) => LangkahPanduan[];
}

/** Escape anything that didn't come from this file (driver.js renders descriptions as HTML). */
export function escapeHtml(s: string): string {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);
}

export const LABEL_PERANAN: Record<string, string> = {
    pentadbir_sistem: 'Pentadbir Sistem',
    pentadbir_aplikasi: 'Pentadbir Aplikasi',
    pegawai_operasi: 'Pegawai Operasi',
    pelulus: 'Pelulus',
    pengguna_paparan: 'Pengguna Paparan',
    juruaudit: 'Juruaudit',
    pemegang_taruh_terhad: 'Pemegang Taruh Terhad',
};

/** Priority when a user holds several roles: the one that shapes their daily work most. */
const KEUTAMAAN_PERANAN = ['pentadbir_sistem', 'pentadbir_aplikasi', 'pegawai_operasi', 'pelulus', 'juruaudit', 'pengguna_paparan', 'pemegang_taruh_terhad'];

export function perananUtama(peranan: string[]): string | null {
    return KEUTAMAAN_PERANAN.find((p) => peranan.includes(p)) ?? peranan[0] ?? null;
}

function tipPeranan(k: KonteksPanduan): LangkahPanduan {
    return { ...tipPerananAsas(k), tengahJikaTiada: true };
}

function tipPerananAsas(k: KonteksPanduan): LangkahPanduan {
    switch (perananUtama(k.peranan)) {
        case 'pentadbir_sistem':
        case 'pentadbir_aplikasi':
            return {
                sasaran: ['nav-users', 'nav-rbc', 'nav-pentadbiran'],
                tajuk: 'Tip untuk pentadbir',
                teks: 'Urus akaun di <b>Pengguna</b>, peranan dan kebenaran di <b>Capaian</b>, serta parameter, kod rujukan dan kesihatan sistem di <b>Pentadbiran Sistem</b>.',
            };
        case 'pegawai_operasi':
            return {
                sasaran: ['nav-pro', 'nav-pjk', 'nav-ptn'],
                tajuk: 'Tip untuk pegawai operasi',
                teks: 'Mulakan dengan <b>Profil Hartanah</b> untuk mencari lot dan membuka profil 360°. Urus perjanjian di <b>Pajakan &amp; Kontrak</b> dan lawatan tapak di <b>Pemantauan Tanah</b>.',
            };
        case 'pelulus':
            return {
                sasaran: ['nav-wfe', 'nav-inbox'],
                tajuk: 'Tip untuk pelulus',
                teks: 'Permohonan yang menunggu keputusan anda berada di <b>Workflow → Tugasan Saya</b> (juga dalam <b>Inbox Tugasan</b>). Lulus, tolak atau kembalikan dengan justifikasi.',
            };
        case 'juruaudit':
            return {
                sasaran: ['nav-aud'],
                tajuk: 'Tip untuk juruaudit',
                teks: 'Semua jejak aktiviti boleh dicari dan dieksport di <b>Audit Trail</b>, termasuk kronologi rekod dan semakan integriti rantaian audit.',
            };
        case 'pengguna_paparan':
            return {
                sasaran: ['nav-utama', 'nav-lap'],
                tajuk: 'Tip untuk pengguna paparan',
                teks: 'Lihat petunjuk prestasi di <b>Papan Pemuka</b> dan jana laporan standard di <b>Laporan &amp; Analitik</b>. Paparan anda mengikut skop capaian anda.',
            };
        case 'pemegang_taruh_terhad':
            return {
                sasaran: ['nav-tkt'],
                tajuk: 'Tip untuk pemegang taruh',
                teks: 'Gunakan <b>Tiket Sokongan</b> untuk mengemukakan pertanyaan dan menjejak status permohonan anda.',
            };
        default:
            return {
                tajuk: 'Sedia untuk bermula',
                teks: 'Pilih modul daripada menu di sebelah kiri. Jika anda tidak menemui modul yang diperlukan, hubungi pentadbir sistem untuk semakan capaian.',
            };
    }
}

export const SENARAI_PANDUAN: Panduan[] = [
    {
        kunci: 'pengenalan',
        versi: '1',
        nama: 'Pengenalan SISDATA',
        padan: (p) => p === '/admin',
        tunggu: ['sidebar', 'menu-pengguna'],
        langkah: (k) => {
            const peranan = perananUtama(k.peranan);
            const modul = k.modul.length ? k.modul.slice(0, 8).map(escapeHtml).join(', ') + (k.modul.length > 8 ? ' dan lain-lain' : '') : '';
            return [
                {
                    tajuk: `Selamat datang ke SISDATA${k.nama ? `, ${escapeHtml(k.nama)}` : ''}`,
                    teks:
                        'Sistem Pengurusan Data Hartanah MAIS. Panduan ringkas ini menunjukkan bahagian utama sistem' +
                        (peranan && LABEL_PERANAN[peranan] ? ` untuk peranan anda sebagai <b>${LABEL_PERANAN[peranan]}</b>` : '') +
                        '. Anda boleh menutupnya pada bila-bila masa.',
                },
                {
                    sasaran: ['sidebar', 'buka-menu'],
                    side: 'right',
                    tajuk: 'Menu modul',
                    teks: modul
                        ? `Menu ini hanya memaparkan modul yang anda dibenarkan buka (${k.modul.length}): ${modul}.`
                        : 'Menu ini hanya memaparkan modul yang anda dibenarkan buka.',
                },
                {
                    sasaran: 'portal-pautan',
                    tajuk: 'Pautan pantas',
                    teks: 'Halaman utama anda menyenaraikan pautan pantas ke fungsi yang boleh anda gunakan.',
                },
                {
                    sasaran: 'carian-global',
                    tajuk: 'Carian global',
                    teks: 'Cari lot, hak milik, pemilik, perjanjian dan rekod lain merentas modul. Hasil carian mengikut skop capaian anda.',
                },
                {
                    sasaran: 'notifikasi',
                    side: 'bottom',
                    align: 'end',
                    tajuk: 'Notifikasi',
                    teks: 'Tugasan baharu, peringatan tarikh akhir dan pengumuman muncul di sini. Bilangan merah menunjukkan notifikasi belum dibaca.',
                },
                {
                    sasaran: 'bantuan',
                    side: 'bottom',
                    tajuk: 'Bantuan',
                    teks: 'Perlukan bantuan? Buka tiket sokongan terus dari halaman ini — maklumat skrin semasa dilampirkan secara automatik.',
                },
                {
                    sasaran: 'paparan',
                    side: 'bottom',
                    tajuk: 'Paparan',
                    teks: 'Besarkan saiz teks, tukar ke mod gelap atau lihat pilihan bahasa mengikut keselesaan anda.',
                },
                {
                    sasaran: 'ulang-panduan',
                    side: 'bottom',
                    tajuk: 'Panduan halaman',
                    teks: 'Klik butang <b>?</b> ini untuk mengulang panduan halaman yang sedang dibuka.',
                },
                {
                    sasaran: 'menu-pengguna',
                    side: 'bottom',
                    align: 'end',
                    tajuk: 'Menu pengguna',
                    teks: 'Di sini anda boleh menghidupkan atau mematikan <b>Panduan interaktif</b>, mengulang panduan, menukar kata laluan dan log keluar.',
                },
                tipPeranan(k),
            ];
        },
    },
    {
        kunci: 'dsh.eksekutif',
        versi: '1',
        nama: 'Dashboard Eksekutif',
        padan: (p) => p === '/admin',
        tunggu: ['dsh-widget', 'dsh-tindakan'],
        langkah: () => [
            { sasaran: 'dsh-aras', tajuk: 'Aras dashboard', teks: 'Tukar antara aras pengurusan yang tersedia untuk peranan anda. Aras lalai ditanda.' },
            { sasaran: 'dsh-penapis', tajuk: 'Penapis', teks: 'Tapis petunjuk mengikut tempoh, daerah atau kategori. Semua widget dikemas kini serentak.' },
            { sasaran: 'dsh-widget', tajuk: 'Petunjuk prestasi', teks: 'Klik nilai indikator untuk melihat senarai rekod di sebaliknya.' },
            { sasaran: 'dsh-tindakan', tajuk: 'Susun atur & eksport', teks: 'Ubah susun atur widget, simpan paparan kegemaran, langgan ringkasan berkala atau eksport ke PDF/Excel.' },
        ],
    },
    {
        kunci: 'pro.senarai',
        versi: '1',
        nama: 'Profil Hartanah — senarai lot',
        padan: (p) => p === '/admin/pro/lot',
        tunggu: ['pro-jadual', 'pro-carian'],
        langkah: () => [
            { sasaran: 'modul-nav', tajuk: 'Profil Hartanah', teks: 'Rekod induk setiap lot tanah MAIS. Tab di sini membuka import data, duplikasi, kualiti data dan lain-lain mengikut capaian anda.' },
            { sasaran: 'pro-carian', tajuk: 'Cari lot', teks: 'Taip no. lot, mukim, zoning atau tag. Senarai ditapis secara langsung.' },
            { sasaran: 'pro-penapis', tajuk: 'Penapis lanjut', teks: 'Tapis mengikut daerah, status, klasifikasi, kategori tanah dan banyak lagi.' },
            { sasaran: 'pro-paparan', tajuk: 'Jadual atau peta', teks: 'Lihat hasil carian sebagai jadual atau terus di atas peta.' },
            { sasaran: 'pro-jadual', tajuk: 'Profil 360°', teks: 'Klik no. lot untuk membuka profil 360° — hak milik, pajakan, cukai, pemantauan, aset, kes dan dokumen dalam satu halaman.' },
            { sasaran: 'pro-tambah', kebenaran: ['pro.lot.cipta'], tajuk: 'Tambah lot', teks: 'Daftar lot baharu. Perubahan medan kritikal memerlukan pengesahan pegawai kedua.' },
        ],
    },
    {
        kunci: 'pro.profil',
        versi: '1',
        nama: 'Profil Hartanah 360°',
        padan: (p) => /^\/admin\/pro\/lot\/[0-9a-fA-F-]{36}$/.test(p),
        tunggu: ['pro-tab'],
        langkah: () => [
            { sasaran: 'pro-profil', tajuk: 'Ringkasan lot', teks: 'Nama hartanah, lokasi, status lot, klasifikasi data dan status spatial. Bendera amaran dipaparkan di sini.' },
            { sasaran: 'pro-tab', side: 'bottom', tajuk: 'Tab profil 360°', teks: 'Setiap tab menghimpunkan maklumat daripada modul lain — identiti, hak milik, pajakan, cukai, pemantauan, aset, kes dan dokumen.' },
            { sasaran: 'pro-tindakan', tajuk: 'Tindakan', teks: 'Kemas kini, cetak, eksport atau semak kualiti rekod mengikut kebenaran anda.' },
            { sasaran: 'pro-garis-masa', side: 'left', tajuk: 'Garis masa', teks: 'Sejarah peristiwa penting lot ini dari semua modul, terkini di atas.' },
        ],
    },
    {
        kunci: 'gis.peta',
        versi: '1',
        nama: 'Peta GIS',
        padan: (p) => p === '/admin/gis',
        tunggu: ['gis-peta'],
        langkah: () => [
            { sasaran: 'gis-peta', side: 'left', tajuk: 'Peta GIS', teks: 'Zum dan seret untuk meneroka. <b>Klik pada lot</b> untuk mengenal pasti lot itu — butirannya dipaparkan dalam tab Atribut.' },
            { sasaran: 'gis-tab-lapisan', tajuk: 'Lapisan', teks: 'Hidupkan atau matikan lapisan tematik, ubah susunan dan ketelusannya, serta tukar peta asas.' },
            { sasaran: 'gis-tab-carian', tajuk: 'Carian', teks: 'Cari lot mengikut no. lot, mukim atau koordinat dan terbang terus ke lokasinya.' },
            { sasaran: 'gis-tab-atribut', tajuk: 'Atribut (kenal pasti)', teks: 'Maklumat lot yang anda klik pada peta, dengan pautan ke profil 360°.' },
            { sasaran: 'gis-tab-ukur', tajuk: 'Ukur', teks: 'Ukur jarak atau keluasan terus di atas peta. Klik untuk menambah bucu; klik dua kali untuk selesai.' },
            { sasaran: 'gis-tab-paparan', tajuk: 'Paparan & cetak', teks: 'Simpan paparan peta kegemaran dan cetak peta dengan tajuk, skala dan legenda.' },
        ],
    },
    {
        kunci: 'wfe.tugasan',
        versi: '1',
        nama: 'Workflow — Tugasan Saya',
        padan: (p) => p === '/admin/wfe/tugasan',
        tunggu: ['wfe-tindakan', 'wfe-senarai'],
        langkah: () => [
            { sasaran: 'wfe-penapis', tajuk: 'Tugasan anda', teks: '<b>Menunggu tindakan</b> ialah permohonan yang perlu anda putuskan; <b>Telah diambil tindakan</b> ialah sejarah keputusan anda.' },
            { sasaran: 'wfe-senarai', tajuk: 'Senarai tugasan', teks: 'Klik perkara untuk melihat butiran penuh, dokumen dan sejarah aliran sebelum membuat keputusan. Baki SLA menunjukkan masa yang tinggal.' },
            { sasaran: 'wfe-tindakan', kebenaran: ['wfe.tugasan.lulus'], tajuk: 'Lulus, tolak atau kembalikan', teks: 'Tindakan pantas untuk setiap tugasan kelulusan.' },
            {
                kebenaran: ['wfe.tugasan.lulus'],
                tajuk: 'Keputusan & justifikasi',
                teks: 'Pilih <b>Lulus</b>, <b>Tolak</b> atau <b>Kembalikan</b> pada tugasan. Penolakan dan pengembalian <b>wajib</b> disertakan justifikasi; sesetengah peringkat juga memerlukan justifikasi untuk kelulusan atau pengesahan MFA. Setiap keputusan direkodkan dalam jejak audit.',
            },
        ],
    },
    {
        kunci: 'pjk.kontrak',
        versi: '1',
        nama: 'Pajakan & Kontrak',
        padan: (p) => p === '/admin/pjk/kontrak',
        tunggu: ['pjk-senarai'],
        langkah: () => [
            { sasaran: 'modul-nav', tajuk: 'Pajakan & Kontrak', teks: 'Urus premis, penyewa, permohonan, perjanjian, bayaran dan rekonsiliasi dari tab ini.' },
            { sasaran: 'pjk-carian', tajuk: 'Cari perjanjian', teks: 'Cari mengikut no. perjanjian, nama penyewa atau premis.' },
            { sasaran: 'pjk-penapis', tajuk: 'Penapis', teks: 'Tapis mengikut status, daerah, kategori hartanah, perjanjian yang hampir tamat atau yang ada tunggakan.' },
            { sasaran: 'pjk-senarai', tajuk: 'Senarai perjanjian', teks: 'Klik perjanjian untuk melihat jadual bayaran, pembaharuan dan dokumen.' },
            { sasaran: 'pjk-daftar', kebenaran: ['pjk.kontrak.cipta'], tajuk: 'Daftar perjanjian', teks: 'Daftar perjanjian pajakan atau sewaan baharu.' },
        ],
    },
    {
        kunci: 'adu',
        versi: '1',
        nama: 'Aduan & Isu Tanah',
        padan: (p) => p === '/admin/adu' || p === '/admin/adu/aduan',
        tunggu: ['adu-ringkasan', 'adu-senarai'],
        langkah: () => [
            { sasaran: 'modul-nav', tajuk: 'Aduan & Isu Tanah', teks: 'Papan pemuka, barisan aduan, rekod aduan baharu dan laporan aduan.' },
            { sasaran: 'adu-penapis', tajuk: 'Penapis', teks: 'Tapis ringkasan mengikut tempoh, daerah, kategori dan saluran aduan.' },
            { sasaran: 'adu-ringkasan', tajuk: 'Ringkasan', teks: 'Aduan aktif, melepasi SLA dan diselesaikan. Klik kad untuk membuka senarai aduan berkenaan.' },
            { sasaran: 'adu-carian', tajuk: 'Cari aduan', teks: 'Cari mengikut no. rujukan, keterangan atau lokasi.' },
            { sasaran: 'adu-penapis-pantas', tajuk: 'Penapis pantas', teks: 'Tunjukkan hanya aduan tugasan anda, yang melepasi SLA dan sebagainya.' },
            { sasaran: 'adu-senarai', tajuk: 'Barisan aduan', teks: 'Klik aduan untuk menyiasat, mengagih tugasan dan merekod penyelesaian.' },
            { sasaran: 'adu-rekod', kebenaran: ['adu.aduan.cipta'], tajuk: 'Rekod aduan', teks: 'Rekod aduan yang diterima melalui kaunter, telefon atau e-mel.' },
        ],
    },
    {
        kunci: 'aud',
        versi: '1',
        nama: 'Audit Trail',
        padan: (p) => p === '/admin/aud',
        tunggu: ['aud-penapis'],
        langkah: () => [
            { sasaran: 'modul-nav', tajuk: 'Audit Trail', teks: 'Carian audit, kronologi satu rekod, laporan audit serta semakan integriti dan retensi.' },
            { sasaran: 'aud-penapis', tajuk: 'Penapis carian', teks: 'Tapis mengikut tempoh, pengguna, modul, jenis tindakan atau rekod. Klik <b>Cari</b> untuk menjalankan carian.' },
            { sasaran: 'aud-senarai', tajuk: 'Hasil audit', teks: 'Klik baris untuk melihat nilai sebelum dan selepas perubahan.' },
            { sasaran: 'aud-eksport', kebenaran: ['aud.rekod.eksport'], tajuk: 'Eksport', teks: 'Eksport hasil carian untuk semakan luar. Setiap eksport juga direkodkan.' },
        ],
    },
    {
        kunci: 'rbc.pengguna',
        versi: '1',
        nama: 'Pengguna',
        padan: (p) => p === '/admin/users',
        tunggu: ['pengguna-senarai'],
        langkah: () => [
            { sasaran: 'pengguna-penapis', tajuk: 'Cari pengguna', teks: 'Cari mengikut nama atau e-mel, dan tapis mengikut status akaun atau peranan.' },
            { sasaran: 'pengguna-senarai', tajuk: 'Senarai pengguna', teks: 'Klik <b>Urus</b> untuk menukar status akaun, peranan, skop daerah, tempoh capaian dan menamatkan sesi.' },
            { sasaran: 'pengguna-tambah', kebenaran: ['rbc.pengguna.cipta'], tajuk: 'Tambah pengguna', teks: 'Cipta akaun SSO MAIS atau akaun dalaman. Akaun dalaman wajib menukar kata laluan pada log masuk pertama.' },
            { sasaran: 'nav-rbc', kebenaran: ['rbc.peranan.lihat'], side: 'right', tajuk: 'Capaian', teks: 'Peranan, kebenaran, matriks capaian, sesi aktif dan polisi keselamatan diurus di menu Capaian.' },
        ],
    },
    {
        kunci: 'rbc.capaian',
        versi: '1',
        nama: 'Capaian — peranan & kebenaran',
        padan: (p) => p === '/admin/rbc/peranan',
        tunggu: ['peranan-senarai'],
        langkah: () => [
            { sasaran: 'modul-nav', tajuk: 'Capaian', teks: 'Peranan, matriks capaian, log masuk, sesi aktif, pengesahan semula, pemetaan SSO dan polisi keselamatan.' },
            { sasaran: 'peranan-senarai', tajuk: 'Peranan', teks: 'Klik peranan untuk menetapkan kebenaran (modul × fungsi × tindakan) dan skop daerah atau kategori data.' },
            { sasaran: 'peranan-tambah', kebenaran: ['rbc.peranan.cipta'], tajuk: 'Tambah peranan', teks: 'Cipta peranan baharu atau klon peranan sedia ada sebagai titik mula.' },
        ],
    },
    {
        kunci: 'lap',
        versi: '1',
        nama: 'Laporan & Analitik',
        padan: (p) => p === '/admin/lap',
        tunggu: ['lap-katalog', 'lap-penapis'],
        langkah: () => [
            { sasaran: 'modul-nav', tajuk: 'Laporan & Analitik', teks: 'Katalog laporan, pembina ad hoc, laporan tersimpan, carian pintar, jadual edaran dan analitik.' },
            { sasaran: 'lap-penapis', tajuk: 'Cari laporan', teks: 'Tapis katalog mengikut kategori atau kata kunci.' },
            { sasaran: 'lap-katalog', tajuk: 'Katalog', teks: 'Hanya laporan yang anda dibenarkan dipaparkan, dan setiap laporan dijana mengikut skop capaian anda.' },
            { sasaran: 'lap-jana', tajuk: 'Jana laporan', teks: 'Jana laporan dengan parameter pilihan anda dan eksport ke PDF atau Excel.' },
        ],
    },
    {
        kunci: 'kba',
        versi: '1',
        nama: 'Pangkalan Pengetahuan',
        padan: (p) => p === '/admin/kba',
        tunggu: ['kba-soalan'],
        langkah: () => [
            { sasaran: 'kba-soalan', side: 'top', tajuk: 'Tanya soalan', teks: 'Tulis soalan tentang operasi, capaian atau aliran proses SISDATA. Jawapan berdasarkan sumber yang anda dibenarkan baca, dengan petikan.' },
            { sasaran: 'kba-contoh', tajuk: 'Contoh soalan', teks: 'Klik contoh untuk bermula dengan pantas.' },
            { sasaran: 'kba-sisi', side: 'right', tajuk: 'Sejarah perbualan', teks: 'Sambung semula perbualan terdahulu atau mulakan perbualan baharu.' },
            { sasaran: 'modul-nav', tajuk: 'Artikel & carian', teks: 'Cari artikel rujukan secara terus melalui tab Carian dan Artikel.' },
        ],
    },
];

/** Tours that belong to this page, in the order they should run. */
export function panduanUntuk(pathname: string): Panduan[] {
    return SENARAI_PANDUAN.filter((t) => t.padan(pathname));
}

export const TETAPAN_LALAI: TetapanPanduan = { aktif: true, selesai: {} };

export const panduanApi = {
    get: () => api.get<{ data: TetapanPanduan }>('/rbc/panduan').then((r) => r.data),
    kemasKini: (body: { aktif?: boolean; selesai?: { kunci: string; versi: string }; set_semula?: boolean }) =>
        api.put<{ data: TetapanPanduan }>('/rbc/panduan', body).then((r) => r.data),
};

/** localStorage flags — automation and power users can switch auto-start off per browser. */
export const KUNCI_MATI = 'sisdata.panduan.mati';
export const KUNCI_PAKSA = 'sisdata.panduan.paksa';

/**
 * Should auto-start be suppressed in this browser? True for `?panduan=0` /
 * localStorage `sisdata.panduan.mati=1`, and for automated browsers
 * (navigator.webdriver — Playwright, Selenium) unless `?panduan=1` /
 * `sisdata.panduan.paksa=1` explicitly asks for tours. Manual replay ("?")
 * always works.
 */
export function autoMulaDisekat(): boolean {
    try {
        const q = new URLSearchParams(window.location.search).get('panduan');
        if (q === '0') {
            localStorage.setItem(KUNCI_MATI, '1');
            localStorage.removeItem(KUNCI_PAKSA);
        } else if (q === '1') {
            localStorage.removeItem(KUNCI_MATI);
            localStorage.setItem(KUNCI_PAKSA, '1');
        }
        if (localStorage.getItem(KUNCI_MATI) === '1') return true;
        if (navigator.webdriver && localStorage.getItem(KUNCI_PAKSA) !== '1') return true;
    } catch {
        // Storage blocked: fall back to the webdriver check alone.
        return !!navigator.webdriver;
    }
    return false;
}
