<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Online help (SRS-DSH-25, SRS-GEN-08): FAQ, step-by-step guides and the user
 * manual link, filtered to what the user may use and ranked for the screen
 * they are on (`laluan` = current frontend path). Content is Malay
 * (SRS-GEN-09); the manual URL is an ADM parameter.
 */
class Bantuan
{
    /** @var list<array{kod: string, tajuk: string, laluan: list<string>, kebenaran: string|null, langkah: list<string>}> */
    private const PANDUAN = [
        ['kod' => 'dashboard', 'tajuk' => 'Menggunakan Dashboard Eksekutif', 'laluan' => ['/admin', '/admin/dsh'], 'kebenaran' => 'dsh.dashboard.lihat', 'langkah' => [
            'Buka menu Dashboard. Aras dashboard (Eksekutif, Pengurusan atau Operasi) dipilih secara automatik mengikut peranan anda; tukar aras melalui tab di bahagian atas jika anda dibenarkan.',
            'Gunakan penapis Tahun, Daerah, Mukim, Bahagian, Kategori tanah, Status dan Modul. Penapis dikekalkan apabila anda berpindah antara widget dan halaman dashboard.',
            'Klik nilai mana-mana indikator untuk melihat senarai rekod di sebaliknya, kemudian klik rekod untuk membuka Profil Hartanah 360° atau rekod asal.',
            'Semak cap masa "Data dikira pada" di setiap widget untuk mengetahui kesegaran data.',
        ]],
        ['kod' => 'susun_atur', 'tajuk' => 'Mengubah susun atur widget', 'laluan' => ['/admin', '/admin/dsh'], 'kebenaran' => 'dsh.dashboard.lihat', 'langkah' => [
            'Klik "Ubah susun atur" pada dashboard.',
            'Gunakan butang anak panah untuk menyusun semula widget, tukar saiz atau jenis carta, atau sembunyikan widget yang tidak diperlukan.',
            'Klik "Simpan susun atur". Susun atur disimpan untuk akaun anda sahaja; "Set semula" mengembalikan susun atur lalai.',
        ]],
        ['kod' => 'eksport', 'tajuk' => 'Mengeksport dan menyimpan paparan', 'laluan' => ['/admin', '/admin/dsh'], 'kebenaran' => 'dsh.dashboard.eksport', 'langkah' => [
            'Tetapkan penapis yang dikehendaki.',
            'Klik "Eksport" dan pilih PDF atau Excel. Fail mengandungi parameter penapis, cap masa data dan tanda air identiti anda; eksport direkod dalam jejak audit.',
            'Untuk menyimpan penapis sebagai paparan kegemaran, klik "Simpan paparan" dan beri nama.',
        ]],
        ['kod' => 'ai', 'tajuk' => 'Bertanya melalui medan prompt', 'laluan' => ['/admin', '/admin/dsh'], 'kebenaran' => 'dsh.ai.lihat', 'langkah' => [
            'Taip soalan dalam Bahasa Melayu atau Inggeris, cth. "Berapa lot tanah di Petaling tahun 2026?" atau "Tunjuk trend kadar kutipan".',
            'Jawapan dihadkan kepada skop capaian anda walaupun soalan menyebut daerah lain; setiap prompt direkod dalam jejak audit.',
            'Anda juga boleh mengubah dashboard, cth. "Tambah widget aduan terbuka" atau "Buang widget storan dokumen".',
        ]],
        ['kod' => 'inbox', 'tajuk' => 'Inbox tugasan dan draf', 'laluan' => ['/admin/dsh/inbox', '/admin/wfe/tugasan'], 'kebenaran' => 'dsh.dashboard.lihat', 'langkah' => [
            'Buka "Inbox Tugasan" untuk melihat tugasan yang perlu diambil tindakan, tarikh akhir dan status SLA.',
            'Klik tugasan untuk membuka papan tugasan Workflow dan membuat keputusan; tindakan kelulusan memerlukan MFA.',
            'Tab "Draf" menyenaraikan draf transaksi yang anda simpan. Buka semula untuk menyambung, atau buang draf yang tidak diperlukan.',
        ]],
        ['kod' => 'carian', 'tajuk' => 'Carian dokumen dan rujukan', 'laluan' => ['/admin/dsh/carian'], 'kebenaran' => null, 'langkah' => [
            'Buka "Carian" dan taip kata kunci, atau gunakan penapis No. lot, Mukim, Daerah, Pemilik dan Kategori.',
            'Keputusan dikumpulkan mengikut kategori rekod dan hanya memaparkan rekod yang anda dibenarkan buka.',
        ]],
        ['kod' => 'lot', 'tajuk' => 'Profil Hartanah 360°', 'laluan' => ['/admin/pro'], 'kebenaran' => 'pro.lot.lihat', 'langkah' => [
            'Buka "Lot Tanah", cari lot melalui nombor lot, mukim atau daerah.',
            'Klik lot untuk Profil Hartanah 360° — tab Hak Milik, Pajakan, Cukai, Aduan, Dokumen dan Peta.',
        ]],
        ['kod' => 'peta', 'tajuk' => 'Peta GIS', 'laluan' => ['/admin/gis'], 'kebenaran' => 'gis.peta.lihat', 'langkah' => [
            'Buka "Peta GIS" dan hidupkan lapisan yang diperlukan pada panel lapisan.',
            'Klik lot pada peta untuk melihat atribut dan pautan ke Profil Hartanah 360°.',
        ]],
    ];

    /** @var list<array{soalan: string, jawapan: string}> */
    private const FAQ = [
        ['soalan' => 'Mengapa saya tidak nampak data daerah lain?', 'jawapan' => 'Data dihadkan mengikut skop capaian (daerah/bahagian) peranan anda. Hubungi Pentadbir Aplikasi jika skop anda perlu dikemas kini.'],
        ['soalan' => 'Berapa kerap data dashboard dikemas kini?', 'jawapan' => 'Indikator dikira semula sekurang-kurangnya setiap 15 minit. Cap masa pengiraan dipaparkan pada setiap widget dan setiap laporan eksport.'],
        ['soalan' => 'Mengapa sesetengah indikator tidak dipaparkan?', 'jawapan' => 'Indikator berklasifikasi Sulit/Rahsia (cth. nilai portfolio, kes mahkamah) hanya dipaparkan kepada pengguna yang diberi kebenaran khusus.'],
        ['soalan' => 'Adakah soalan saya kepada medan AI dihantar ke perkhidmatan luar?', 'jawapan' => 'Tidak. Pemprosesan prompt berlaku dalam persekitaran persendirian MAIS sahaja dan tiada data dihantar kepada perkhidmatan AI awam.'],
        ['soalan' => 'Bagaimana menerima ringkasan dashboard melalui e-mel?', 'jawapan' => 'Klik "Langgan ringkasan" pada dashboard dan pilih kekerapan harian, mingguan atau bulanan.'],
        ['soalan' => 'Draf saya hilang selepas sesi tamat?', 'jawapan' => 'Draf yang disimpan kekal dalam tab Draf di Inbox Tugasan sehingga anda membuangnya atau menghantarnya.'],
    ];

    public function __construct(private AccessControl $access, private Parameters $parameters) {}

    /** @return array{panduan: list<array<string, mixed>>, faq: list<array{soalan: string, jawapan: string}>, manual: string|null} */
    public function for(User $user, ?string $laluan = null): array
    {
        $guides = array_values(array_filter(self::PANDUAN, fn ($g) => $g['kebenaran'] === null || $this->access->allows($user, $g['kebenaran'])));
        $guides = array_map(fn ($g) => $g + ['berkaitan' => $laluan !== null && $this->matches($laluan, $g['laluan'])], $guides);
        usort($guides, fn ($a, $b) => (int) $b['berkaitan'] <=> (int) $a['berkaitan']);
        $manual = (string) $this->parameters->get('dsh.url_manual', '');

        return ['panduan' => $guides, 'faq' => self::FAQ, 'manual' => $manual !== '' ? $manual : null];
    }

    /** @param list<string> $prefixes */
    private function matches(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $p) {
            if ($path === $p || ($p !== '/admin' && str_starts_with($path, $p.'/')) || ($p !== '/admin' && $path === $p)) {
                return true;
            }
        }

        return false;
    }
}
