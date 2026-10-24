import { api } from './api';
import type { User } from './types';

export interface LoginPayload {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export function login(payload: LoginPayload) {
    return api.post<{ data: User }>('/login', payload);
}

export function register(payload: RegisterPayload) {
    return api.post<{ data: User }>('/register', payload);
}

/**
 * Resolves to `{ logout_url }` when the session came from SSO MAIS (the
 * caller should navigate there to end the IdP session too), or undefined
 * (204) for a password login.
 */
export function logout() {
    return api.post<{ logout_url?: string } | undefined>('/logout');
}
