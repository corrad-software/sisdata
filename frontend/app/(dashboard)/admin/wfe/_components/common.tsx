'use client';

import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { Badge } from '@/components/ui/Badge';
import { AnchorButton, Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Textarea } from '@/components/ui/Textarea';
import { FieldInput } from '@/components/ui/FieldInput';
import { Alert } from '@/components/ui/Alert';
import {
    ACTION_LABEL,
    EVENT_LABEL,
    STATUS_LABEL,
    STATUS_VARIANT,
    errorMessage,
    fmtDateTime,
    slaText,
    stepUpUrl,
    useMfa,
    type WfeHistory,
    type WfeTask,
} from '@/lib/wfe';

export function StatusBadge({ status }: { status: string }) {
    return <Badge variant={STATUS_VARIANT[status] ?? 'neutral'}>{STATUS_LABEL[status] ?? status}</Badge>;
}

export function SlaBadge({ task }: { task: Pick<WfeTask, 'baki_sla_minit' | 'minit_sehari'> }) {
    const { text, tone } = slaText(task.baki_sla_minit, task.minit_sehari);
    return <Badge variant={tone === 'neutral' ? 'neutral' : tone}>{text}</Badge>;
}

export function Card({ title, actions, children }: { title?: string; actions?: ReactNode; children: ReactNode }) {
    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
            {(title || actions) && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {title && <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{title}</h3>}
                    {actions}
                </div>
            )}
            {children}
        </section>
    );
}

/** Label/value list (SummaryList look) that accepts rich values. */
export function InfoList({ items, stacked = false }: { items: Array<{ label: string; value: ReactNode }>; stacked?: boolean }) {
    return (
        <dl className="divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white">
            {items.map((item) => (
                <div key={item.label} className={`flex flex-col gap-1 px-4 py-3 ${stacked ? '' : 'sm:flex-row sm:items-center sm:gap-4'}`}>
                    <dt className={`text-body-sm font-medium text-txt-black-500 ${stacked ? '' : 'sm:w-40 sm:flex-shrink-0'}`}>{item.label}</dt>
                    <dd className="flex-1 text-body-sm text-txt-black-900 break-words min-w-0">{item.value}</dd>
                </div>
            ))}
        </dl>
    );
}

export function HistoryList({ items }: { items: WfeHistory[] }) {
    const shown = items.filter((h) => !['tugasan', 'masuk_peringkat'].includes(h.peristiwa) || h.justifikasi);

    return (
        <ol className="relative border-s border-otl-gray-200 ms-3">
            {shown.map((h) => {
                const color =
                    h.peristiwa === 'lulus' || h.peristiwa === 'selesai'
                        ? 'bg-bg-success-50 text-txt-success'
                        : h.peristiwa === 'tolak' || h.peristiwa === 'eskalasi'
                          ? 'bg-bg-danger-50 text-txt-danger'
                          : h.peristiwa === 'kembali' || h.peristiwa === 'tangguh'
                            ? 'bg-bg-warning-50 text-txt-warning'
                            : 'bg-bg-primary-50 text-txt-primary';
                const outcome = typeof h.butiran?.keputusan === 'string' ? ` — ${STATUS_LABEL[h.butiran.keputusan] ?? h.butiran.keputusan}` : '';
                const level = h.peristiwa === 'eskalasi' && h.butiran?.aras ? ` aras ${String(h.butiran.aras)}` : '';

                return (
                    <li key={h.id} className="mb-6 ms-6 last:mb-0">
                        <span className={`absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-bg-white ${color}`}>
                            <svg className="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M10 2a8 8 0 100 16 8 8 0 000-16z" />
                            </svg>
                        </span>
                        <p className="text-body-sm font-medium text-txt-black-900">
                            {EVENT_LABEL[h.peristiwa] ?? h.peristiwa}
                            {level}
                            {outcome}
                            {h.peringkat ? <span className="font-normal text-txt-black-500"> · {h.peringkat}</span> : null}
                        </p>
                        <p className="text-body-xs text-txt-black-500">
                            {fmtDateTime(h.tarikh_masa)}
                            {h.pengguna ? ` · ${h.pengguna}` : ''}
                            {h.bagi_pihak ? ` (bagi pihak ${h.bagi_pihak})` : ''}
                            {h.peranan_semasa?.length ? ` · peranan: ${h.peranan_semasa.join(', ')}` : ''}
                            {h.mfa_pada ? ' · MFA disahkan' : ''}
                        </p>
                        {h.justifikasi && <p className="mt-1 text-body-sm text-txt-black-700 whitespace-pre-line">“{h.justifikasi}”</p>}
                    </li>
                );
            })}
        </ol>
    );
}

/** Banner + button to re-authenticate at SSO MAIS for critical stages (SRS-WFE-19). */
export function MfaNotice({ returnPath }: { returnPath: string }) {
    const { data: mfa } = useMfa();
    if (!mfa) return null;
    if (mfa.sah) {
        return <Alert type="success">MFA disahkan sehingga {fmtDateTime(mfa.tamat_pada ?? null)}.</Alert>;
    }
    if (!mfa.sso) {
        return (
            <Alert type="warning">
                Peringkat ini kritikal dan memerlukan MFA melalui SSO MAIS. Akaun anda log masuk dengan kata laluan sahaja — sila log masuk
                menggunakan SSO MAIS untuk mengambil tindakan.
            </Alert>
        );
    }
    return (
        <Alert type="warning">
            <div className="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                <span>Peringkat ini kritikal. Sahkan identiti anda melalui MFA (SSO MAIS) sebelum membuat keputusan.</span>
                <AnchorButton href={stepUpUrl(returnPath)} size="sm">
                    Sahkan dengan MFA
                </AnchorButton>
            </div>
        </Alert>
    );
}

type Action = 'lulus' | 'tolak' | 'kembali' | 'tangguh';

/** Decision dialog: justification is mandatory for tolak/kembali/tangguh (SRS-WFE-17). */
export function DecisionDialog({
    task,
    action,
    onClose,
    returnPath,
}: {
    task: WfeTask | null;
    action: Action | null;
    onClose: () => void;
    returnPath: string;
}) {
    const queryClient = useQueryClient();
    const [justification, setJustification] = useState('');
    const [until, setUntil] = useState('');
    const [error, setError] = useState<string | null>(null);
    const { data: mfa } = useMfa();

    const needsJustification = action !== 'lulus' || !!task?.peringkat?.perlu_justifikasi;
    const needsMfa = !!task?.peringkat?.perlu_mfa && !mfa?.sah;

    const mutation = useMutation({
        mutationFn: () =>
            api.post<{ message: string }>(`/wfe/tugasan/${task?.id}/tindakan`, {
                tindakan: action,
                justifikasi: justification || undefined,
                tangguh_hingga: action === 'tangguh' && until ? `${until}T17:00:00+08:00` : undefined,
            }),
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['wfe'] });
            close();
        },
        onError: (e) => setError(errorMessage(e)),
    });

    function close() {
        setJustification('');
        setUntil('');
        setError(null);
        onClose();
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (needsJustification && !justification.trim()) {
            setError('Justifikasi bertulis wajib diisi bagi tindakan ini.');
            return;
        }
        mutation.mutate();
    }

    return (
        <Modal open={!!task && !!action} onClose={close}>
            {task && action && (
                <form onSubmit={submit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        {ACTION_LABEL[action]}: {task.tajuk}
                    </h2>
                    <p className="text-body-sm text-txt-black-500">
                        Peringkat: {task.peringkat?.nama ?? '-'}
                        {task.bagi_pihak ? ` · bertindak bagi pihak ${task.bagi_pihak}` : ''}
                    </p>

                    {needsMfa && <MfaNotice returnPath={returnPath} />}

                    <Textarea
                        label="Justifikasi"
                        name="justifikasi"
                        value={justification}
                        onChange={setJustification}
                        required={needsJustification}
                        maxLength={2000}
                        rows={4}
                        help={needsJustification ? 'Wajib bagi tindakan ini.' : 'Pilihan.'}
                    />

                    {action === 'tangguh' && (
                        <FieldInput
                            label="Tangguh hingga"
                            name="tangguh_hingga"
                            type="date"
                            value={until}
                            onChange={setUntil}
                            help="Kosongkan untuk tempoh lalai. Masa SLA dihentikan sepanjang tangguhan."
                        />
                    )}

                    {error && <Alert type="error">{error}</Alert>}

                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="secondary" onClick={close}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant={action === 'tolak' ? 'danger' : 'primary'}
                            disabled={mutation.isPending || (needsMfa && !!task.peringkat?.perlu_mfa)}
                        >
                            {ACTION_LABEL[action]}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}
