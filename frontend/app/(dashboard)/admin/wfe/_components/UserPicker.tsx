'use client';

import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { InputLabel } from '@/components/ui/InputLabel';

export interface PickedUser {
    id: number;
    name: string;
    email: string;
}

/** Search-as-you-type user picker (GET /wfe/pengguna). */
export function UserPicker({ label, value, onChange, id }: { label: string; value: PickedUser | null; onChange: (u: PickedUser | null) => void; id: string }) {
    const [term, setTerm] = useState('');
    const [debounced, setDebounced] = useState('');
    useEffect(() => {
        const t = setTimeout(() => setDebounced(term), 300);
        return () => clearTimeout(t);
    }, [term]);
    const { data } = useQuery({
        queryKey: ['wfe', 'pengguna', debounced],
        queryFn: () => api.get<{ data: PickedUser[] }>(`/wfe/pengguna?q=${encodeURIComponent(debounced)}`).then((r) => r.data),
        enabled: debounced.length >= 2,
    });

    return (
        <div>
            <InputLabel htmlFor={id} className="mb-1.5">
                {label}
            </InputLabel>
            {value ? (
                <div className="flex items-center justify-between rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm">
                    <span>
                        {value.name} <span className="text-txt-black-500">({value.email})</span>
                    </span>
                    <button type="button" className="text-primary-600 font-medium" onClick={() => onChange(null)}>
                        Tukar
                    </button>
                </div>
            ) : (
                <>
                    <input
                        id={id}
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Cari nama atau e-mel (min. 2 aksara)"
                        className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-body-sm"
                    />
                    {(data ?? []).length > 0 && (
                        <ul className="mt-1 max-h-48 overflow-y-auto rounded-md border border-otl-gray-200 bg-bg-white">
                            {data?.map((u) => (
                                <li key={u.id}>
                                    <button type="button" className="w-full text-left px-3 py-2 text-body-sm hover:bg-bg-washed" onClick={() => onChange(u)}>
                                        {u.name} <span className="text-txt-black-500">({u.email})</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}
