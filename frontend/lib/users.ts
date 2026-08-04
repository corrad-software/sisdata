import { api } from './api';
import type { User } from './types';

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

export function fetchUsers(search: string, page: number) {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (page > 1) params.set('page', String(page));
    const qs = params.toString();
    return api.get<PaginatedUsers>(`/users${qs ? `?${qs}` : ''}`);
}

export interface UserFormPayload {
    name: string;
    email: string;
    password?: string;
    role: 'admin' | 'user';
    is_active: boolean;
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
