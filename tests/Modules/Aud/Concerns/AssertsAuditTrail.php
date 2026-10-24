<?php

namespace Tests\Modules\Aud\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * For any module's tests: assert that an action produced an audit record
 * (MOD-AUD, SRS-GEN-02). Works against the real audit store, so use it with
 * RefreshDatabase on Postgres.
 *
 *     use AssertsAuditTrail;
 *
 *     $this->putJson("/api/v1/pro/lot/{$lot->id}", ['keluasan' => 2.5]);
 *     $this->assertAudited('kemas_kini', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $lot->id])
 *          ->assertFieldChanged('keluasan', '2.0', '2.5');
 *
 *     $this->assertAudited('muat_turun', ['modul' => 'dms', 'id_pengguna' => $user->id]);
 *     $this->assertNotAudited('padam', ['nama_entiti' => 'lot_tanah']);
 *
 * $where keys are audit_rekod columns (modul, nama_entiti, id_rekod,
 * id_pengguna, status_transaksi, saluran_capaian, sebab_perubahan, …).
 */
trait AssertsAuditTrail
{
    /**
     * Latest audit_rekod row with this action matching $where, plus its field changes.
     *
     * @param  array<string, mixed>  $where
     */
    protected function assertAudited(string $action, array $where = []): AuditedRecord
    {
        $row = $this->auditQuery($action, $where)->orderByDesc('no_urutan')->first();

        $this->assertNotNull($row, sprintf(
            'Tiada rekod audit "%s" yang sepadan dengan %s. Rekod terakhir: %s',
            $action,
            json_encode($where),
            json_encode(DB::table('audit_rekod')->orderByDesc('no_urutan')->limit(5)->get(['jenis_tindakan', 'modul', 'nama_entiti', 'id_rekod', 'status_transaksi'])),
        ));

        $changes = DB::table('audit_perubahan_medan')->where('id_audit', $row->id)->orderBy('id')->get();

        return new AuditedRecord($row, $changes);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    protected function assertNotAudited(string $action, array $where = []): void
    {
        $this->assertSame(0, $this->auditQuery($action, $where)->count(), "Rekod audit \"{$action}\" tidak dijangka wujud.");
    }

    /**
     * @param  array<string, mixed>  $where
     */
    protected function assertAuditCount(int $expected, string $action, array $where = []): void
    {
        $this->assertSame($expected, $this->auditQuery($action, $where)->count());
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function auditQuery(string $action, array $where): Builder
    {
        $query = DB::table('audit_rekod')->where('jenis_tindakan', $action);
        foreach ($where as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, is_bool($value) ? $value : (string) $value);
        }

        return $query;
    }
}
