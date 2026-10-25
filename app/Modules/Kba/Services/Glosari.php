<?php

namespace App\Modules\Kba\Services;

/**
 * SRS-KBA-03: English questions over a mostly-Malay corpus. A small domain
 * glossary expands each English content word with its Malay term(s) for
 * keyword retrieval and evidence checks — deterministic, no model needed.
 */
final class Glosari
{
    /** @var array<string, list<string>> */
    private const EN_MS = [
        'land' => ['tanah'], 'lot' => ['lot'], 'title' => ['hak', 'milik'], 'titles' => ['hak', 'milik'], 'ownership' => ['pemilikan', 'milik'],
        'owner' => ['pemilik'], 'owners' => ['pemilik'], 'lease' => ['pajakan', 'sewaan'], 'leases' => ['pajakan'], 'rent' => ['sewa', 'sewaan'],
        'tax' => ['cukai'], 'taxes' => ['cukai'], 'quit' => ['cukai'], 'document' => ['dokumen'], 'documents' => ['dokumen'], 'file' => ['fail'],
        'files' => ['fail'], 'upload' => ['muat', 'naik'], 'uploaded' => ['muat', 'naik'], 'approval' => ['kelulusan', 'lulus'], 'approve' => ['lulus', 'kelulusan'],
        'approves' => ['lulus', 'kelulusan'], 'approved' => ['lulus', 'diluluskan'], 'approver' => ['pelulus'], 'workflow' => ['aliran', 'workflow'],
        'user' => ['pengguna'], 'users' => ['pengguna'], 'role' => ['peranan'], 'roles' => ['peranan'], 'permission' => ['kebenaran'],
        'permissions' => ['kebenaran'], 'access' => ['capaian'], 'audit' => ['audit'], 'trail' => ['jejak', 'trail'], 'log' => ['log'],
        'notification' => ['notifikasi', 'pemberitahuan'], 'notifications' => ['notifikasi'], 'report' => ['laporan'], 'reports' => ['laporan'],
        'search' => ['carian', 'cari'], 'complaint' => ['aduan'], 'complaints' => ['aduan'], 'court' => ['mahkamah'], 'case' => ['kes'],
        'cases' => ['kes'], 'legal' => ['perundangan', 'undang'], 'asset' => ['aset'], 'assets' => ['aset'], 'map' => ['peta'], 'maps' => ['peta'],
        'spatial' => ['spatial', 'ruang'], 'knowledge' => ['pengetahuan'], 'base' => ['pangkalan'], 'ticket' => ['tiket'], 'tickets' => ['tiket'],
        'support' => ['sokongan'], 'password' => ['kata', 'laluan'], 'login' => ['log', 'masuk'], 'backup' => ['sandaran'], 'recovery' => ['pemulihan'],
        'disaster' => ['bencana'], 'encryption' => ['penyulitan'], 'encrypted' => ['disulitkan', 'penyulitan'], 'district' => ['daerah'], 'districts' => ['daerah'],
        'acquisition' => ['pemerolehan'], 'monitoring' => ['pemantauan'], 'inspection' => ['pemeriksaan', 'pemantauan'], 'requirement' => ['keperluan'],
        'requirements' => ['keperluan'], 'answer' => ['jawapan'], 'answers' => ['jawapan'], 'question' => ['soalan'], 'questions' => ['soalan'],
        'source' => ['sumber'], 'sources' => ['sumber'], 'citation' => ['petikan'], 'citations' => ['petikan'], 'index' => ['indeks'], 'indexed' => ['diindeks', 'indeks'],
        'indexing' => ['pengindeksan', 'indeks'], 'classification' => ['klasifikasi'], 'classified' => ['berklasifikasi', 'sulit'], 'secret' => ['rahsia'],
        'confidential' => ['sulit'], 'public' => ['awam'], 'internal' => ['dalaman'], 'version' => ['versi'], 'versions' => ['versi'], 'article' => ['artikel'],
        'articles' => ['artikel'], 'feedback' => ['maklum', 'balas'], 'limit' => ['had'], 'rate' => ['kadar'], 'model' => ['model'], 'local' => ['tempatan'],
        'cloud' => ['awan', 'cloud'], 'retention' => ['retensi', 'penyimpanan'], 'archive' => ['arkib'], 'disposal' => ['pelupusan'], 'expiry' => ['luput', 'tamat'],
        'payment' => ['bayaran'], 'payments' => ['bayaran'], 'invoice' => ['invois'], 'contract' => ['kontrak'], 'contracts' => ['kontrak'], 'tenant' => ['penyewa'],
        'waqf' => ['wakaf'], 'endowment' => ['wakaf'], 'portal' => ['portal'], 'dashboard' => ['dashboard'], 'integration' => ['integrasi'],
        'migration' => ['migrasi'], 'testing' => ['pengujian'], 'training' => ['latihan'], 'warranty' => ['jaminan'], 'availability' => ['ketersediaan'],
        'performance' => ['prestasi'], 'security' => ['keselamatan'], 'session' => ['sesi'], 'timeout' => ['tamat', 'sesi'], 'module' => ['modul'],
        'modules' => ['modul'], 'data' => ['data'], 'database' => ['pangkalan', 'data'], 'table' => ['jadual'], 'tables' => ['jadual'], 'field' => ['medan'],
        'fields' => ['medan'], 'entity' => ['entiti'], 'entities' => ['entiti'], 'process' => ['proses'], 'flow' => ['aliran'], 'status' => ['status'],
        'record' => ['rekod'], 'records' => ['rekod'], 'change' => ['perubahan', 'ubah'], 'changes' => ['perubahan'], 'history' => ['sejarah'],
        'export' => ['eksport'], 'import' => ['import'], 'survey' => ['ukur'], 'subdivision' => ['pecah', 'sempadan'], 'conversion' => ['tukar', 'syarat'],
        'district officer' => ['pegawai', 'daerah'], 'officer' => ['pegawai'], 'administrator' => ['pentadbir'], 'admin' => ['pentadbir'],
        'size' => ['saiz'], 'office' => ['pejabat'], 'maximum' => ['maksimum', 'had'], 'period' => ['tempoh'], 'days' => ['hari'], 'months' => ['bulan'],
        'years' => ['tahun'], 'key' => ['kunci'], 'keys' => ['kunci'], 'rotated' => ['diputar'], 'counter' => ['kaunter'], 'hours' => ['waktu', 'jam'],
        'opening' => ['dibuka', 'buka'], 'open' => ['dibuka', 'buka'], 'scanned' => ['diimbas', 'imbas'], 'antivirus' => ['antivirus'],
        'injection' => ['injection', 'suntikan'], 'prompt' => ['prompt'], 'grounded' => ['bergrounding'], 'insufficient' => ['tiada', 'mencukupi'],
    ];

    /** @return list<string> Malay equivalents of an (English) token, or [] */
    public static function ms(string $token): array
    {
        return self::EN_MS[$token] ?? [];
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string> tokens plus their Malay equivalents (distinct)
     */
    public static function expand(array $tokens): array
    {
        $out = $tokens;
        foreach ($tokens as $t) {
            foreach (self::ms($t) as $m) {
                if (! in_array($m, $out, true)) {
                    $out[] = $m;
                }
            }
        }

        return $out;
    }
}
