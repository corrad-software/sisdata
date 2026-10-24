'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { ApiError } from '@/lib/api';
import { logout } from '@/lib/auth';
import { rbc } from '@/lib/rbc';
import type { User } from '@/lib/types';
import { ChangePasswordModal } from './ChangePasswordModal';

const POLL_MS = 30_000;

function format(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

/**
 * SRS-RBC-18: warns before the server-side idle timeout ends the session and
 * lets the user continue; SRS-RBC-12: forces a password change when required.
 * The server is authoritative — this only polls GET /rbc/sesi/status (which
 * does not count as activity) and counts down locally between polls.
 */
export function SessionGuard({ user }: { user: User }) {
    const router = useRouter();
    const [remaining, setRemaining] = useState<number | null>(null);
    const [warnAt, setWarnAt] = useState(120);
    const [mustChange, setMustChange] = useState(user.perlu_tukar_kata_laluan);
    const deadline = useRef<number | null>(null);
    const ended = useRef(false);

    const endSession = useCallback(() => {
        if (ended.current) return;
        ended.current = true;
        router.push('/login?sesi=tamat');
    }, [router]);

    const sync = useCallback(async () => {
        try {
            const { data } = await rbc.sessionStatus();
            // Never warn for more than half the timeout, or a short timeout would warn permanently.
            setWarnAt(Math.min(data.amaran_saat, Math.floor((data.tamat_minit * 60) / 2)));
            if (data.baki_saat !== null) {
                deadline.current = Date.now() + data.baki_saat * 1000;
                setRemaining(data.baki_saat);
            }
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) endSession();
        }
    }, [endSession]);

    useEffect(() => {
        const first = setTimeout(sync, 0);
        const poll = setInterval(sync, POLL_MS);
        const tick = setInterval(() => {
            if (deadline.current === null) return;
            const left = Math.max(0, Math.round((deadline.current - Date.now()) / 1000));
            setRemaining(left);
            if (left === 0) void sync();
        }, 1000);
        return () => {
            clearTimeout(first);
            clearInterval(poll);
            clearInterval(tick);
        };
    }, [sync]);

    async function extend() {
        try {
            const { data } = await rbc.extendSession();
            if (data.baki_saat !== null) {
                deadline.current = Date.now() + data.baki_saat * 1000;
                setRemaining(data.baki_saat);
            }
        } catch {
            endSession();
        }
    }

    async function signOut() {
        const result = await logout().catch(() => undefined);
        if (result?.logout_url) {
            window.location.assign(result.logout_url);
            return;
        }
        router.push('/login');
    }

    const warning = remaining !== null && remaining <= warnAt && !mustChange;

    return (
        <>
            <Modal open={warning} onClose={extend}>
                <div className="p-6 space-y-4" role="alertdialog" aria-labelledby="sesi-amaran" aria-describedby="sesi-amaran-teks">
                    <h2 id="sesi-amaran" className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        Sesi anda akan tamat
                    </h2>
                    <p id="sesi-amaran-teks" className="text-body-sm text-txt-black-700">
                        Anda tidak aktif untuk satu tempoh. Sesi akan ditamatkan dalam{' '}
                        <strong className="tabular-nums" aria-live="polite">
                            {format(remaining ?? 0)}
                        </strong>{' '}
                        minit untuk keselamatan.
                    </p>
                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button variant="secondary" onClick={signOut}>
                            Log Keluar
                        </Button>
                        <Button onClick={extend}>Teruskan Sesi</Button>
                    </div>
                </div>
            </Modal>

            <ChangePasswordModal
                open={mustChange}
                forced
                onClose={() => undefined}
                onChanged={() => {
                    setMustChange(false);
                    router.refresh();
                }}
            />
        </>
    );
}
