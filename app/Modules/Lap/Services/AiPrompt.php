<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Lap\Support\SetData;
use Illuminate\Support\Facades\DB;

/**
 * "Ask for a report in plain language" (SRS-LAP-13) and smart search parsing
 * (SRS-LAP-14). Rule-based, inside SISDATA — no text or data is sent to a
 * public AI service (SRS-LAP-21). The output is an ordinary report
 * definition: it is executed by the same engine under the user's own scope
 * (a prompt can never widen it), and can be saved, shared or edited further
 * in the builder.
 */
class AiPrompt
{
    /** Ordered: first match wins. */
    private const SET_KATA = [
        'caj_sewaan' => ['tunggakan sewa', 'sewa tertunggak', 'sewaan tertunggak', 'hasil sewa', 'caj sewa', 'kutipan sewa', 'hasil sewaan'],
        'cukai' => ['cukai'],
        'aduan' => ['aduan'],
        'pajakan' => ['pajakan', 'kontrak', 'sewaan', 'penyewa'],
        'hak_milik' => ['hak milik', 'geran'],
        'pemantauan' => ['isu pemantauan', 'pemantauan', 'pencerobohan'],
        'tugasan' => ['tugasan', 'sla', 'aliran kerja'],
        'kes' => ['kes mahkamah', 'mahkamah', 'guaman', 'perundangan'],
        'penyelenggaraan' => ['penyelenggaraan'],
        'aset' => ['aset', 'bangunan'],
        'pemerolehan' => ['pemerolehan'],
        'risiko' => ['risk rating', 'risiko'],
        'aktiviti_audit' => ['audit', 'capaian data'],
        'lot' => ['lot', 'tanah', 'portfolio', 'hartanah'],
    ];

    private const KUMPUL = [
        'daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'saluran' => 'saluran_penerimaan', 'keterukan' => 'tahap_keterukan', 'modul' => 'modul',
        'kondisi' => 'status_kondisi', 'umur' => 'kumpulan_umur', 'bahagian' => 'bahagian', 'zoning' => 'zoning', 'keutamaan' => 'tahap_keutamaan',
        'peringkat' => 'peringkat_mahkamah', 'warna' => 'kod_warna', 'aras' => 'aras_risiko',
    ];

    private const WANG_KATA = ['baki', 'kos', 'nilai', 'amaun', 'denda', 'dibayar', 'jumlah', 'hasil', 'tuntutan'];

    public function __construct(private Katalog $katalog) {}

    /**
     * @return array{difahami: bool, definisi: array<string, mixed>|null, penjelasan: list<string>}
     */
    public function interpret(User $user, string $prompt): array
    {
        $p = ' '.mb_strtolower(trim(preg_replace('/\s+/', ' ', $prompt) ?? '')).' ';
        $why = [];
        $setKey = null;
        foreach (self::SET_KATA as $key => $words) {
            foreach ($words as $w) {
                if (str_contains($p, ' '.$w) || str_contains($p, $w.' ')) {
                    $setKey = $key;
                    break 2;
                }
            }
        }
        if ($setKey === null) {
            return ['difahami' => false, 'definisi' => null, 'penjelasan' => [__('lap::lap.ai.tidak_difahami')]];
        }
        if (! $this->katalog->canUseSet($user, $setKey)) {
            return ['difahami' => false, 'definisi' => null, 'penjelasan' => [__('lap::lap.ai.tiada_kebenaran', ['set' => SetData::get($setKey)['label']])]];
        }
        $set = SetData::get($setKey);
        $fields = $set['medan'];
        $why[] = __('lap::lap.ai.set', ['set' => $set['label']]);
        $def = ['set' => $setKey, 'kumpulan' => [], 'agregat' => [['fungsi' => 'bilangan']], 'penapis' => [], 'carta' => 'bar'];

        // Grouping: "mengikut daerah dan status", "ikut bulan".
        if (preg_match_all('/(?:mengikut|ikut|by|setiap|per)\s+([a-z ]+?)(?=\s+(?:bagi|untuk|pada|dalam|tahun|bulan ini|di|yang|berbanding)\s|$|,|\.)/u', $p, $m)) {
            foreach ($m[1] as $chunk) {
                foreach (preg_split('/\s+(?:dan|&|,)\s+|\s+/u', trim($chunk)) ?: [] as $word) {
                    $g = $this->groupFor($word, $set);
                    if ($g !== null && ! in_array($g, $def['kumpulan'], true) && count($def['kumpulan']) < 2) {
                        $def['kumpulan'][] = $g;
                    }
                }
            }
        }
        if (str_contains($p, 'trend') && $def['kumpulan'] === [] && $set['tarikh']) {
            $def['kumpulan'][] = $set['tarikh'].':bulan';
        }
        if ($def['kumpulan'] !== []) {
            $why[] = __('lap::lap.ai.kumpulan', ['kumpulan' => implode(', ', array_map(fn ($g) => $fields[explode(':', $g)[0]]['label'].(str_contains($g, ':') ? ' ('.__('lap::lap.butiran.'.explode(':', $g)[1]).')' : ''), $def['kumpulan']))]);
        }

        // Money aggregate.
        $fn = str_contains($p, 'purata') ? 'purata' : 'jumlah';
        foreach (self::WANG_KATA as $w) {
            if (! str_contains($p, $w)) {
                continue;
            }
            foreach ($fields as $k => $f) {
                if ($f['jenis'] === 'wang' && empty($f['kebenaran']) && (str_contains($k, $w) || str_contains(mb_strtolower($f['label']), $w))) {
                    $def['agregat'][] = ['fungsi' => $fn, 'medan' => $k];
                    $why[] = __('lap::lap.ai.agregat', ['fungsi' => __('lap::lap.fungsi.'.$fn), 'medan' => mb_strtolower($f['label'])]);
                    break 2;
                }
            }
        }

        // Boolean filters by keyword.
        foreach (['tertunggak' => 'tertunggak', 'terbuka' => 'terbuka', 'belum selesai' => 'terbuka', 'lewat' => 'lewat_terbuka', 'aktif' => 'aktif', 'melebihi' => 'melebihi_sasaran'] as $w => $k) {
            if (str_contains($p, $w) && isset($fields[$k]) && $fields[$k]['jenis'] === 'bool') {
                $def['penapis'][] = ['medan' => $k, 'op' => 'benar'];
                $why[] = __('lap::lap.ai.penapis', ['penapis' => $fields[$k]['label']]);
            }
        }
        if (str_contains($p, 'merah') && isset($fields['kod_warna'])) {
            $def['penapis'][] = ['medan' => 'kod_warna', 'op' => 'sama', 'nilai' => 'merah'];
            $why[] = __('lap::lap.ai.penapis', ['penapis' => 'Kod warna merah']);
        }

        // District names.
        if (isset($fields['kod_daerah'])) {
            foreach ($this->districts() as $kod => $nama) {
                if (str_contains($p, ' '.mb_strtolower($nama).' ') || str_contains($p, ' '.mb_strtolower($nama).',')) {
                    $def['penapis'][] = ['medan' => 'kod_daerah', 'op' => 'sama', 'nilai' => (string) $kod];
                    $why[] = __('lap::lap.ai.daerah', ['daerah' => $nama]);
                }
            }
        }

        // Period.
        $tarikh = $set['tarikh'];
        if ($tarikh) {
            $preset = null;
            foreach (['tahun lepas' => 'tahun_lepas', 'tahun ini' => 'tahun_ini', 'bulan ini' => 'bulan_ini', 'suku ini' => 'suku_ini', '12 bulan' => '12_bulan', '30 hari' => '30_hari', 'hari ini' => 'hari_ini'] as $w => $v) {
                if (str_contains($p, $w)) {
                    $preset = $v;
                    break;
                }
            }
            if ($preset) {
                $def['tempoh'] = ['medan' => $tarikh, 'pratetap' => $preset];
                $why[] = __('lap::lap.ai.tempoh', ['tempoh' => __('lap::lap.pratetap.'.$preset)]);
            } elseif (preg_match('/tahun (20\d{2})/', $p, $y)) {
                $def['tempoh'] = ['medan' => $tarikh, 'dari' => "{$y[1]}-01-01", 'hingga' => "{$y[1]}-12-31"];
                $why[] = __('lap::lap.ai.tempoh', ['tempoh' => $y[1]]);
            }
            if (preg_match('/berbanding|bandingkan|tahun ke tahun|yoy|tahun sebelum/', $p) && $def['kumpulan'] !== []) {
                $def['tempoh'] ??= ['medan' => $tarikh, 'pratetap' => 'tahun_ini'];
                $def['bandingan'] = 'tahun_lepas';
                $why[] = __('lap::lap.ai.bandingan');
            }
        }
        if (str_contains($p, 'trend') || (isset($def['kumpulan'][0]) && str_contains($def['kumpulan'][0], ':'))) {
            $def['carta'] = 'garis';
        } elseif (preg_match('/ pai |peratusan|komposisi/', $p)) {
            $def['carta'] = 'pai';
        }
        if ($def['kumpulan'] === []) {
            // No grouping asked: list the records.
            $def['agregat'] = [];
            $why[] = __('lap::lap.ai.senarai');
        }

        return ['difahami' => true, 'definisi' => $def, 'penjelasan' => $why];
    }

    private function groupFor(string $word, array $set): ?string
    {
        $word = trim($word);
        if (in_array($word, ['bulan', 'tahun', 'suku'], true)) {
            return $set['tarikh'] ? $set['tarikh'].':'.$word : null;
        }
        if (isset(self::KUMPUL[$word]) && isset($set['medan'][self::KUMPUL[$word]])) {
            return self::KUMPUL[$word];
        }
        foreach ($set['medan'] as $k => $f) {
            if (in_array($f['jenis'], ['kod', 'bool'], true) && empty($f['kebenaran']) && $word !== '' && mb_strlen($word) >= 4
                && (str_starts_with($k, $word) || str_contains($k, '_'.$word) || str_contains(mb_strtolower($f['label']), $word))) {
                return $k;
            }
        }

        return null;
    }

    /** @return array<string, string> kod => nama (current district reference data) */
    public function districts(): array
    {
        return DB::table('ref_daerah')->whereNull('sah_hingga')->orderBy('susunan')->pluck('nama', 'kod')->map(fn ($n) => (string) $n)->all();
    }

    /** @return array<string, string> */
    public function mukims(): array
    {
        return DB::table('ref_mukim')->whereNull('sah_hingga')->pluck('nama', 'kod')->map(fn ($n) => (string) $n)->all();
    }

    /** @return array<string, string> */
    public function landUses(): array
    {
        return DB::table('ref_kategori_guna_tanah')->whereNull('sah_hingga')->pluck('nama', 'kod')->map(fn ($n) => (string) $n)->all();
    }

    /**
     * SRS-LAP-14: natural keywords → lot filters ("lot terbiar di Sepang
     * kategori pertanian", "tanah wakaf tiada pajakan Petaling", "PT 1234").
     *
     * @return array{penapis: list<array<string, mixed>>, tafsiran: list<string>}
     */
    public function searchFilters(string $q): array
    {
        $p = ' '.mb_strtolower(trim(preg_replace('/\s+/', ' ', $q) ?? '')).' ';
        $f = [];
        $why = [];
        $used = [];
        foreach ($this->districts() as $kod => $nama) {
            $n = mb_strtolower($nama);
            if (str_contains($p, ' '.$n.' ')) {
                $f[] = ['medan' => 'kod_daerah', 'op' => 'sama', 'nilai' => (string) $kod];
                $why[] = __('lap::lap.carian.daerah', ['nama' => $nama]);
                $used[] = $n;
            }
        }
        foreach ($this->mukims() as $kod => $nama) {
            $n = mb_strtolower($nama);
            if (! in_array($n, $used, true) && str_contains($p, ' '.$n.' ')) {
                $f[] = ['medan' => 'kod_mukim', 'op' => 'sama', 'nilai' => (string) $kod];
                $why[] = __('lap::lap.carian.mukim', ['nama' => $nama]);
                $used[] = $n;
            }
        }
        foreach ($this->landUses() as $kod => $nama) {
            $n = mb_strtolower($nama);
            if (str_contains($p, ' '.$n.' ') || str_contains($p, ' '.mb_strtolower($kod).' ')) {
                $f[] = ['medan' => 'kategori_guna_tanah', 'op' => 'sama', 'nilai' => (string) $kod];
                $why[] = __('lap::lap.carian.kategori', ['nama' => $nama]);
                $used[] = $n;
            }
        }
        $flags = [
            'terbiar' => [['medan' => 'ada_pajakan_aktif', 'op' => 'palsu'], 'Tidak berpajakan (berpotensi terbiar)'],
            'tiada pajakan' => [['medan' => 'ada_pajakan_aktif', 'op' => 'palsu'], 'Tiada pajakan aktif'],
            'kosong' => [['medan' => 'ada_pajakan_aktif', 'op' => 'palsu'], 'Tiada pajakan aktif'],
            'disewa' => [['medan' => 'ada_pajakan_aktif', 'op' => 'benar'], 'Ada pajakan aktif'],
            'berpajakan' => [['medan' => 'ada_pajakan_aktif', 'op' => 'benar'], 'Ada pajakan aktif'],
            'ceroboh' => [['medan' => 'ada_isu_pencerobohan', 'op' => 'benar'], 'Ada isu pencerobohan'],
            'mahkamah' => [['medan' => 'ada_kes_aktif', 'op' => 'benar'], 'Ada kes mahkamah aktif'],
            'wakaf' => [['medan' => 'bahagian', 'op' => 'sama', 'nilai' => 'wakaf'], 'Bahagian wakaf'],
            'baitulmal' => [['medan' => 'bahagian', 'op' => 'sama', 'nilai' => 'baitulmal'], 'Bahagian Baitulmal'],
        ];
        $seen = [];
        foreach ($flags as $w => [$filter, $label]) {
            $k = $filter['medan'].$filter['op'];
            if (str_contains($p, $w) && ! isset($seen[$k])) {
                $seen[$k] = true;
                $f[] = $filter;
                $why[] = $label;
                $used[] = $w;
            }
        }
        if (str_contains($p, 'terbiar')) {
            $f[] = ['medan' => 'ada_isu_terbiar', 'op' => 'benar'];
            $why[] = 'Isu tanah terbiar terbuka';
        }
        // Lot numbers: any token with a digit.
        if (preg_match_all('/(?:lot|pt|hs\(d\)|hsd)?\s*([a-z]*\d[\w\/-]*)/u', $p, $m)) {
            foreach (array_unique($m[1]) as $tok) {
                if (! preg_match('/^(19|20)\d{2}$/', $tok)) {
                    $f[] = ['medan' => 'no_lot', 'op' => 'mengandungi', 'nilai' => $tok];
                    $why[] = __('lap::lap.carian.no_lot', ['no' => mb_strtoupper($tok)]);
                }
            }
        }

        return ['penapis' => $f, 'tafsiran' => $why];
    }
}
