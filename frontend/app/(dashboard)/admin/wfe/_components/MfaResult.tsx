'use client';

import { useEffect } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { MFA_RESULT } from '@/lib/wfe';

/** Shows the outcome of the SSO MAIS step-up (?mfa=…) once, then cleans the URL. */
export function MfaResult() {
    const params = useSearchParams();
    const router = useRouter();
    const pathname = usePathname();
    const queryClient = useQueryClient();
    const result = params.get('mfa');

    useEffect(() => {
        if (!result) return;
        const info = MFA_RESULT[result];
        if (info) {
            if (info.type === 'success') toast.success(info.text);
            else toast.error(info.text);
        }
        queryClient.invalidateQueries({ queryKey: ['wfe', 'mfa'] });
        router.replace(pathname);
    }, [result, router, pathname, queryClient]);

    return null;
}
