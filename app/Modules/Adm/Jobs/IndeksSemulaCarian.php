<?php

namespace App\Modules\Adm\Jobs;

use App\Modules\Adm\Contracts\Reindexable;
use App\Modules\Adm\Models\IndeksCarian;
use App\Modules\Adm\Services\Carian\PendaftaranCarian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** SRS-ADM-20: admin-triggered reindex of one search entity. */
class IndeksSemulaCarian implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public string $entity) {}

    public function handle(PendaftaranCarian $registry): void
    {
        $row = IndeksCarian::query()->firstOrCreate(['entiti' => $this->entity], ['label' => $this->entity]);
        $provider = $registry->providers()[$this->entity] ?? null;

        if (! $provider instanceof Reindexable) {
            $row->forceFill(['status_indeks' => 'tidak_disokong'])->saveQuietly();

            return;
        }

        $row->forceFill(['status_indeks' => 'berjalan'])->saveQuietly();

        try {
            $count = $provider->reindex();
            $row->forceFill(['status_indeks' => 'siap', 'diindeks_semula_pada' => now()])->saveQuietly();
            Log::info('Search reindex done', ['modul' => 'adm', 'entiti' => $this->entity, 'bilangan' => $count]);
        } catch (Throwable $e) {
            $row->forceFill(['status_indeks' => 'gagal'])->saveQuietly();
            report($e);
        }
    }
}
