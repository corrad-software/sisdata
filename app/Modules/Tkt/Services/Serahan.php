<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Tkt\Models\AhliPasukan;
use App\Modules\Tkt\Models\Kronologi;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Models\PemetaanAduan;
use App\Modules\Tkt\Models\PeraturanPenugasan;
use App\Modules\Tkt\Models\SlaKonfigurasi;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS-TKT-21 / §18.7: hand-over of the support function from the vendor
 * to BTM MAIS after the warranty. Nothing is deleted: tickets, timelines,
 * SLA versions, escalation hierarchy and KB links stay; `pindah()` moves
 * open tickets and assignment rules from one team to another (recorded on
 * every ticket), and `pakej()` is the written hand-over package.
 */
class Serahan
{
    public function __construct(private Penugasan $penugasan, private Jejak $jejak) {}

    /** @return array{tiket: int, peraturan: int} */
    public function pindah(Pasukan $dari, Pasukan $ke, User $oleh, string $catatan, bool $nyahaktifSumber = false): array
    {
        if ($dari->id === $ke->id || ! $ke->aktif) {
            throw ValidationException::withMessages(['ke_pasukan' => __('tkt::tkt.ralat.pasukan_tidak_sah')]);
        }

        return DB::transaction(function () use ($dari, $ke, $oleh, $catatan, $nyahaktifSumber) {
            $moved = 0;
            Tiket::query()->where('id_pasukan', $dari->id)->whereNotIn('status', Lalai::AKHIR)->orderBy('tarikh_buka')
                ->each(function (Tiket $t) use ($dari, $ke, $oleh, $catatan, &$moved) {
                    $t->forceFill([
                        'id_pasukan' => $ke->id,
                        'aras_sokongan' => $ke->aras,
                        'id_juruteknik' => $this->penugasan->palingRingan($ke->id),
                        'nama_vendor' => $ke->jenis === 'vendor' ? $ke->nama_vendor : null,
                    ])->save();
                    $this->jejak->catat($t, 'tugasan', $oleh, $catatan, null, null, ['serahan' => true, 'dari' => $dari->nama, 'ke' => $ke->nama]);
                    $moved++;
                });
            $rules = PeraturanPenugasan::query()->where('id_pasukan', $dari->id)->update(['id_pasukan' => $ke->id, 'id_juruteknik' => null]);
            if ($nyahaktifSumber) {
                $dari->forceFill(['aktif' => false])->save();
            }

            return ['tiket' => $moved, 'peraturan' => $rules];
        });
    }

    /**
     * Hand-over package sections (all history, SLA configuration versions,
     * escalation hierarchy, teams, assignment rules, KB article links,
     * known open issues, central-system mapping).
     *
     * @return list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>
     */
    public function pakej(): array
    {
        $tz = KalendarSokongan::TZ;
        $fmt = fn ($d) => $d?->setTimezone($tz)->format('Y-m-d H:i');
        $tickets = [];
        $open = [];
        $kb = [];
        Tiket::query()->with(['pasukan', 'juruteknik', 'pelapor'])->orderBy('tarikh_buka')->each(function (Tiket $t) use (&$tickets, &$open, &$kb, $fmt) {
            $row = [$t->nombor_rujukan, $t->tajuk, Lalai::KATEGORI[$t->kategori] ?? $t->kategori, Lalai::KETERUKAN_RINGKAS[$t->keterukan] ?? $t->keterukan, $t->modul, Lalai::STATUS[$t->status] ?? $t->status, $t->saluran, $t->pelapor?->name, $t->pasukan?->nama, $t->juruteknik?->name, $fmt($t->tarikh_buka), $fmt($t->tarikh_selesai), $fmt($t->tarikh_tutup), $t->punca_akar, $t->penyelesaian, $t->sintetik ? 'Ya' : 'Tidak'];
            $tickets[] = $row;
            if ($t->terbuka()) {
                $open[] = [$t->nombor_rujukan, $t->tajuk, Lalai::KETERUKAN_RINGKAS[$t->keterukan] ?? $t->keterukan, $t->modul, Lalai::STATUS[$t->status] ?? $t->status, $t->pasukan?->nama, $fmt($t->tarikh_sasaran_selesai)];
            }
            if ($t->id_artikel_kb !== null) {
                $kb[] = [$t->nombor_rujukan, $t->id_artikel_kb, '/admin/kba/artikel/'.$t->id_artikel_kb];
            }
        });
        $timeline = [];
        Kronologi::query()->join('tkt_tiket', 'tkt_tiket.id', '=', 'tkt_kronologi.id_tiket')->leftJoin('users', 'users.id', '=', 'tkt_kronologi.id_pengguna')
            ->orderBy('tkt_kronologi.id')->select(['tkt_kronologi.*', 'tkt_tiket.nombor_rujukan', 'users.name as nama_pengguna'])
            ->chunk(1000, function ($rows) use (&$timeline, $fmt) {
                foreach ($rows as $k) {
                    /** @var Kronologi $k */
                    $timeline[] = [$k->getAttribute('nombor_rujukan'), $fmt($k->cap_masa), Lalai::JENIS_CATATAN[$k->jenis_catatan] ?? $k->jenis_catatan, $k->getAttribute('nama_pengguna'), $k->status_dari, $k->status_ke, $k->kandungan];
                }
            });
        $sla = SlaKonfigurasi::query()->orderBy('keterukan')->orderBy('tarikh_berkuat_kuasa')->get();

        return [
            ['tajuk' => 'Tiket', 'lajur' => ['No.', 'Tajuk', 'Kategori', 'Severity', 'Modul', 'Status', 'Saluran', 'Pelapor', 'Pasukan', 'Juruteknik', 'Dibuka', 'Selesai', 'Ditutup', 'Punca akar', 'Penyelesaian', 'Sintetik'], 'baris' => $tickets],
            ['tajuk' => 'Kronologi', 'lajur' => ['No. tiket', 'Masa', 'Jenis', 'Pengguna', 'Dari', 'Ke', 'Kandungan'], 'baris' => $timeline],
            ['tajuk' => 'Isu terbuka', 'lajur' => ['No.', 'Tajuk', 'Severity', 'Modul', 'Status', 'Pasukan', 'Sasaran selesai'], 'baris' => $open],
            ['tajuk' => 'Konfigurasi SLA', 'lajur' => ['Severity', 'Respons (minit)', 'Penyelesaian (minit)', 'Waktu perkhidmatan', 'Amaran (%)', 'Berkuat kuasa', 'Aktif', 'Catatan'], 'baris' => $sla->map(fn (SlaKonfigurasi $c) => [Lalai::KETERUKAN_RINGKAS[$c->keterukan] ?? $c->keterukan, $c->ambang_respons, $c->ambang_penyelesaian, $c->waktu_perkhidmatan, $c->ambang_amaran_peratus, $c->tarikh_berkuat_kuasa->toDateString(), $c->aktif ? 'Ya' : 'Tidak', $c->catatan])->values()->all()],
            ['tajuk' => 'Hierarki eskalasi', 'lajur' => ['Severity', 'Peringkat', 'Penerima', 'Selepas (minit)'], 'baris' => $sla->where('aktif', true)->flatMap(fn (SlaKonfigurasi $c) => array_map(fn ($l) => [Lalai::KETERUKAN_RINGKAS[$c->keterukan] ?? $c->keterukan, $l['peringkat'], $l['penerima'], $l['selepas_minit']], $c->hierarki_eskalasi))->values()->all()],
            ['tajuk' => 'Pasukan', 'lajur' => ['Kod', 'Nama', 'Aras', 'Jenis', 'Vendor', 'Modul', 'Aktif', 'Ahli'], 'baris' => Pasukan::query()->with('ahli.pengguna')->orderBy('id')->get()->map(fn (Pasukan $p) => [$p->kod, $p->nama, $p->aras, $p->jenis, $p->nama_vendor, $p->modul === null ? 'Semua' : implode(', ', $p->modul), $p->aktif ? 'Ya' : 'Tidak', $p->ahli->map(fn (AhliPasukan $a) => $a->pengguna?->name.' ('.$a->peranan.')')->implode('; ')])->values()->all()],
            ['tajuk' => 'Peraturan penugasan', 'lajur' => ['Modul', 'Kategori', 'Severity', 'Pasukan', 'Keutamaan', 'Aktif'], 'baris' => PeraturanPenugasan::query()->with('pasukan')->orderBy('id')->get()->map(fn (PeraturanPenugasan $r) => [$r->modul ?? 'Semua', $r->kategori ?? 'Semua', $r->keterukan ?? 'Semua', $r->pasukan?->nama, $r->keutamaan, $r->aktif ? 'Ya' : 'Tidak'])->values()->all()],
            ['tajuk' => 'Pautan artikel KB', 'lajur' => ['No. tiket', 'ID artikel', 'Pautan'], 'baris' => $kb],
            ['tajuk' => 'Pemetaan Sistem Aduan', 'lajur' => ['Jenis', 'Tempatan', 'Berpusat', 'Arah', 'Aktif'], 'baris' => PemetaanAduan::query()->orderBy('jenis')->orderBy('id')->get()->map(fn (PemetaanAduan $m) => [$m->jenis, $m->nilai_tempatan, $m->nilai_berpusat, $m->arah, $m->aktif ? 'Ya' : 'Tidak'])->values()->all()],
        ];
    }
}
