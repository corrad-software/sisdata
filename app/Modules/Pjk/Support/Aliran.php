<?php

namespace App\Modules\Pjk\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * MOD-PJK approval flows (SRS-PJK-05, SRS-GEN-03). PJK only starts them and
 * applies the outcome on WorkflowCompleted; stages, approvers and value /
 * category layers are configured in MOD-WFE (defaults in Database/wfe/*.json,
 * seeded by database/seeders/PjkAliranSeeder).
 */
final class Aliran
{
    public const KONTRAK = 'pjk.kelulusan_kontrak';

    public const PINDAAN = 'pjk.pindaan_kontrak';

    public const PELARASAN = 'pjk.pelarasan_lejar';

    public const DEPOSIT = 'pjk.penyelarasan_deposit';

    public const DASAR = 'pjk.dasar_agihan';

    /** flow => [name, subject type, context fields for branch conditions] */
    public const FLOWS = [
        self::KONTRAK => ['Kelulusan perjanjian sewaan / pajakan baharu', 'kontrak_pajakan', ['nilai_kontrak', 'kadar', 'kategori_hartanah', 'jenis_perjanjian', 'bahagian', 'kod_daerah', 'sumber']],
        self::PINDAAN => ['Kelulusan pembaharuan, semakan kadar, pindaan terma dan penamatan', 'pindaan_kontrak', ['jenis_pindaan', 'nilai_kontrak', 'kadar_baharu', 'peratus_perubahan_kadar', 'kategori_hartanah', 'bahagian', 'kod_daerah']],
        self::PELARASAN => ['Kelulusan pelarasan / pembatalan / pengecualian lejar (pegawai kedua)', 'pelarasan_lejar', ['jenis', 'amaun', 'kod_daerah']],
        self::DEPOSIT => ['Kelulusan penyelarasan deposit (Bahagian Kewangan)', 'deposit_cagaran', ['amaun', 'amaun_tolakan', 'kod_daerah']],
        self::DASAR => ['Kelulusan kadar Dasar Agihan Manfaat', 'dasar_agihan_manfaat', ['kategori_hartanah', 'status_perolehan', 'kadar_agihan']],
    ];

    /**
     * @param  array<string, mixed>  $context
     *
     * @throws ValidationException when the flow is not configured in WFE
     */
    public static function start(WorkflowEngine $engine, string $flow, string|int $subjectId, User $user, array $context, string $title): string
    {
        try {
            return $engine->start($flow, self::FLOWS[$flow][1], $subjectId, $user, $context, $title);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['aliran' => __('pjk::pjk.ralat.aliran_tiada', ['aliran' => $flow])]);
        }
    }
}
