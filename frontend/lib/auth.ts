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

export function logout() {
    return api.post<void>('/logout');
}
