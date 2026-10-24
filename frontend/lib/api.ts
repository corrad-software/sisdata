export class ApiError extends Error {
    status: number;
    errors?: Record<string, string[]>;

    constructor(status: number, body: { message?: string; errors?: Record<string, string[]> } | null) {
        super(body?.message ?? 'Request failed');
        this.status = status;
        this.errors = body?.errors;
    }
}

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase();
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');

    const isMutating = method !== 'GET' && method !== 'HEAD';

    if (isMutating) {
        if (!getCookie('XSRF-TOKEN')) {
            await ensureCsrfCookie();
        }
        const xsrf = getCookie('XSRF-TOKEN');
        if (xsrf) headers.set('X-XSRF-TOKEN', xsrf);
        if (options.body && !(options.body instanceof FormData)) {
            headers.set('Content-Type', 'application/json');
        }
    }

    const response = await fetch(`/api/v1${path}`, { ...options, method, headers, credentials: 'include' });

    if (response.status === 204) return undefined as T;

    const contentType = response.headers.get('content-type') ?? '';
    const data = contentType.includes('application/json') ? await response.json() : null;

    if (!response.ok) throw new ApiError(response.status, data);

    return data as T;
}

export const api = {
    get: <T>(path: string) => request<T>(path),
    post: <T>(path: string, body?: unknown) =>
        request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
    postForm: <T>(path: string, formData: FormData) => request<T>(path, { method: 'POST', body: formData }),
    put: <T>(path: string, body?: unknown) =>
        request<T>(path, { method: 'PUT', body: body ? JSON.stringify(body) : undefined }),
    delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
};
