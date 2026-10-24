<?php

namespace App\Modules\Wfe\Services;

use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;

/**
 * In-memory catalogue of flow codes declared by modules (WorkflowFlows) and
 * of drill-down link patterns (SubjectLinks). Both are filled from service
 * providers' boot() on every request, so nothing is persisted.
 */
class FlowRegistry implements SubjectLinks, WorkflowFlows
{
    /** @var array<string, array{kod_aliran: string, nama: string, modul: string, jenis_subjek: string, medan_konteks: list<string>}> */
    private array $flows = [];

    /** @var array<string, string> */
    private array $links = [];

    public function declare(string $flowCode, string $name, string $module, string $subjectType, array $contextFields = []): void
    {
        $this->flows[$flowCode] = [
            'kod_aliran' => $flowCode,
            'nama' => $name,
            'modul' => $module,
            'jenis_subjek' => $subjectType,
            'medan_konteks' => array_values($contextFields),
        ];
    }

    /** @return array{kod_aliran: string, nama: string, modul: string, jenis_subjek: string, medan_konteks: list<string>}|null */
    public function get(string $flowCode): ?array
    {
        return $this->flows[$flowCode] ?? null;
    }

    /** @return list<array{kod_aliran: string, nama: string, modul: string, jenis_subjek: string, medan_konteks: list<string>}> */
    public function all(): array
    {
        $flows = $this->flows;
        ksort($flows);

        return array_values($flows);
    }

    public function register(string $subjectType, string $pathPattern): void
    {
        $this->links[$subjectType] = $pathPattern;
    }

    public function linkFor(string $subjectType, string|int $subjectId): ?string
    {
        $pattern = $this->links[$subjectType] ?? null;

        return $pattern === null ? null : str_replace('{id}', rawurlencode((string) $subjectId), $pattern);
    }
}
