<?php

namespace App\Modules\Pws\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Kba\Contracts\LlmGateway;
use App\Modules\Pws\Models\Kandungan;
use App\Modules\Pws\Models\StatistikTerbitan;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-PWS-04: portal content governance.
 *  - officers draft by hand or with the LOCAL model (Kba\Contracts\LlmGateway
 *    — Ollama on loopback; never a public AI service). AI output is always a
 *    draft flagged dijana_ai, with the prompt and model kept;
 *  - publishing needs approval in MOD-WFE (SRS-GEN-03); WFE never lets the
 *    submitter approve their own item, so a second officer reviews;
 *  - only `diterbitkan` items are served on the public API.
 * Statistics snapshots follow the same path (flow pws.terbitan_statistik).
 */
class KandunganService
{
    public const ALIRAN_KANDUNGAN = 'pws.terbitan_kandungan';

    public const ALIRAN_STATISTIK = 'pws.terbitan_statistik';

    public const SUBJEK_KANDUNGAN = 'pws_kandungan_cms';

    public const SUBJEK_STATISTIK = 'pws_statistik_terbitan';

    private const SISTEM = <<<'TXT'
Anda pembantu penulisan kandungan untuk "Portal Hartanah Wakaf Selangor" (Majlis Agama Islam Selangor).
Tulis draf yang sopan, tepat dan ringkas dalam Bahasa Melayu standard (atau Bahasa Inggeris jika diminta).
Jangan reka angka, tarikh, nama individu, nombor lot atau dasar yang tidak diberi dalam arahan; gunakan [ISI: ...] sebagai ruang untuk pegawai melengkapkan.
Jangan sertakan data peribadi. Gunakan nama rasmi "Portal Hartanah Wakaf Selangor" — bukan "Portal Wakaf".
Format: baris pertama ialah tajuk (tanpa tanda #), diikuti satu baris kosong, kemudian isi kandungan dalam perenggan biasa.
TXT;

    public function __construct(private WorkflowEngine $workflow, private AuditLogger $audit) {}

    /** @param array<string, mixed> $input */
    public function create(array $input, User $by): Kandungan
    {
        $data = $this->validate($input, true);

        return Kandungan::query()->create($data + [
            'slug' => $this->slug($data['tajuk_bm']), 'status_terbitan' => 'draf', 'dijana_ai' => false, 'id_pengarang' => $by->id,
        ]);
    }

    /** @param array<string, mixed> $input */
    public function update(Kandungan $k, array $input): Kandungan
    {
        if (! in_array($k->status_terbitan, ['draf', 'ditolak', 'ditarik'], true)) {
            throw ValidationException::withMessages(['status_terbitan' => [__('pws::pws.ralat.kandungan_dikunci')]]);
        }
        $k->fill($this->validate($input, false));
        if ($k->status_terbitan !== 'draf') {
            $k->status_terbitan = 'draf';
        }
        $k->save();

        return $k;
    }

    /**
     * Draft with the local model. Throws 503 when no local model is available
     * (the officer writes by hand instead).
     *
     * @param  array<string, mixed>  $input
     */
    public function draftWithAi(array $input, User $by, LlmGateway $llm): Kandungan
    {
        $data = Validator::make($input, [
            'jenis' => ['required', Rule::in(array_keys(Kandungan::JENIS))],
            'arahan' => ['required', 'string', 'min:10', 'max:2000'],
            'bahasa' => ['nullable', Rule::in(['ms', 'en'])],
        ], [], ['jenis' => 'jenis kandungan', 'arahan' => 'arahan (prompt)'])->validate();

        if ($llm->model() === null || ! $llm->available()) {
            throw new HttpException(503, __('pws::pws.ralat.ai_tiada'));
        }
        $english = ($data['bahasa'] ?? 'ms') === 'en';
        $user = 'Jenis kandungan: '.Kandungan::JENIS[$data['jenis']]."\nBahasa: ".($english ? 'Bahasa Inggeris' : 'Bahasa Melayu')."\nArahan pegawai:\n".$data['arahan'];

        try {
            $out = $llm->generate(self::SISTEM, $user);
        } catch (RuntimeException $e) {
            report($e);
            throw new HttpException(503, __('pws::pws.ralat.ai_gagal'));
        }
        [$title, $body] = $this->split($out['teks']);
        $title = $title !== '' ? $title : Str::limit($data['arahan'], 120, '');

        $k = Kandungan::query()->create([
            'jenis' => $data['jenis'],
            'slug' => $this->slug($title),
            'tajuk_bm' => $english ? $title.' (draf)' : $title,
            'tajuk_bi' => $english ? $title : null,
            'kandungan_bm' => $english ? '[ISI: terjemahan Bahasa Melayu]' : $body,
            'kandungan_bi' => $english ? $body : null,
            'status_terbitan' => 'draf',
            'dijana_ai' => true,
            'prompt_ai' => $data['arahan'],
            'model_ai' => $llm->model(),
            'id_pengarang' => $by->id,
        ]);
        $this->audit->record('jana_ai', 'pws', 'pws_kandungan_cms', $k->id, meta: ['model' => $llm->model(), 'token' => $out['token'], 'aksara_prompt' => mb_strlen($data['arahan'])]);

        return $k;
    }

    public function submit(Kandungan $k, User $by): Kandungan
    {
        if (! in_array($k->status_terbitan, ['draf', 'ditolak', 'ditarik'], true)) {
            throw ValidationException::withMessages(['status_terbitan' => [__('pws::pws.ralat.kandungan_dikunci')]]);
        }
        if ($k->dijana_ai && str_contains($k->kandungan_bm.' '.$k->kandungan_bi, '[ISI:')) {
            throw ValidationException::withMessages(['kandungan_bm' => [__('pws::pws.ralat.ruang_isi')]]);
        }
        $k->forceFill([
            'id_aliran' => $this->start(self::ALIRAN_KANDUNGAN, self::SUBJEK_KANDUNGAN, $k->id, $by, ['jenis' => $k->jenis, 'dijana_ai' => $k->dijana_ai], __('pws::pws.aliran.tajuk_kandungan', ['tajuk' => Str::limit($k->tajuk_bm, 80)])),
            'status_terbitan' => 'dalam_semakan', 'catatan_semakan' => null,
        ])->save();

        return $k;
    }

    public function withdraw(Kandungan $k, string $reason): Kandungan
    {
        if ($k->status_terbitan !== 'diterbitkan') {
            throw ValidationException::withMessages(['status_terbitan' => [__('pws::pws.ralat.bukan_diterbitkan')]]);
        }
        $k->forceFill(['status_terbitan' => 'ditarik', 'catatan_semakan' => $reason])->save();

        return $k;
    }

    public function submitStatistics(StatistikTerbitan $s, User $by): StatistikTerbitan
    {
        if (! in_array($s->status, ['draf', 'ditolak', 'ditarik'], true)) {
            throw ValidationException::withMessages(['status' => [__('pws::pws.ralat.kandungan_dikunci')]]);
        }
        $s->forceFill([
            'id_aliran' => $this->start(self::ALIRAN_STATISTIK, self::SUBJEK_STATISTIK, $s->id, $by, ['tempoh' => $s->tempoh], __('pws::pws.aliran.tajuk_statistik', ['tajuk' => $s->tajuk])),
            'status' => 'dalam_semakan',
        ])->save();

        return $s;
    }

    /** WorkflowCompleted listener: apply the reviewer's decision. */
    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        $approved = $event->outcome === 'lulus';
        if ($event->flowCode === self::ALIRAN_KANDUNGAN && $event->subjectType === self::SUBJEK_KANDUNGAN) {
            $k = Kandungan::query()->find($event->subjectId);
            if ($k === null || $k->status_terbitan !== 'dalam_semakan') {
                return;
            }
            $k->forceFill([
                'status_terbitan' => $approved ? 'diterbitkan' : ($event->outcome === 'tolak' ? 'ditolak' : 'draf'),
                'id_penyemak' => $event->decidedBy, 'catatan_semakan' => $event->remarks,
                'tarikh_terbit' => $approved ? now() : $k->tarikh_terbit,
            ])->save();
        } elseif ($event->flowCode === self::ALIRAN_STATISTIK && $event->subjectType === self::SUBJEK_STATISTIK) {
            $s = StatistikTerbitan::query()->find($event->subjectId);
            if ($s === null || $s->status !== 'dalam_semakan') {
                return;
            }
            $s->forceFill([
                'status' => $approved ? 'diterbitkan' : ($event->outcome === 'tolak' ? 'ditolak' : 'draf'),
                'diluluskan_oleh' => $approved ? $event->decidedBy : null, 'tarikh_terbit' => $approved ? now() : null,
            ])->save();
        }
    }

    /** @return array<string, mixed> Public (Awam) shape — no author, prompt or reviewer. */
    public static function presentPublic(Kandungan $k): array
    {
        return [
            'slug' => $k->slug, 'jenis' => $k->jenis, 'tajuk_bm' => $k->tajuk_bm, 'tajuk_bi' => $k->tajuk_bi,
            'kandungan_bm' => $k->kandungan_bm, 'kandungan_bi' => $k->kandungan_bi, 'ringkasan' => $k->ringkasan,
            'kemajuan_peratus' => $k->jenis === 'projek' ? $k->kemajuan_peratus : null, 'lokasi' => $k->jenis === 'projek' ? $k->lokasi : null,
            'dibantu_ai' => $k->dijana_ai, 'tarikh_terbit' => $k->tarikh_terbit?->toIso8601String(), 'dikemas_kini' => $k->updated_at->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $context */
    private function start(string $flow, string $subject, int $id, User $by, array $context, string $title): string
    {
        try {
            return $this->workflow->start($flow, $subject, $id, $by, $context, $title);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['aliran' => [__('pws::pws.ralat.aliran_tiada', ['aliran' => $flow])]]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'jenis' => [$req, Rule::in(array_keys(Kandungan::JENIS))],
            'tajuk_bm' => [$req, 'string', 'max:250'],
            'tajuk_bi' => ['sometimes', 'nullable', 'string', 'max:250'],
            'kandungan_bm' => [$req, 'string', 'max:50000'],
            'kandungan_bi' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'ringkasan' => ['sometimes', 'nullable', 'string', 'max:500'],
            'kemajuan_peratus' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'lokasi' => ['sometimes', 'nullable', 'string', 'max:200'],
        ], [], ['jenis' => 'jenis', 'tajuk_bm' => 'tajuk (BM)', 'tajuk_bi' => 'tajuk (BI)', 'kandungan_bm' => 'kandungan (BM)', 'kandungan_bi' => 'kandungan (BI)', 'kemajuan_peratus' => 'kemajuan'])->validate();
    }

    /** @return array{0: string, 1: string} */
    private function split(string $text): array
    {
        $text = trim(str_replace("\r", '', $text));
        $lines = explode("\n", $text, 2);
        $title = trim(ltrim($lines[0], "# \t*"));
        $body = trim($lines[1] ?? '');
        if ($body === '' || mb_strlen($title) > 120) {
            // Small local models don't always follow the "title line" format: keep everything as body.
            $first = preg_split('/(?<=[.!?])\s/u', $text, 2)[0] ?? $text;

            return [Str::limit(trim(ltrim($first, "# \t*")), 100), $text];
        }

        return [Str::limit($title, 240, ''), $body];
    }

    private function slug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 100, '')) ?: 'kandungan';
        $slug = $base;
        $i = 1;
        while (Kandungan::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
