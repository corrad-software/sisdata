'use client';

import { useCallback, useMemo, useState } from 'react';
import {
    Background,
    Controls,
    MarkerType,
    MiniMap,
    ReactFlow,
    applyNodeChanges,
    type Connection,
    type Edge,
    type Node,
    type NodeChange,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { Button } from '@/components/ui/Button';
import { emptyStage, type WfeReference, type WfeStage, type WfeStructure, type WfeTransition } from '@/lib/wfe';
import { ConditionRows } from './FormEditor';

const input = 'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 text-body-sm';

type Selection = { kind: 'node'; kod: string } | { kind: 'edge'; index: number } | null;

function defaultPosition(kod: string, stages: WfeStage[]): { x: number; y: number } {
    if (kod === 'mula') return { x: 0, y: 120 };
    if (kod === 'tamat') return { x: 240 * (stages.length + 1), y: 120 };
    const i = stages.findIndex((s) => s.kod === kod);
    return { x: 240 * (i + 1), y: 100 };
}

function conditionText(t: WfeTransition, operators: Record<string, string>): string {
    if (t.label) return t.label;
    if (t.syarat.length === 0) return '';
    return t.syarat
        .map((c) => `${c.medan} ${operators[c.operator] ?? c.operator} ${Array.isArray(c.nilai) ? c.nilai.join('/') : (c.nilai ?? '')}`.trim())
        .join(t.logik === 'mana_mana' ? ' ATAU ' : ' DAN ');
}

/**
 * SRS-WFE-09: React Flow editor over the SAME definition structure as the
 * form editor — drag stages, connect them (transitions), edit stage and
 * condition properties; positions are kept in susun_atur.
 */
export function FlowEditor({ value, onChange, reference, readOnly }: { value: WfeStructure; onChange: (s: WfeStructure) => void; reference?: WfeReference; readOnly: boolean }) {
    const [selection, setSelection] = useState<Selection>(null);
    const operators = useMemo(() => reference?.operator ?? {}, [reference]);
    const layout = useMemo(() => value.susun_atur?.nodes ?? {}, [value.susun_atur]);

    const built = useMemo((): Node[] => {
        const saved = value.susun_atur?.nodes ?? {};
        const pos = (kod: string) => saved[kod] ?? defaultPosition(kod, value.peringkat);
        const base = 'rounded-md border text-body-xs';
        return [
            { id: 'mula', type: 'input', position: pos('mula'), data: { label: 'Mula' }, deletable: false, className: `${base} !bg-bg-success-50 !border-otl-success-200` },
            ...value.peringkat.map((s) => ({
                id: s.kod,
                position: pos(s.kod),
                data: {
                    label: (
                        <div className="text-left space-y-0.5">
                            <p className="font-semibold">{s.nama}</p>
                            <p className="text-txt-black-500">
                                {s.pelulus.length} pelulus · {s.mod_kelulusan === 'semua' ? 'semua' : s.mod_kelulusan === 'kuorum' ? `kuorum ${s.kuorum ?? 1}` : 'mana-mana'}
                            </p>
                            <p className="text-txt-black-500">SLA {s.sla_nilai ?? '-'} {s.sla_unit === 'jam' ? 'jam' : 'hari'}{s.perlu_mfa ? ' · MFA' : ''}</p>
                        </div>
                    ),
                },
                className: `${base} !w-48 ${s.perlu_mfa ? '!border-otl-warning-200 !bg-bg-warning-50' : ''}`,
            })),
            { id: 'tamat', type: 'output', position: pos('tamat'), data: { label: 'Tamat (lulus)' }, deletable: false, className: `${base} !bg-bg-primary-50` },
        ];
    }, [value.peringkat, value.susun_atur]);

    // Local node state keeps dragging smooth; resync (during render) when the definition changes.
    const [nodes, setNodes] = useState<Node[]>(built);
    const [builtFrom, setBuiltFrom] = useState(built);
    if (built !== builtFrom) {
        setBuiltFrom(built);
        setNodes(built);
    }

    const edges: Edge[] = value.peralihan.map((t, i) => ({
        id: `e${i}`,
        source: t.dari,
        target: t.ke,
        label: conditionText(t, operators) || (value.peralihan.filter((x) => x.dari === t.dari).length > 1 ? 'lalai' : undefined),
        markerEnd: { type: MarkerType.ArrowClosed },
        animated: t.syarat.length > 0,
        selected: selection?.kind === 'edge' && selection.index === i,
    }));

    const onNodesChange = useCallback(
        (changes: NodeChange[]) => {
            setNodes((ns) => applyNodeChanges(changes, ns));
            if (readOnly) return;
            const removed = changes.filter((c) => c.type === 'remove').map((c) => c.id).filter((id) => id !== 'mula' && id !== 'tamat');
            if (removed.length && value.peringkat.length - removed.length >= 1) {
                onChange({
                    ...value,
                    peringkat: value.peringkat.filter((s) => !removed.includes(s.kod)),
                    peralihan: value.peralihan.filter((t) => !removed.includes(t.dari) && !removed.includes(t.ke)),
                });
                setSelection(null);
            }
        },
        [onChange, readOnly, value],
    );

    const onNodeDragStop = useCallback(
        (_: unknown, node: Node) => {
            if (readOnly) return;
            onChange({ ...value, susun_atur: { nodes: { ...layout, [node.id]: { x: Math.round(node.position.x), y: Math.round(node.position.y) } } } });
        },
        [layout, onChange, readOnly, value],
    );

    const onConnect = useCallback(
        (c: Connection) => {
            if (readOnly || !c.source || !c.target || c.source === c.target || c.source === 'tamat' || c.target === 'mula') return;
            const siblings = value.peralihan.filter((t) => t.dari === c.source);
            const next: WfeTransition = { dari: c.source, ke: c.target, keutamaan: siblings.length, logik: 'semua', label: null, syarat: [] };
            onChange({ ...value, peralihan: [...value.peralihan, next] });
            setSelection({ kind: 'edge', index: value.peralihan.length });
        },
        [onChange, readOnly, value],
    );

    const onEdgesDelete = useCallback(
        (deleted: Edge[]) => {
            if (readOnly) return;
            const ids = new Set(deleted.map((e) => Number(e.id.slice(1))));
            onChange({ ...value, peralihan: value.peralihan.filter((_, i) => !ids.has(i)) });
            setSelection(null);
        },
        [onChange, readOnly, value],
    );

    function addStage() {
        let n = value.peringkat.length + 1;
        while (value.peringkat.some((s) => s.kod === `peringkat_${n}`)) n++;
        const stage = emptyStage(n);
        onChange({
            ...value,
            peringkat: [...value.peringkat, stage],
            susun_atur: { nodes: { ...layout, [stage.kod]: { x: 240 * n, y: 260 } } },
        });
        setSelection({ kind: 'node', kod: stage.kod });
    }

    const stageIndex = selection?.kind === 'node' ? value.peringkat.findIndex((s) => s.kod === selection.kod) : -1;
    const stage = stageIndex >= 0 ? value.peringkat[stageIndex] : null;
    const edge = selection?.kind === 'edge' ? value.peralihan[selection.index] : null;
    const setStage = (patch: Partial<WfeStage>) => onChange({ ...value, peringkat: value.peringkat.map((s, i) => (i === stageIndex ? { ...s, ...patch } : s)) });
    const setEdge = (patch: Partial<WfeTransition>) =>
        selection?.kind === 'edge' && onChange({ ...value, peralihan: value.peralihan.map((t, i) => (i === selection.index ? { ...t, ...patch } : t)) });

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div className="h-[28rem] sm:h-[34rem] rounded-lg border border-otl-gray-200 bg-bg-washed" data-testid="editor-visual">
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    onNodesChange={onNodesChange}
                    onNodeDragStop={onNodeDragStop}
                    onConnect={onConnect}
                    onEdgesDelete={onEdgesDelete}
                    onNodeClick={(_, n) => setSelection(n.id === 'mula' || n.id === 'tamat' ? null : { kind: 'node', kod: n.id })}
                    onEdgeClick={(_, e) => setSelection({ kind: 'edge', index: Number(e.id.slice(1)) })}
                    onPaneClick={() => setSelection(null)}
                    nodesDraggable={!readOnly}
                    nodesConnectable={!readOnly}
                    elementsSelectable
                    deleteKeyCode={readOnly ? null : ['Backspace', 'Delete']}
                    fitView
                    proOptions={{ hideAttribution: true }}
                >
                    <Background />
                    <Controls showInteractive={false} />
                    <MiniMap pannable zoomable className="hidden sm:block" />
                </ReactFlow>
            </div>

            <aside className="rounded-lg border border-otl-gray-200 p-4 space-y-3 bg-bg-white">
                {!readOnly && (
                    <Button size="sm" variant="outline" onClick={addStage} className="w-full">
                        Tambah peringkat
                    </Button>
                )}
                <p className="text-body-xs text-txt-black-500">
                    Seret peringkat untuk menyusun; tarik dari titik bawah ke titik atas peringkat lain untuk menyambung. Klik peringkat atau anak panah untuk mengubah
                    sifatnya. Tekan Delete untuk membuang yang dipilih.
                </p>

                {stage && (
                    <fieldset disabled={readOnly} className="space-y-3">
                        <h4 className="font-semibold text-body-sm">Peringkat: {stage.kod}</h4>
                        <label className="block text-body-xs space-y-1">
                            <span>Nama</span>
                            <input className={input} value={stage.nama} onChange={(e) => setStage({ nama: e.target.value })} />
                        </label>
                        <label className="block text-body-xs space-y-1">
                            <span>Mod kelulusan</span>
                            <select className={input} value={stage.mod_kelulusan} onChange={(e) => setStage({ mod_kelulusan: e.target.value as WfeStage['mod_kelulusan'] })}>
                                {Object.entries(reference?.mod_kelulusan ?? {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </label>
                        <div className="flex gap-2">
                            <label className="block text-body-xs space-y-1 flex-1">
                                <span>SLA</span>
                                <input type="number" min={1} className={input} value={stage.sla_nilai ?? ''} onChange={(e) => setStage({ sla_nilai: e.target.value ? Number(e.target.value) : null })} />
                            </label>
                            <label className="block text-body-xs space-y-1 flex-1">
                                <span>Unit</span>
                                <select className={input} value={stage.sla_unit} onChange={(e) => setStage({ sla_unit: e.target.value as WfeStage['sla_unit'] })}>
                                    <option value="hari_bekerja">hari bekerja</option>
                                    <option value="jam">jam</option>
                                </select>
                            </label>
                        </div>
                        <label className="flex items-center gap-2 text-body-sm">
                            <input type="checkbox" checked={stage.perlu_mfa} onChange={(e) => setStage({ perlu_mfa: e.target.checked })} className="rounded border-otl-gray-300 text-primary-600" />
                            Kritikal (wajib MFA)
                        </label>
                        <div className="text-body-xs space-y-1">
                            <p className="font-medium">Pelulus</p>
                            {stage.pelulus.map((p, i) => (
                                <div key={i} className="flex gap-1">
                                    <select className={input} aria-label="Jenis pelulus" value={p.jenis} onChange={(e) => setStage({ pelulus: stage.pelulus.map((x, j) => (j === i ? { ...x, jenis: e.target.value } : x)) })}>
                                        {Object.entries(reference?.jenis_pelulus ?? {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                    </select>
                                    <input className={input} aria-label="Nilai pelulus" value={p.nilai ?? ''} disabled={p.jenis === 'penyelia_pemohon'} onChange={(e) => setStage({ pelulus: stage.pelulus.map((x, j) => (j === i ? { ...x, nilai: e.target.value } : x)) })} />
                                </div>
                            ))}
                            <p className="text-txt-black-500">Eskalasi dan butiran lain: tab Borang.</p>
                        </div>
                    </fieldset>
                )}

                {edge && (
                    <fieldset disabled={readOnly} className="space-y-3">
                        <h4 className="font-semibold text-body-sm">
                            Peralihan: {edge.dari} → {edge.ke}
                        </h4>
                        <label className="block text-body-xs space-y-1">
                            <span>Label</span>
                            <input className={input} value={edge.label ?? ''} onChange={(e) => setEdge({ label: e.target.value || null })} />
                        </label>
                        <div className="flex gap-2">
                            <label className="block text-body-xs space-y-1 flex-1">
                                <span>Keutamaan</span>
                                <input type="number" min={0} className={input} value={edge.keutamaan} onChange={(e) => setEdge({ keutamaan: Number(e.target.value) })} />
                            </label>
                            <label className="block text-body-xs space-y-1 flex-1">
                                <span>Gabungan</span>
                                <select className={input} value={edge.logik} onChange={(e) => setEdge({ logik: e.target.value as 'semua' | 'mana_mana' })}>
                                    <option value="semua">DAN</option>
                                    <option value="mana_mana">ATAU</option>
                                </select>
                            </label>
                        </div>
                        <ConditionRows transition={edge} reference={reference} readOnly={readOnly} onChange={(syarat) => setEdge({ syarat })} />
                    </fieldset>
                )}

                {!stage && !edge && <p className="text-body-sm text-txt-black-500">Tiada pilihan.</p>}
            </aside>
        </div>
    );
}
