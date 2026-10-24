<?php

namespace App\Modules\Pjk\Services;

use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SRS-PJK-11: the contract ledger. Every row carries the running balance
 * (positive = owed by the tenant). Rows are only ever appended — the table
 * rejects UPDATE/DELETE — and a contract row lock serialises writers so the
 * running balance can never fork.
 */
class LejarService
{
    /**
     * @param  array<string, mixed>  $attrs  kod_caj, caj_id, kaedah_bayaran, no_resit, bayaran_id, transaksi_gateway_id, id_transaksi_asal, pelarasan_id, status_pelarasan, keterangan, id_pegawai
     */
    public function post(Kontrak $kontrak, string $jenis, int $debitSen, int $kreditSen, array $attrs = [], ?Carbon $tarikh = null): LejarTransaksi
    {
        if ($debitSen < 0 || $kreditSen < 0 || ($debitSen === 0 && $kreditSen === 0)) {
            throw new InvalidArgumentException('Amaun lejar tidak sah.');
        }

        return DB::transaction(function () use ($kontrak, $jenis, $debitSen, $kreditSen, $attrs, $tarikh) {
            DB::table('kontrak_pajakan')->where('id', $kontrak->id)->lockForUpdate()->first();
            $baki = $this->bakiSen($kontrak->id) + $debitSen - $kreditSen;

            return LejarTransaksi::query()->create($attrs + [
                'kontrak_id' => $kontrak->id,
                'no_transaksi' => Penomboran::next('lejar'),
                'tarikh_transaksi' => ($tarikh ?? Carbon::today())->format('Y-m-d'),
                'jenis' => $jenis,
                'debit' => Wang::dec($debitSen),
                'kredit' => Wang::dec($kreditSen),
                'baki_berjalan' => Wang::dec($baki),
            ]);
        });
    }

    public function bakiSen(string $kontrakId): int
    {
        $last = DB::table('lejar_transaksi')->where('kontrak_id', $kontrakId)->orderByDesc('id')->value('baki_berjalan');

        return $last === null ? 0 : Wang::sen((string) $last);
    }

    /**
     * Ledger rows in a period with the opening balance (statement, SRS-PJK-11).
     *
     * @return array{baki_awal: string, baris: list<LejarTransaksi>, jumlah_debit: string, jumlah_kredit: string, baki_akhir: string}
     */
    public function statement(Kontrak $kontrak, ?string $from = null, ?string $to = null): array
    {
        $opening = 0;
        if ($from !== null) {
            $prev = LejarTransaksi::query()->where('kontrak_id', $kontrak->id)->where('tarikh_transaksi', '<', $from)->orderByDesc('id')->value('baki_berjalan');
            $opening = $prev === null ? 0 : Wang::sen((string) $prev);
        }
        $rows = LejarTransaksi::query()->where('kontrak_id', $kontrak->id)
            ->when($from, fn ($q) => $q->where('tarikh_transaksi', '>=', $from))
            ->when($to, fn ($q) => $q->where('tarikh_transaksi', '<=', $to))
            ->orderBy('id')->get();

        $debit = 0;
        $kredit = 0;
        foreach ($rows as $r) {
            $debit += Wang::sen($r->debit);
            $kredit += Wang::sen($r->kredit);
        }

        return [
            'baki_awal' => Wang::dec($opening),
            'baris' => $rows->all(),
            'jumlah_debit' => Wang::dec($debit),
            'jumlah_kredit' => Wang::dec($kredit),
            'baki_akhir' => Wang::dec($opening + $debit - $kredit),
        ];
    }
}
