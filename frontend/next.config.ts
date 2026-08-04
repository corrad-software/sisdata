import path from 'node:path';
import type { NextConfig } from 'next';

const backend = process.env.INTERNAL_API_URL ?? 'http://127.0.0.1:8000';

const nextConfig: NextConfig = {
    turbopack: {
        root: path.join(__dirname),
    },
    async rewrites() {
        return [
            { source: '/api/:path*', destination: `${backend}/api/:path*` },
            { source: '/sanctum/:path*', destination: `${backend}/sanctum/:path*` },
        ];
    },
};

export default nextConfig;
