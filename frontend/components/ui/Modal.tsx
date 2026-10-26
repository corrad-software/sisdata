'use client';

import { useEffect } from 'react';
import type { ReactNode } from 'react';

export function Modal({ open, onClose, children }: { open: boolean; onClose: () => void; children: ReactNode }) {
    useEffect(() => {
        function handleKey(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
        }
        if (open) document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div className="fixed inset-0 bg-bg-black-900/50" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div
                    className="relative w-full sm:max-w-lg bg-bg-white rounded-lg shadow-card"
                    onClick={(e) => e.stopPropagation()}
                >
                    {children}
                </div>
            </div>
        </div>
    );
}
