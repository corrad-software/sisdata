'use client';

import { useQuery } from '@tanstack/react-query';
import { fetchPenyelenggaraan } from '@/lib/adm';

/** SRS-ADM-29: notice shown to every signed-in user while maintenance mode is on. */
export function MaintenanceBanner() {
    const { data } = useQuery({ queryKey: ['adm', 'penyelenggaraan'], queryFn: fetchPenyelenggaraan, refetchInterval: 60000 });
    const state = data?.data;
    if (!state?.aktif) return null;

    return (
        <div role="status" className="border-b border-otl-warning-200 bg-bg-warning-50 px-4 sm:px-6 py-2 text-body-sm text-txt-warning">
            <strong>Mod penyelenggaraan:</strong> {state.notis}
            {state.tamat_dijangka && <> Dijangka tamat: {state.tamat_dijangka}.</>}
        </div>
    );
}
