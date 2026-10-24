<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\SuratDijana;
use App\Modules\Pjk\Models\TemplatSurat;
use App\Modules\Pjk\Support\Dokumen;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-PJK-09: configurable letter templates (offer, payment reminder,
 * expiry, warning, termination) with merge fields filled from the contract
 * and a running reference number; generated letters are kept in DMS.
 * Editing a template creates a new version (old versions stay for audit).
 */
class SuratService
{
    public const MEDAN = ['no_rujukan_surat', 'tarikh_surat', 'nama_penyewa', 'alamat_penyewa', 'no_kontrak', 'nama_premis', 'no_lot', 'tarikh_mula', 'tarikh_tamat', 'kadar', 'deposit', 'baki_tunggakan', 'tarikh_akhir_bayaran'];

    public function __construct(private DocumentStore $documents, private LotRegistry $lots, private LejarService $lejar) {}

    /** @param array<string, mixed> $input */
    public function updateTemplate(TemplatSurat $templat, array $input, User $user): TemplatSurat
    {
        $data = Validator::make($input, [
            'tajuk' => ['required', 'string', 'max:200'],
            'kandungan' => ['required', 'string', 'max:20000'],
            'jenis_surat' => ['sometimes', Rule::in(array_keys(Kod::JENIS_SURAT))],
        ], [], ['tajuk' => 'tajuk', 'kandungan' => 'kandungan'])->validate();
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $data['kandungan'], $m);
        $unknown = array_diff(array_unique($m[1]), self::MEDAN);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['kandungan' => __('pjk::pjk.ralat.medan_tidak_dikenali', ['medan' => implode(', ', $unknown)])]);
        }

        return DB::transaction(function () use ($templat, $data, $user) {
            TemplatSurat::query()->where('kod', $templat->kod)->update(['status_aktif' => false]);
            $next = (int) TemplatSurat::query()->where('kod', $templat->kod)->max('versi') + 1;

            return TemplatSurat::query()->create([
                'kod' => $templat->kod, 'jenis_surat' => $data['jenis_surat'] ?? $templat->jenis_surat, 'tajuk' => $data['tajuk'],
                'kandungan' => $data['kandungan'], 'medan_gabungan' => self::MEDAN, 'versi' => $next, 'status_aktif' => true, 'id_pengemas_kini' => $user->id,
            ]);
        });
    }

    public function generate(Kontrak $kontrak, TemplatSurat $templat, User $user): SuratDijana
    {
        if (! $templat->status_aktif) {
            throw ValidationException::withMessages(['templat' => __('pjk::pjk.ralat.templat_tidak_aktif')]);
        }

        $surat = DB::transaction(function () use ($kontrak, $templat, $user) {
            $no = Penomboran::next('surat');
            $text = $this->merge($templat->kandungan, $this->fields($kontrak) + ['no_rujukan_surat' => $no]);

            return SuratDijana::query()->create([
                'kontrak_id' => $kontrak->id, 'templat_id' => $templat->id, 'no_rujukan_surat' => $no, 'kandungan' => $text, 'id_pegawai' => $user->id,
            ]);
        });

        try {
            $pdf = Dokumen::pdf($templat->tajuk, [['teks' => $surat->kandungan]], $surat->no_rujukan_surat);
            $id = $this->documents->store(Dokumen::file($pdf, str_replace('/', '-', $surat->no_rujukan_surat).'.pdf'), Kontrak::SUBJECT, $kontrak->id, 'surat_pjk', $user, 'sulit', ['jenis_surat' => $templat->jenis_surat, 'no_rujukan_surat' => $surat->no_rujukan_surat]);
            $surat->forceFill(['id_dokumen' => $id])->save();
        } catch (Throwable $e) {
            report($e);
        }

        return $surat;
    }

    /** @param array<string, string> $fields */
    public function merge(string $content, array $fields): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => $fields[$m[1]] ?? '', $content);
    }

    /** @return array<string, string> */
    public function fields(Kontrak $kontrak): array
    {
        $penyewa = Penyewa::query()->find($kontrak->penyewa_id);
        $premis = PremisAset::query()->withoutGlobalScope('access')->find($kontrak->premis_id);
        $lot = $this->lots->find($kontrak->lot_id);
        $next = JadualCaj::query()->where('kontrak_id', $kontrak->id)->whereIn('status_caj', ['belum_bayar', 'separa'])->orderBy('tarikh_akhir')->value('tarikh_akhir');
        $date = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';

        return [
            'tarikh_surat' => Carbon::today()->format('d/m/Y'),
            'nama_penyewa' => (string) ($penyewa->nama ?? $kontrak->penyewa),
            'alamat_penyewa' => trim(($penyewa->alamat ?? '').' '.($penyewa->poskod ?? '')),
            'no_kontrak' => (string) $kontrak->no_kontrak,
            'nama_premis' => (string) ($premis->nama_premis ?? '—'),
            'no_lot' => (string) ($lot['no_lot'] ?? '—'),
            'tarikh_mula' => $date($kontrak->tarikh_mula),
            'tarikh_tamat' => $date($kontrak->tarikh_tamat),
            'kadar' => Wang::rm($kontrak->kadar),
            'deposit' => Wang::rm($kontrak->deposit),
            'baki_tunggakan' => Wang::rm(max(0, $this->lejar->bakiSen($kontrak->id))),
            'tarikh_akhir_bayaran' => $date($next ?? Carbon::today()->addDays(14)),
        ];
    }
}
