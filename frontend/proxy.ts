import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

const SESSION_COOKIE = process.env.SESSION_COOKIE_NAME ?? 'laravel-session';

// Cheap cookie-presence check only — an opaque Laravel session cookie can't be
// validated here without a network call. The authoritative "am I logged in"
// check happens server-side via getServerUser(): app/(dashboard)/layout.tsx
// for protecting /admin, and app/(auth)/layout.tsx for redirecting an
// already-authenticated visitor away from /login and /register.
//
// Deliberately NOT redirecting /login and /register away when a session
// cookie is merely present: logging out rotates the cookie to a fresh,
// unauthenticated session rather than clearing it, so "cookie present" isn't
// "authenticated" — treating it as such here bounced /login back to /admin
// (whose layout would then bounce right back to /login) in a redirect loop.
export function proxy(request: NextRequest) {
    const { pathname } = request.nextUrl;
    const hasSession = request.cookies.has(SESSION_COOKIE);

    if (!hasSession && pathname.startsWith('/admin')) {
        const url = new URL('/login', request.url);
        url.searchParams.set('redirect', pathname);
        return NextResponse.redirect(url);
    }

    return NextResponse.next();
}

export const config = {
    matcher: ['/admin/:path*'],
};
