import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

const SESSION_COOKIE = process.env.SESSION_COOKIE_NAME ?? 'laravel-session';
const GUEST_ONLY_PATHS = ['/login', '/register'];

// Cheap cookie-presence check only — an opaque Laravel session cookie can't be
// validated here without a network call. The authoritative check happens in
// app/(dashboard)/layout.tsx by forwarding the cookie to GET /api/user.
export function proxy(request: NextRequest) {
    const { pathname } = request.nextUrl;
    const hasSession = request.cookies.has(SESSION_COOKIE);

    if (!hasSession && pathname.startsWith('/admin')) {
        const url = new URL('/login', request.url);
        url.searchParams.set('redirect', pathname);
        return NextResponse.redirect(url);
    }

    if (hasSession && GUEST_ONLY_PATHS.includes(pathname)) {
        return NextResponse.redirect(new URL('/admin', request.url));
    }

    return NextResponse.next();
}

export const config = {
    matcher: ['/admin/:path*', '/login', '/register'],
};
