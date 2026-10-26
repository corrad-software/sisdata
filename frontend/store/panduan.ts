import { create } from 'zustand';
import type { TetapanPanduan } from '@/lib/types';

/** SRS-GEN-08: shared state between <PanduanInteraktif />, the "?" button and the user menu. */
interface PanduanState {
    /** The signed-in user's prefs (seeded from `/api/v1/user`, then kept in sync with PUT /rbc/panduan). */
    tetapan: TetapanPanduan | null;
    setTetapan: (t: TetapanPanduan) => void;
    /** Tour keys available on the current page (drives the "?" button and menu item). */
    tersedia: string[];
    setTersedia: (k: string[]) => void;
    /** Replay request — a counter so the same request can be repeated. */
    ulang: number;
    minta: () => void;
}

export const usePanduanStore = create<PanduanState>()((set) => ({
    tetapan: null,
    setTetapan: (tetapan) => set({ tetapan }),
    tersedia: [],
    setTersedia: (tersedia) => set({ tersedia }),
    ulang: 0,
    minta: () => set((s) => ({ ulang: s.ulang + 1 })),
}));
