<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pjk\Contracts\ESewa;
use App\Modules\Pjk\Contracts\GatewayException;
use App\Modules\Pjk\Contracts\GrpMais;
use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Models\FailPenyelesaian;
use App\Modules\Pjk\Models\RekonsiliasiLarian;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Models\SisihanRekonsiliasi;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Support\Luaran;
use App\Modules\Pjk\Support\Wang;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-PJK-18 / SRS-PJK-21 / SRS-INT-08: daily reconciliation.
 *
 *  gateway: settlement statement ↔ gateway transactions credited in the
 *           ledger. Discrepancies: tiada_lejar (settled, not in ledger),
 *           tiada_penyelesaian (in ledger, not settled), beza_amaun.
 *  grp / esewa: receipts issued by SISDATA ↔ records held by GRP MAIS /
 *           e-Sewa, same three kinds.
 * Each run lists its discrepancies for Bahagian Kewangan and notifies them.
 */
class RekonsiliasiService
{
    public const SUMBER = ['gateway', 'grp', 'esewa'];

    public function __construct(private PaymentGateway $gateway, private Notifier $notifier) {}

    public function run(string $source, CarbonImmutable $date, ?FailPenyelesaian $file = null, ?User $user = null): RekonsiliasiLarian
    {
        try {
            $external = $file !== null ? $file->rekod : $this->fetch($source, $date);
        } catch (GatewayException $e) {
            return RekonsiliasiLarian::query()->create(['sumber' => $source, 'tarikh' => $date->format('Y-m-d'), 'status' => 'gagal', 'mesej' => mb_substr($e->getMessage(), 0, 500), 'id_pegawai' => $user?->id]);
        }
        if ($file === null) {
            $file = FailPenyelesaian::query()->create([
                'sumber' => $source, 'tarikh_penyelesaian' => $date->format('Y-m-d'), 'nama_fail' => $source.'-'.$date->format('Ymd').'.json',
                'bil_rekod' => count($external), 'jumlah' => Wang::dec(array_sum(array_map(fn ($r) => Wang::sen((string) $r['amaun']), $external))), 'rekod' => $external,
            ]);
        }

        $run = DB::transaction(function () use ($source, $date, $file, $external, $user) {
            $run = RekonsiliasiLarian::query()->create(['sumber' => $source, 'tarikh' => $date->format('Y-m-d'), 'fail_penyelesaian_id' => $file->id, 'status' => 'selesai', 'id_pegawai' => $user?->id]);
            $system = $source === 'gateway' ? $this->systemGateway($date) : $this->systemReceipts($date);
            $matched = 0;
            $diffs = 0;
            $seen = [];

            foreach ($external as $row) {
                $ref = (string) $row['rujukan'];
                $seen[$ref] = true;
                $mine = $system[$ref] ?? null;
                if ($mine === null) {
                    $this->diff($run, 'tiada_lejar', $ref, $source === 'gateway' ? TransaksiGateway::query()->where('no_rujukan', $ref)->value('kontrak_id') : null, null, (string) $row['amaun']);
                    $diffs++;
                } elseif (Wang::sen((string) $row['amaun']) !== Wang::sen($mine['amaun'])) {
                    $this->diff($run, 'beza_amaun', $ref, $mine['kontrak_id'], $mine['amaun'], (string) $row['amaun']);
                    $diffs++;
                } else {
                    $matched++;
                    if ($source === 'gateway') {
                        TransaksiGateway::query()->where('no_rujukan', $ref)->update(['id_fail_penyelesaian' => $file->id]);
                    }
                }
            }
            foreach ($system as $ref => $mine) {
                if (! isset($seen[$ref])) {
                    $this->diff($run, 'tiada_penyelesaian', $ref, $mine['kontrak_id'], $mine['amaun'], null);
                    $diffs++;
                }
            }
            $run->forceFill(['bil_padan' => $matched, 'bil_sisihan' => $diffs])->save();

            return $run;
        });

        if ($run->bil_sisihan > 0) {
            try {
                $this->notifier->send('pjk.sisihan_rekonsiliasi', [], [
                    'sumber' => strtoupper($source), 'tarikh' => $date->format('d/m/Y'), 'bil_sisihan' => $run->bil_sisihan,
                ], '/admin/pjk/rekonsiliasi?larian='.$run->id, 'pjk.rekon:'.$source.':'.$date->format('Y-m-d').':'.$run->id);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $run;
    }

    /** Uploaded test / manual statement (CSV: rujukan,amaun[,status][,tarikh]). */
    public function upload(string $source, CarbonImmutable $date, UploadedFile $file, User $user): FailPenyelesaian
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['fail' => __('pjk::pjk.ralat.fail_tidak_sah')]);
        }
        $line = 0;
        while (($cols = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if ($line === 1 && ! preg_match('/^\d/', (string) ($cols[1] ?? ''))) {
                continue; // header
            }
            if (count($cols) < 2 || trim((string) $cols[0]) === '') {
                continue;
            }
            try {
                $amount = Wang::norm(trim((string) $cols[1]));
            } catch (Throwable) {
                fclose($handle);
                throw ValidationException::withMessages(['fail' => __('pjk::pjk.ralat.fail_baris', ['baris' => $line])]);
            }
            $rows[] = ['rujukan' => trim((string) $cols[0]), 'amaun' => $amount, 'status' => trim((string) ($cols[2] ?? 'berjaya')) ?: 'berjaya', 'tarikh' => trim((string) ($cols[3] ?? $date->format('Y-m-d')))];
        }
        fclose($handle);

        return FailPenyelesaian::query()->create([
            'sumber' => $source, 'tarikh_penyelesaian' => $date->format('Y-m-d'), 'nama_fail' => mb_substr($file->getClientOriginalName(), 0, 200),
            'bil_rekod' => count($rows), 'jumlah' => Wang::dec(array_sum(array_map(fn ($r) => Wang::sen($r['amaun']), $rows))), 'rekod' => $rows, 'id_pemuat_naik' => $user->id,
        ]);
    }

    /** @return list<array{rujukan: string, amaun: string}> */
    private function fetch(string $source, CarbonImmutable $date): array
    {
        return match ($source) {
            'gateway' => Luaran::call('pjk_gateway', 'penyelesaian', fn () => $this->gateway->settlement($date)),
            'grp' => Luaran::call('pjk_grp', 'senarai_resit', fn () => app(GrpMais::class)->receipts($date)),
            default => Luaran::call('pjk_esewa', 'senarai_bayaran', fn () => app(ESewa::class)->payments($date)),
        };
    }

    /** @return array<string, array{amaun: string, kontrak_id: string}> credited gateway transactions of the day */
    private function systemGateway(CarbonImmutable $date): array
    {
        return DB::table('transaksi_gateway as t')
            ->join('lejar_transaksi as l', fn ($j) => $j->on('l.transaksi_gateway_id', '=', 't.id')->where('l.jenis', '=', 'bayaran'))
            ->where('t.status', 'berjaya')->whereDate('t.masa_mula', $date->format('Y-m-d'))
            ->get(['t.no_rujukan', 'l.kredit', 't.kontrak_id'])
            ->mapWithKeys(fn ($r) => [(string) $r->no_rujukan => ['amaun' => Wang::norm((string) $r->kredit), 'kontrak_id' => (string) $r->kontrak_id]])->all();
    }

    /** @return array<string, array{amaun: string, kontrak_id: string}> receipts issued on the day */
    private function systemReceipts(CarbonImmutable $date): array
    {
        return Resit::query()->whereDate('tarikh', $date->format('Y-m-d'))->get(['no_resit', 'amaun', 'kontrak_id'])
            ->mapWithKeys(fn (Resit $r) => [$r->no_resit => ['amaun' => $r->amaun, 'kontrak_id' => $r->kontrak_id]])->all();
    }

    private function diff(RekonsiliasiLarian $run, string $kind, string $ref, ?string $kontrakId, ?string $mine, ?string $theirs): void
    {
        SisihanRekonsiliasi::query()->create([
            'larian_id' => $run->id, 'jenis' => $kind, 'rujukan' => $ref, 'kontrak_id' => $kontrakId,
            'amaun_sistem' => $mine, 'amaun_luar' => $theirs, 'status_tindakan' => 'baharu',
        ]);
    }
}
