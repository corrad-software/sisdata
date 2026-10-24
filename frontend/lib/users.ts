import { api } from './api';
import type { AccountStatus, User } from './types';

export interface PaginatedUsers {
    data: User[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        from: number | null;
        to: number | null;
    };
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
}

export interface UserFilters {
    search?: string;
    status?: AccountStatus | '';
    peranan?: string;
    bahagian?: string;
    page?: number;
}

export function fetchUsers(filters: UserFilters | string, page = 1) {
    const f: UserFilters = typeof filters === 'string' ? { search: filters, page } : filters;
    const params = new URLSearchParams();
    if (f.search) params.set('search', f.search);
    if (f.status) params.set('status', f.status);
    if (f.peranan) params.set('peranan', f.peranan);
    if (f.bahagian) params.set('bahagian', f.bahagian);
    if ((f.page ?? 1) > 1) params.set('page', String(f.page));
    const qs = params.toString();
    return api.get<PaginatedUsers>(`/users${qs ? `?${qs}` : ''}`);
}

export interface UserFormPayload {
    name: string;
    email: string;
    password?: string;
    jenis_pengesahan?: 'dalaman' | 'sso';
    /** Role ids (MOD-RBC). */
    peranan?: number[];
    status_akaun?: AccountStatus;
    no_pekerja?: string | null;
    bahagian?: string | null;
    unit?: string | null;
    jawatan?: string | null;
    tarikh_mula_capaian?: string | null;
    tarikh_tamat_capaian?: string | null;
}

export function createUser(payload: UserFormPayload) {
    return api.post<{ data: User }>('/users', payload);
}

export function updateUser(id: number, payload: UserFormPayload) {
    return api.put<{ data: User }>(`/users/${id}`, payload);
}

export function deleteUser(id: number) {
    return api.delete<void>(`/users/${id}`);
}
