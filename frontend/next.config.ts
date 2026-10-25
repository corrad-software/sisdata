import path from 'node:path';
import type { NextConfig } from 'next';

const backend = process.env.INTERNAL_API_URL ?? 'http://127.0.0.1:8000';

const nextConfig: NextConfig = {
    turbopack: {
        root: path.join(__dirname),
    },
    // SRS-SEC-05: baseline browser hardening for every page (the API sets the same on its responses).
    async headers() {
        return [
            {
                source: '/((?!api/|sanctum/|auth/).*)',
                headers: [
                    { key: 'X-Content-Type-Options', value: 'nosniff' },
                    { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
                    { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
                    { key: 'Permissions-Policy', value: 'camera=(self), geolocation=(self), microphone=()' },
                ],
            },
        ];
    },
    async rewrites() {
        return [
            { source: '/api/:path*', destination: `${backend}/api/:path*` },
            { source: '/sanctum/:path*', destination: `${backend}/sanctum/:path*` },
            // SRS-RBC-01: SSO MAIS OIDC redirect/callback, handled by Laravel
            // but kept on this origin so the session cookie lands on :3000.
            { source: '/auth/:path*', destination: `${backend}/auth/:path*` },
        ];
    },
};

export default nextConfig;
