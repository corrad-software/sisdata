<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dsh\Contracts\InsightGenerator;
use App\Modules\Dsh\Contracts\KpiDrillDown;
use App\Modules\Dsh\Models\LogPromptAi;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The dashboard's natural-language prompt (SRS-DSH-15/16/17). The
 * InsightGenerator only turns the question into a structured query over the
 * KPIs the user may see; MOD-DSH then runs that query AS the user, so the RBAC
 * scope always applies — a district officer asking about another district
 * gets an answer limited to their own scope, and the attempt is recorded
 * (dsh_log_prompt_ai + central audit trail).
 */
class AiPrompt
{
    public function __construct(
        private InsightGenerator $generator,
        private KpiService $kpis,
        private Skop $skop,
        private WidgetKatalog $widgets,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  the dashboard's current filters (normalised)
     * @return array<string, mixed>
     */
    public function ask(User $user, string $prompt, string $aras, array $filters = []): array
    {
        $catalogue = $this->kpis->catalogue($user);
        $districts = $this->districts();
        $status = 'dijawab';
        $nota = [];

        try {
            $query = $this->generator->interpret($user, $prompt, $catalogue, $districts);
            $engine = $this->generator->engine();
        } catch (Throwable $e) {
            report($e);
            $query = ['niat' => 'tidak_difahami', 'kpi' => [], 'penapis' => []];
            $engine = $this->generator->engine();
        }

        $penapis = Penapis::from(array_merge($filters, $query['penapis']));
        $diminta = $penapis['kod_daerah'] ?? null;
        if ($this->skop->outsideDistrict($user, $diminta)) {
            // SRS-DSH-17: never widen the scope — answer within the user's own districts.
            $status = 'terhad';
            unset($penapis['kod_daerah'], $penapis['kod_mukim']);
            $nota[] = __('dsh::dsh.ai.terhad', ['daerah' => $districts[$diminta] ?? $diminta]);
        }

        $allowed = array_column($catalogue, 'key');
        $keys = array_values(array_intersect($query['kpi'], $allowed));
        $jawapan = ['jenis' => 'teks', 'teks' => '', 'kpi' => [], 'visual' => $query['visual'] ?? 'stat', 'nota' => $nota];

        if ($query['niat'] === 'tidak_difahami' || $keys === []) {
            $status = $status === 'terhad' ? 'terhad' : 'tidak_difahami';
            $jawapan['teks'] = __('dsh::dsh.ai.tidak_difahami');
            $jawapan['cadangan'] = array_slice(array_column($catalogue, 'label'), 0, 6);
        } elseif ($query['niat'] === 'susun_atur') {
            $tindakan = ($query['susun_atur']['tindakan'] ?? 'tambah') === 'buang' ? 'buang' : 'tambah';
            $changed = $this->applyLayout($user, $aras, $tindakan, $keys);
            $jawapan['jenis'] = 'susun_atur';
            $jawapan['susun_atur'] = ['tindakan' => $tindakan, 'widget' => $changed];
            $jawapan['teks'] = $changed === [] ? __('dsh::dsh.ai.susun_atur_tiada') : __('dsh::dsh.ai.susun_atur_'.$tindakan, ['bil' => count($changed)]);
        } else {
            $results = $this->kpis->compute($user, $penapis, $keys);
            $jawapan['kpi'] = $results;
            $jawapan['jenis'] = match ($query['niat']) {
                'trend' => 'carta',
                'senarai' => 'jadual',
                default => ($jawapan['visual'] === 'stat' ? 'nilai' : 'carta'),
            };
            $jawapan['teks'] = implode(' ', array_map(fn ($r) => $r['label'].': '.$this->fmt($r['nilai'], $r['unit']).'.', $results));
            if ($query['niat'] === 'senarai' && isset($results[0])) {
                $provider = $this->kpis->find($user, $results[0]['key']);
                if ($provider instanceof KpiDrillDown) {
                    $jawapan['jadual'] = $provider->records($user, $penapis, null, 1, 10);
                }
            }
        }

        $scope = $this->skop->scopes($user);
        $log = LogPromptAi::query()->create([
            'id_pengguna' => $user->id,
            'teks_prompt' => mb_substr($prompt, 0, 2000),
            'pertanyaan_dijana' => ['niat' => $query['niat'], 'kpi' => $keys, 'penapis' => $penapis, 'daerah_diminta' => $diminta],
            'skop_rbac' => $scope,
            'jawapan' => ['jenis' => $jawapan['jenis'], 'teks' => mb_substr((string) $jawapan['teks'], 0, 2000)],
            'enjin' => $engine,
            'status' => $status,
            'cap_masa' => Carbon::now(),
        ]);
        $this->audit->record('prompt_ai', 'dsh', 'dsh_log_prompt_ai', $log->id,
            status: $status === 'terhad' ? 'ditolak' : 'berjaya',
            reason: $status === 'terhad' ? __('dsh::dsh.ai.audit_terhad') : null,
            meta: ['prompt' => mb_substr($prompt, 0, 500), 'kpi' => $keys, 'penapis' => $penapis, 'daerah_diminta' => $diminta, 'skop' => $scope, 'enjin' => $engine, 'status' => $status],
        );

        return ['id' => $log->id, 'status' => $status, 'enjin' => $engine, 'pertanyaan' => ['niat' => $query['niat'], 'kpi' => $keys, 'penapis' => $penapis], 'jawapan' => $jawapan];
    }

    /**
     * SRS-DSH-16: show / hide the KPI widgets in the user's personal layout.
     *
     * @param  list<string>  $keys
     * @return list<string> widget codes changed
     */
    private function applyLayout(User $user, string $aras, string $tindakan, array $keys): array
    {
        $layout = $this->widgets->layout($user, $aras);
        $targets = array_map(fn ($k) => 'kpi:'.$k, $keys);
        $changed = [];
        $items = [];
        $front = [];
        foreach ($layout as $w) {
            $item = ['kod' => $w['kod'], 'saiz' => $w['saiz'], 'tersembunyi' => $w['tersembunyi'], 'jenis_visual' => $w['jenis_visual'] === 'auto' ? null : $w['jenis_visual']];
            if (in_array($w['kod'], $targets, true)) {
                $item['tersembunyi'] = $tindakan === 'buang';
                $changed[] = $w['kod'];
                if ($tindakan === 'tambah') {
                    $front[] = $item;

                    continue;
                }
            }
            $items[] = $item;
        }
        if ($changed !== []) {
            $this->widgets->save($user, $aras, [...$front, ...$items]);
        }

        return $changed;
    }

    /** @return array<string, string> */
    public function districts(): array
    {
        $out = [];
        foreach (Baca::db()->select('SELECT kod, nama FROM ref_daerah WHERE aktif = true ORDER BY susunan, nama') as $r) {
            $out[(string) $r->kod] = (string) $r->nama;
        }

        return $out;
    }

    private function fmt(mixed $v, string $unit): string
    {
        if (! is_numeric($v)) {
            return __('dsh::dsh.tiada_data');
        }
        $n = number_format((float) $v, floor((float) $v) == (float) $v ? 0 : 1, '.', ',');

        return match ($unit) {
            'RM' => 'RM '.$n,
            '%' => $n.'%',
            default => trim($n.' '.$unit),
        };
    }
}
