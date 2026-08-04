import { api } from './api';
import type { Settings } from './types';

export function fetchSettings() {
    return api.get<{ data: Settings }>('/settings');
}

export interface UpdateSettingsPayload {
    site_title: string;
    footer_text: string | null;
    primary_color: string;
}

export function updateSettings(payload: UpdateSettingsPayload) {
    return api.put<{ data: Settings }>('/settings', payload);
}

function fileFormData(file: File) {
    const formData = new FormData();
    formData.append('file', file);
    return formData;
}

export function uploadLogo(file: File) {
    return api.postForm<{ data: Settings }>('/settings/logo', fileFormData(file));
}

export function uploadFavicon(file: File) {
    return api.postForm<{ data: Settings }>('/settings/favicon', fileFormData(file));
}

export function deleteLogo() {
    return api.delete<{ data: Settings }>('/settings/logo');
}

export function deleteFavicon() {
    return api.delete<{ data: Settings }>('/settings/favicon');
}
