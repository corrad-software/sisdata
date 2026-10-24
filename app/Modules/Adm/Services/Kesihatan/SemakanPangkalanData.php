<?php

namespace App\Modules\Adm\Services\Kesihatan;

use Illuminate\Support\Facades\DB;
use Throwable;

/** PostgreSQL reachability + database size (SRS-ADM-16 "penggunaan pangkalan data"). */
class SemakanPangkalanData implements Semakan
{
    public function kod(): string
    {
        return 'pangkalan_data';
    }

    public function label(): string
    {
        return 'PostgreSQL';
    }

    public function jalankan(): array
    {
        try {
            DB::select('select 1');
            $details = ['pemacu' => DB::connection()->getDriverName()];

            if (DB::connection()->getDriverName() === 'pgsql') {
                $row = DB::selectOne('select pg_database_size(current_database()) as saiz, (select count(*) from pg_stat_activity where datname = current_database()) as sambungan, current_setting(\'max_connections\')::int as maks');
                $details += [
                    'saiz_bait' => (int) $row->saiz,
                    'sambungan' => (int) $row->sambungan,
                    'sambungan_maks' => (int) $row->maks,
                ];
            }

            return ['status' => self::OK, 'mesej' => null, 'butiran' => $details];
        } catch (Throwable $e) {
            return ['status' => self::GAGAL, 'mesej' => class_basename($e)];
        }
    }
}
