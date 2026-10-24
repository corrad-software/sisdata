<?php

namespace App\Modules\Wfe\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\DefinisiLog;
use App\Modules\Wfe\Models\Eskalasi;
use App\Modules\Wfe\Models\Pelulus;
use App\Modules\Wfe\Models\Peralihan;
use App\Modules\Wfe\Models\Peringkat;
use App\Modules\Wfe\Models\Susulan;
use App\Modules\Wfe\Support\WfeException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-WFE-01..05, 12: versioned flow definitions.
 *
 * The definition travels as one JSON structure (the same one the form
 * editor and the React Flow editor send) and is validated, then stored
 * normalised in wf_peringkat / wf_pelulus / wf_peralihan / wf_syarat /
 * wf_eskalasi / wf_susulan. Only drafts are editable; active versions are
 * changed by creating a new version, and nothing is ever deleted.
 */
class DefinitionService
{
    public const KAEDAH = ['borang', 'editor_visual', 'api', 'salin', 'ai', 'sistem'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> normalised structure
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'kod_aliran' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/'],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'modul' => ['required', 'string', 'max:20'],
            'jenis_transaksi' => ['required', 'string', 'max:100'],
            'templat' => ['sometimes', 'boolean'],
            'peringkat' => ['required', 'array', 'min:1', 'max:30'],
            'peringkat.*.kod' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'distinct', Rule::notIn([Peralihan::MULA, Peralihan::TAMAT])],
            'peringkat.*.nama' => ['required', 'string', 'max:255'],
            'peringkat.*.mod_kelulusan' => ['required', Rule::in([Peringkat::SEMUA, Peringkat::MANA_MANA, Peringkat::KUORUM])],
            'peringkat.*.kuorum' => ['nullable', 'integer', 'min:1'],
            'peringkat.*.sla_nilai' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'peringkat.*.sla_unit' => ['nullable', Rule::in(['hari_bekerja', 'jam'])],
            'peringkat.*.perlu_mfa' => ['sometimes', 'boolean'],
            'peringkat.*.perlu_justifikasi' => ['sometimes', 'boolean'],
            'peringkat.*.pelulus' => ['required', 'array', 'min:1', 'max:20'],
            'peringkat.*.pelulus.*.jenis' => ['required', Rule::in(Pelulus::JENIS)],
            'peringkat.*.pelulus.*.nilai' => ['nullable', 'string', 'max:100'],
            'peringkat.*.pelulus.*.label' => ['nullable', 'string', 'max:255'],
            'peringkat.*.eskalasi' => ['sometimes', 'array', 'max:10'],
            'peringkat.*.eskalasi.*.aras' => ['required', 'integer', 'min:1', 'max:10'],
            'peringkat.*.eskalasi.*.tempoh_nilai' => ['required', 'integer', 'min:0', 'max:10000'],
            'peringkat.*.eskalasi.*.tempoh_unit' => ['required', Rule::in(['hari_bekerja', 'jam'])],
            'peringkat.*.eskalasi.*.jenis_penerima' => ['required', Rule::in(Eskalasi::JENIS)],
            'peringkat.*.eskalasi.*.nilai_penerima' => ['nullable', 'string', 'max:100'],
            'peralihan' => ['sometimes', 'array', 'max:200'],
            'peralihan.*.dari' => ['required', 'string', 'max:50'],
            'peralihan.*.ke' => ['required', 'string', 'max:50'],
            'peralihan.*.keutamaan' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'peralihan.*.logik' => ['nullable', Rule::in(['semua', 'mana_mana'])],
            'peralihan.*.label' => ['nullable', 'string', 'max:255'],
            'peralihan.*.syarat' => ['sometimes', 'array', 'max:20'],
            'peralihan.*.syarat.*.medan' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.]+$/'],
            'peralihan.*.syarat.*.operator' => ['required', Rule::in(array_keys(ConditionEvaluator::OPERATORS))],
            'peralihan.*.syarat.*.nilai' => ['nullable'],
            'susulan' => ['sometimes', 'array', 'max:20'],
            'susulan.*.bila' => ['required', Rule::in(['lulus', 'tolak', 'semua'])],
            'susulan.*.tajuk' => ['required', 'string', 'max:255'],
            'susulan.*.jenis_penerima' => ['required', Rule::in(Susulan::JENIS)],
            'susulan.*.nilai_penerima' => ['nullable', 'string', 'max:100'],
            'susulan.*.sla_nilai' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'susulan.*.sla_unit' => ['nullable', Rule::in(['hari_bekerja', 'jam'])],
            'susun_atur' => ['nullable', 'array'],
        ], [], ['peringkat' => __('wfe::wfe.attr.peringkat')]);

        $validator->after(function ($v) use ($data) {
            foreach ($this->graphErrors($data) as $field => $message) {
                $v->errors()->add($field, $message);
            }
        });

        $valid = $validator->validate();

        $stages = array_map(fn (array $s, int $i) => [
            'kod' => $s['kod'],
            'nama' => $s['nama'],
            'no_urutan' => $i + 1,
            'mod_kelulusan' => $s['mod_kelulusan'],
            'kuorum' => $s['mod_kelulusan'] === Peringkat::KUORUM ? (int) ($s['kuorum'] ?? 1) : null,
            'sla_nilai' => $s['sla_nilai'] ?? null,
            'sla_unit' => $s['sla_unit'] ?? 'hari_bekerja',
            'perlu_mfa' => (bool) ($s['perlu_mfa'] ?? false),
            'perlu_justifikasi' => (bool) ($s['perlu_justifikasi'] ?? false),
            'pelulus' => array_map(fn ($p) => Arr::only($p + ['nilai' => null, 'label' => null], ['jenis', 'nilai', 'label']), $s['pelulus']),
            'eskalasi' => array_values(Arr::sort(array_map(fn ($e) => Arr::only($e + ['nilai_penerima' => null], ['aras', 'tempoh_nilai', 'tempoh_unit', 'jenis_penerima', 'nilai_penerima']), $s['eskalasi'] ?? []), fn ($e) => $e['aras'])),
        ], $valid['peringkat'], array_keys($valid['peringkat']));

        return [
            'kod_aliran' => $valid['kod_aliran'],
            'nama' => $valid['nama'],
            'keterangan' => $valid['keterangan'] ?? null,
            'modul' => $valid['modul'],
            'jenis_transaksi' => $valid['jenis_transaksi'],
            'templat' => (bool) ($valid['templat'] ?? false),
            'peringkat' => $stages,
            'peralihan' => $this->transitions($data),
            'susulan' => array_map(fn ($f) => [
                'bila' => $f['bila'], 'tajuk' => $f['tajuk'], 'jenis_penerima' => $f['jenis_penerima'],
                'nilai_penerima' => $f['nilai_penerima'] ?? null, 'sla_nilai' => $f['sla_nilai'] ?? null, 'sla_unit' => $f['sla_unit'] ?? 'hari_bekerja',
            ], $valid['susulan'] ?? []),
            'susun_atur' => $valid['susun_atur'] ?? null,
        ];
    }

    /**
     * Transitions as given, or — when none are given (simple form editor) —
     * a serial chain mula → stage 1 → … → tamat.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{dari: string, ke: string, keutamaan: int, logik: string, label: string|null, syarat: list<array{medan: string, operator: string, nilai: mixed}>}>
     */
    private function transitions(array $data): array
    {
        $given = $data['peralihan'] ?? [];
        if ($given === []) {
            $nodes = array_merge([Peralihan::MULA], array_column($data['peringkat'] ?? [], 'kod'), [Peralihan::TAMAT]);
            $chain = [];
            for ($i = 0; $i < count($nodes) - 1; $i++) {
                $chain[] = ['dari' => $nodes[$i], 'ke' => $nodes[$i + 1], 'keutamaan' => 0, 'logik' => 'semua', 'label' => null, 'syarat' => []];
            }

            return $chain;
        }

        return array_values(array_map(fn ($t) => [
            'dari' => $t['dari'],
            'ke' => $t['ke'],
            'keutamaan' => (int) ($t['keutamaan'] ?? 0),
            'logik' => $t['logik'] ?? 'semua',
            'label' => $t['label'] ?? null,
            'syarat' => array_values(array_map(fn ($c) => ['medan' => $c['medan'], 'operator' => $c['operator'], 'nilai' => $c['nilai'] ?? null], $t['syarat'] ?? [])),
        ], $given));
    }

    /**
     * Structural checks the field rules can't express: nodes exist, every
     * node has a default path, no cycles, every stage reachable, sensible
     * quorum and escalation levels.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function graphErrors(array $data): array
    {
        $errors = [];
        $stages = is_array($data['peringkat'] ?? null) ? $data['peringkat'] : [];
        $codes = array_values(array_filter(array_map(fn ($s) => is_array($s) ? ($s['kod'] ?? null) : null, $stages), 'is_string'));
        if ($codes === []) {
            return $errors;
        }

        foreach ($stages as $i => $s) {
            if (! is_array($s)) {
                continue;
            }
            $slots = is_array($s['pelulus'] ?? null) ? count($s['pelulus']) : 0;
            if (($s['mod_kelulusan'] ?? null) === Peringkat::KUORUM && ((int) ($s['kuorum'] ?? 0) < 1 || (int) ($s['kuorum'] ?? 0) > $slots)) {
                $errors["peringkat.$i.kuorum"] = __('wfe::wfe.validation.kuorum', ['max' => $slots]);
            }
            foreach ((array) ($s['pelulus'] ?? []) as $j => $p) {
                if (is_array($p) && ($p['jenis'] ?? null) !== 'penyelia_pemohon' && trim((string) ($p['nilai'] ?? '')) === '') {
                    $errors["peringkat.$i.pelulus.$j.nilai"] = __('wfe::wfe.validation.pelulus_nilai');
                }
            }
            $levels = array_map(fn ($e) => is_array($e) ? ($e['aras'] ?? null) : null, (array) ($s['eskalasi'] ?? []));
            if (count($levels) !== count(array_unique($levels))) {
                $errors["peringkat.$i.eskalasi"] = __('wfe::wfe.validation.eskalasi_aras');
            }
            foreach ((array) ($s['eskalasi'] ?? []) as $j => $e) {
                if (is_array($e) && ($e['jenis_penerima'] ?? null) !== 'penyelia' && trim((string) ($e['nilai_penerima'] ?? '')) === '') {
                    $errors["peringkat.$i.eskalasi.$j.nilai_penerima"] = __('wfe::wfe.validation.pelulus_nilai');
                }
            }
        }

        $edges = $this->transitions($data);
        $nodes = array_merge([Peralihan::MULA, Peralihan::TAMAT], $codes);
        $out = [];
        foreach ($edges as $k => $e) {
            if (! in_array($e['dari'], $nodes, true) || $e['dari'] === Peralihan::TAMAT) {
                $errors["peralihan.$k.dari"] = __('wfe::wfe.validation.nod_tiada', ['nod' => $e['dari']]);

                continue;
            }
            if (! in_array($e['ke'], $nodes, true) || $e['ke'] === Peralihan::MULA || $e['ke'] === $e['dari']) {
                $errors["peralihan.$k.ke"] = __('wfe::wfe.validation.nod_tiada', ['nod' => $e['ke']]);

                continue;
            }
            $out[$e['dari']][] = $e;
        }
        if ($errors !== []) {
            return $errors;
        }

        foreach (array_merge([Peralihan::MULA], $codes) as $node) {
            $hasDefault = collect($out[$node] ?? [])->contains(fn ($e) => $e['syarat'] === []);
            if (! $hasDefault) {
                $errors['peralihan'] = __('wfe::wfe.validation.tiada_lalai', ['nod' => $node]);

                return $errors;
            }
        }

        // Cycle check (DFS) and reachability from "mula".
        $state = [];
        $reached = [];
        $cycle = false;
        $visit = function (string $n) use (&$visit, &$state, &$reached, &$cycle, $out) {
            $state[$n] = 1;
            $reached[$n] = true;
            foreach ($out[$n] ?? [] as $e) {
                $to = $e['ke'];
                if (($state[$to] ?? 0) === 1) {
                    $cycle = true;
                } elseif (($state[$to] ?? 0) === 0) {
                    $visit($to);
                }
            }
            $state[$n] = 2;
        };
        $visit(Peralihan::MULA);

        if ($cycle) {
            $errors['peralihan'] = __('wfe::wfe.validation.kitaran');
        } elseif ($missing = array_values(array_diff($codes, array_keys($reached)))) {
            $errors['peralihan'] = __('wfe::wfe.validation.tidak_tercapai', ['nod' => implode(', ', $missing)]);
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user, string $method = 'borang', ?string $note = null): Definisi
    {
        $structure = $this->validate($data);

        if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
            throw ValidationException::withMessages(['kod_aliran' => __('wfe::wfe.validation.kod_wujud')]);
        }

        return DB::transaction(function () use ($structure, $user, $method, $note) {
            $definition = Definisi::create([
                'kod_aliran' => $structure['kod_aliran'],
                'nama' => $structure['nama'],
                'keterangan' => $structure['keterangan'],
                'modul' => $structure['modul'],
                'jenis_transaksi' => $structure['jenis_transaksi'],
                'templat' => $structure['templat'],
                'no_versi' => 1,
                'status' => Definisi::DRAF,
                'id_pencipta' => $user->id,
                'kaedah_cipta' => $method === 'ai' ? 'ai' : 'manual',
                'susun_atur' => $structure['susun_atur'],
            ]);
            $this->writeChildren($definition, $structure);
            $this->log($definition, $user, 'cipta', $method, $note);

            return $definition;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Definisi $definition, array $data, User $user, string $method = 'borang', ?string $note = null): Definisi
    {
        if ($definition->status !== Definisi::DRAF) {
            throw new WfeException(__('wfe::wfe.error.bukan_draf'), 'definisi_bukan_draf', 409);
        }

        $data['kod_aliran'] = $definition->kod_aliran; // the flow code is fixed across versions
        $structure = $this->validate($data);
        $before = $this->export($definition);

        return DB::transaction(function () use ($definition, $structure, $user, $method, $note, $before) {
            $definition->fill([
                'nama' => $structure['nama'],
                'keterangan' => $structure['keterangan'],
                'modul' => $structure['modul'],
                'jenis_transaksi' => $structure['jenis_transaksi'],
                'templat' => $structure['templat'],
                'susun_atur' => $structure['susun_atur'],
            ])->save();
            $definition->peringkat()->delete();
            $definition->peralihan()->delete();
            $definition->susulan()->delete();
            $this->writeChildren($definition, $structure);
            $this->log($definition, $user, 'kemas_kini', $method, $note, ['sebelum' => Arr::except($before, ['log'])]);

            return $definition->refresh();
        });
    }

    /** New draft version of the same flow, copied from $source. */
    public function newVersion(Definisi $source, User $user): Definisi
    {
        if (Definisi::query()->where('kod_aliran', $source->kod_aliran)->whereIn('status', [Definisi::DRAF, Definisi::MENUNGGU])->exists()) {
            throw new WfeException(__('wfe::wfe.error.draf_wujud'), 'draf_wujud', 409);
        }

        return DB::transaction(function () use ($source, $user) {
            $next = (int) Definisi::query()->where('kod_aliran', $source->kod_aliran)->max('no_versi') + 1;
            $copy = $this->duplicate($source, $user, $source->kod_aliran, $source->nama, $next, (bool) $source->templat);
            $this->log($copy, $user, 'versi_baharu', 'salin', null, ['dari_versi' => $source->no_versi]);

            return $copy;
        });
    }

    /** SRS-WFE-04: copy a template (or any definition) as the base of a new flow. */
    public function copyAs(Definisi $source, User $user, string $flowCode, string $name, bool $template = false): Definisi
    {
        Validator::make(['kod_aliran' => $flowCode, 'nama' => $name], [
            'kod_aliran' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/', Rule::unique('wf_definisi', 'kod_aliran')],
            'nama' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($source, $user, $flowCode, $name, $template) {
            $copy = $this->duplicate($source, $user, $flowCode, $name, 1, $template);
            $this->log($copy, $user, 'salin', 'salin', null, ['dari' => $source->kod_aliran.' v'.$source->no_versi]);

            return $copy;
        });
    }

    /** Draft → awaiting a second administrator (security control: kelulusan pentadbir kedua). */
    public function requestActivation(Definisi $definition, User $user, ?string $note = null): Definisi
    {
        if ($definition->status !== Definisi::DRAF) {
            throw new WfeException(__('wfe::wfe.error.bukan_draf'), 'definisi_bukan_draf', 409);
        }
        $this->validate($this->export($definition)); // still consistent

        $definition->forceFill([
            'status' => Definisi::MENUNGGU,
            'diminta_aktif_oleh' => $user->id,
            'diminta_aktif_pada' => now(),
        ])->save();
        $this->log($definition, $user, 'mohon_aktif', 'api', $note);

        return $definition;
    }

    /**
     * Second administrator approves: this version becomes active, the
     * previous active version is archived. Running instances keep their
     * version (SRS-WFE-12).
     */
    public function approveActivation(Definisi $definition, User $approver, ?string $note = null): Definisi
    {
        if ($definition->status !== Definisi::MENUNGGU) {
            throw new WfeException(__('wfe::wfe.error.bukan_menunggu'), 'definisi_bukan_menunggu', 409);
        }
        if ($definition->diminta_aktif_oleh === $approver->id) {
            throw new WfeException(__('wfe::wfe.error.pentadbir_kedua'), 'pengasingan_tugas', 403);
        }

        DB::transaction(function () use ($definition, $approver, $note) {
            Definisi::query()->where('kod_aliran', $definition->kod_aliran)->where('status', Definisi::AKTIF)
                ->get()->each(function (Definisi $old) use ($approver) {
                    $old->forceFill(['status' => Definisi::ARKIB])->save();
                    $this->log($old, $approver, 'arkib', 'sistem');
                });
            $definition->forceFill([
                'status' => Definisi::AKTIF,
                'diluluskan_oleh' => $approver->id,
                'diluluskan_pada' => now(),
                'tarikh_berkuat_kuasa' => now(),
            ])->save();
            $this->log($definition, $approver, 'aktif', 'api', $note);
        });

        $this->audit->record('lulus', 'wfe', 'wf_definisi', $definition->id, reason: $note, meta: [
            'tindakan' => 'aktif_definisi', 'kod_aliran' => $definition->kod_aliran, 'no_versi' => $definition->no_versi,
        ]);

        return $definition->refresh();
    }

    public function rejectActivation(Definisi $definition, User $approver, string $reason): Definisi
    {
        if ($definition->status !== Definisi::MENUNGGU) {
            throw new WfeException(__('wfe::wfe.error.bukan_menunggu'), 'definisi_bukan_menunggu', 409);
        }
        $definition->forceFill(['status' => Definisi::DRAF, 'diminta_aktif_oleh' => null, 'diminta_aktif_pada' => null])->save();
        $this->log($definition, $approver, 'tolak_aktif', 'api', $reason);
        $this->audit->record('tolak', 'wfe', 'wf_definisi', $definition->id, reason: $reason, meta: ['tindakan' => 'aktif_definisi']);

        return $definition;
    }

    /** @return array<string, mixed> the definition as the editors' JSON structure */
    public function export(Definisi $definition): array
    {
        $definition->loadMissing(['peringkat.pelulus', 'peringkat.eskalasi', 'peralihan.syarat', 'susulan']);

        return [
            'id' => $definition->id,
            'kod_aliran' => $definition->kod_aliran,
            'nama' => $definition->nama,
            'keterangan' => $definition->keterangan,
            'modul' => $definition->modul,
            'jenis_transaksi' => $definition->jenis_transaksi,
            'templat' => (bool) $definition->templat,
            'no_versi' => $definition->no_versi,
            'status' => $definition->status,
            'kaedah_cipta' => $definition->kaedah_cipta,
            'peringkat' => $definition->peringkat->map(fn (Peringkat $s) => [
                'kod' => $s->kod,
                'nama' => $s->nama_peringkat,
                'mod_kelulusan' => $s->mod_kelulusan,
                'kuorum' => $s->kuorum,
                'sla_nilai' => $s->sla_nilai,
                'sla_unit' => $s->sla_unit,
                'perlu_mfa' => $s->perlu_mfa,
                'perlu_justifikasi' => $s->perlu_justifikasi,
                'pelulus' => $s->pelulus->map(fn (Pelulus $p) => ['jenis' => $p->jenis, 'nilai' => $p->nilai, 'label' => $p->label])->all(),
                'eskalasi' => $s->eskalasi->map(fn (Eskalasi $e) => [
                    'aras' => $e->aras, 'tempoh_nilai' => $e->tempoh_nilai, 'tempoh_unit' => $e->tempoh_unit,
                    'jenis_penerima' => $e->jenis_penerima, 'nilai_penerima' => $e->nilai_penerima,
                ])->all(),
            ])->all(),
            'peralihan' => $definition->peralihan->map(fn (Peralihan $t) => [
                'dari' => $t->dari, 'ke' => $t->ke, 'keutamaan' => $t->keutamaan, 'logik' => $t->logik, 'label' => $t->label,
                'syarat' => $t->syarat->map(fn ($c) => ['medan' => $c->medan, 'operator' => $c->operator, 'nilai' => $c->nilai])->all(),
            ])->all(),
            'susulan' => $definition->susulan->map(fn (Susulan $f) => [
                'bila' => $f->bila, 'tajuk' => $f->tajuk, 'jenis_penerima' => $f->jenis_penerima,
                'nilai_penerima' => $f->nilai_penerima, 'sla_nilai' => $f->sla_nilai, 'sla_unit' => $f->sla_unit,
            ])->all(),
            'susun_atur' => $definition->susun_atur,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    public function log(Definisi $definition, ?User $user, string $action, string $method, ?string $note = null, ?array $details = null): void
    {
        DefinisiLog::create([
            'id_definisi' => $definition->id,
            'id_pengguna' => $user?->id,
            'tindakan' => $action,
            'kaedah' => in_array($method, self::KAEDAH, true) ? $method : 'api',
            'arahan' => $note,
            'butiran' => $details,
        ]);
    }

    private function duplicate(Definisi $source, User $user, string $flowCode, string $name, int $version, bool $template): Definisi
    {
        $structure = $this->export($source);
        $copy = Definisi::create([
            'kod_aliran' => $flowCode,
            'nama' => $name,
            'keterangan' => $source->keterangan,
            'modul' => $source->modul,
            'jenis_transaksi' => $source->jenis_transaksi,
            'templat' => $template,
            'no_versi' => $version,
            'status' => Definisi::DRAF,
            'id_pencipta' => $user->id,
            'kaedah_cipta' => 'manual',
            'id_asal' => $source->id,
            'susun_atur' => $source->susun_atur,
        ]);
        $this->writeChildren($copy, $structure);

        return $copy;
    }

    /** @param array<string, mixed> $structure */
    private function writeChildren(Definisi $definition, array $structure): void
    {
        foreach (array_values($structure['peringkat']) as $i => $s) {
            $stage = $definition->peringkat()->create([
                'kod' => $s['kod'],
                'no_urutan' => $i + 1,
                'nama_peringkat' => $s['nama'],
                'mod_kelulusan' => $s['mod_kelulusan'],
                'kuorum' => $s['kuorum'],
                'sla_nilai' => $s['sla_nilai'],
                'sla_unit' => $s['sla_unit'] ?? 'hari_bekerja',
                'perlu_mfa' => (bool) $s['perlu_mfa'],
                'perlu_justifikasi' => (bool) $s['perlu_justifikasi'],
            ]);
            foreach ($s['pelulus'] as $p) {
                $stage->pelulus()->create(['jenis' => $p['jenis'], 'nilai' => $p['nilai'] ?? null, 'label' => $p['label'] ?? null]);
            }
            foreach ($s['eskalasi'] ?? [] as $e) {
                $stage->eskalasi()->create($e);
            }
        }
        foreach ($structure['peralihan'] as $t) {
            $edge = $definition->peralihan()->create(Arr::except($t, ['syarat']));
            foreach ($t['syarat'] as $c) {
                $edge->syarat()->create($c);
            }
        }
        foreach ($structure['susulan'] as $f) {
            $definition->susulan()->create($f);
        }
    }
}
