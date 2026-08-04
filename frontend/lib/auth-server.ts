import { cookies } from 'next/headers';
import type { User } from './types';

const backend = process.env.INTERNAL_API_URL ?? 'http://127.0.0.1:8000';
const frontendUrl = process.env.FRONTEND_URL ?? 'http://localhost:3000';

export async function getServerUser(): Promise<User | null> {
    const cookieStore = await cookies();
    const cookieHeader = cookieStore.toString();

    if (!cookieHeader) return null;

    const response = await fetch(`${backend}/api/user`, {
        // Sanctum only treats a request as coming from the SPA frontend (and
        // thus checks session auth) when Referer/Origin matches a configured
        // stateful domain — this direct server-to-server call needs it too.
        headers: { Accept: 'application/json', Cookie: cookieHeader, Referer: frontendUrl },
        cache: 'no-store',
    });

    if (!response.ok) return null;

    const json = await response.json();
    return json.data as User;
}
