import { cache } from 'react';
import { cookies, headers } from 'next/headers';
import type { User } from './types';

const backend = process.env.INTERNAL_API_URL ?? 'http://127.0.0.1:8000';
const frontendUrl = process.env.FRONTEND_URL ?? 'http://localhost:3000';

// Wrapped in React's cache() so multiple call sites within the same request
// (e.g. a layout and the page it wraps) share one backend call.
export const getServerUser = cache(async (): Promise<User | null> => {
    const cookieStore = await cookies();
    const cookieHeader = cookieStore.toString();

    if (!cookieHeader) return null;

    // MOD-RBC binds each session to the browser fingerprint and IP range
    // (SRS-RBC-20): forward the visitor's User-Agent and address, otherwise
    // this server-side call looks like a different device and ends the session.
    const incoming = await headers();
    const forwarded: Record<string, string> = {};
    const userAgent = incoming.get('user-agent');
    const forwardedFor = incoming.get('x-forwarded-for');
    if (userAgent) forwarded['User-Agent'] = userAgent;
    if (forwardedFor) forwarded['X-Forwarded-For'] = forwardedFor;

    const response = await fetch(`${backend}/api/v1/user`, {
        // Sanctum only treats a request as coming from the SPA frontend (and
        // thus checks session auth) when Referer/Origin matches a configured
        // stateful domain — this direct server-to-server call needs it too.
        headers: { Accept: 'application/json', Cookie: cookieHeader, Referer: frontendUrl, ...forwarded },
        cache: 'no-store',
    });

    if (!response.ok) return null;

    const json = await response.json();
    return json.data as User;
});
