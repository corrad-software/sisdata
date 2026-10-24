<?php

namespace App\Modules\Gis\Support;

/**
 * Boolean SQL indicators about a lot (alias `l`, current geometry `ds`) read
 * from other modules' tables — used by the land-status layer (SRS-GIS-13)
 * and the interim risk layer (mandatory layer 9). Each degrades to FALSE when
 * the owning module's table/columns don't exist (yet).
 */
final class PenunjukLot
{
    public static function kesAktif(): string
    {
        if (! Jadual::has('kes_mahkamah_lot', ['kes_mahkamah_id', 'lot_id']) || ! Jadual::has('kes_mahkamah', ['id', 'status'])) {
            return 'FALSE';
        }

        return "EXISTS (SELECT 1 FROM kes_mahkamah_lot kl JOIN kes_mahkamah k ON k.id = kl.kes_mahkamah_id WHERE kl.lot_id = l.id AND k.status NOT IN ('selesai', 'ditutup', 'tamat'))";
    }

    public static function aduanTerbuka(): string
    {
        if (! Jadual::has('aduan', ['lot_id', 'status'])) {
            return 'FALSE';
        }

        return "EXISTS (SELECT 1 FROM aduan a WHERE a.lot_id = l.id AND a.status NOT IN ('selesai', 'ditutup', 'ditolak', 'dibatalkan'))";
    }

    public static function cukaiTertunggak(): string
    {
        if (! Jadual::has('cukai', ['lot_id', 'status_bayaran', 'tarikh_akhir'])) {
            return 'FALSE';
        }

        return "EXISTS (SELECT 1 FROM cukai c WHERE c.lot_id = l.id AND (c.status_bayaran = 'tertunggak' OR (c.status_bayaran NOT IN ('dibayar', 'selesai') AND c.tarikh_akhir < CURRENT_DATE)))";
    }

    public static function pajakanAktif(): string
    {
        if (! Jadual::has('kontrak_pajakan', ['lot_id', 'status', 'tarikh_tamat'])) {
            return 'FALSE';
        }

        return "EXISTS (SELECT 1 FROM kontrak_pajakan p WHERE p.lot_id = l.id AND p.status = 'aktif' AND (p.tarikh_tamat IS NULL OR p.tarikh_tamat >= CURRENT_DATE))";
    }

    public static function pajakanTamat(): string
    {
        if (! Jadual::has('kontrak_pajakan', ['lot_id', 'status', 'tarikh_tamat'])) {
            return 'FALSE';
        }

        return "EXISTS (SELECT 1 FROM kontrak_pajakan p WHERE p.lot_id = l.id AND p.status = 'aktif' AND p.tarikh_tamat < CURRENT_DATE)";
    }

    public static function tanpaHakMilik(): string
    {
        if (! Jadual::has('hak_milik', ['lot_id', 'status_semasa', 'deleted_at'])) {
            return 'FALSE';
        }

        return "NOT EXISTS (SELECT 1 FROM hak_milik h WHERE h.lot_id = l.id AND h.deleted_at IS NULL AND h.status_semasa = 'aktif')";
    }

    public static function bertindih(): string
    {
        return "ds.status_pengesahan = 'bertindih'";
    }

    public static function belumDisahkan(): string
    {
        return "ds.status_pengesahan = 'belum_disahkan'";
    }

    public static function berpotensi(): string
    {
        return "l.tag @> '[\"berpotensi_dibangunkan\"]'::jsonb";
    }
}
