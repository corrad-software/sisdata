'use client';

import 'driver.js/dist/driver.css';
import { useEffect, useRef } from 'react';
import { usePathname } from 'next/navigation';
import { driver, type DestroyReason, type DriveStep, type Driver } from 'driver.js';
import { autoMulaDisekat, panduanApi, panduanUntuk, TETAPAN_LALAI, type KonteksPanduan, type LangkahPanduan, type Panduan } from '@/lib/panduan';
import { usePanduanStore } from '@/store/panduan';
import type { TetapanPanduan, User } from '@/lib/types';

/*
 * SRS-GEN-08 — runs the step tours in lib/panduan.ts. Mounted once in
 * DashboardShell, so never on /login. A tour:
 *  - auto-starts the first time its page is opened (unless the user turned
 *    the guide off, the browser is automated, a password change is pending,
 *    or a modal dialog is open);
 *  - keeps only the steps whose element is on screen and permitted, so each
 *    role gets its own tour;
 *  - is saved as done (finished or skipped) via PUT /api/v1/rbc/panduan.
 * It stops itself when the route changes or another dialog opens, so it can
 * never sit on top of the idle-timeout / forced password change dialogs.
 */

/** A real dialog other than the tour's own popover. */
const SELEKTOR_MODAL = '[aria-modal="true"]:not(.driver-popover), [role="alertdialog"], dialog[open]:not(.driver-layer)';

function modalTerbuka(): boolean {
    return Array.from(document.querySelectorAll(SELEKTOR_MODAL)).some((el) => !el.closest('.driver-layer, .driver-popover'));
}

function kelihatan(el: Element): boolean {
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) return false;
    // Off-canvas (e.g. the mobile sidebar) or hidden by display:none ancestors.
    if (r.right <= 0 || r.left >= window.innerWidth) return false;
    return el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
}

function cariSasaran(sasaran: string | string[] | undefined): Element | null {
    if (!sasaran) return null;
    for (const key of Array.isArray(sasaran) ? sasaran : [sasaran]) {
        let match = Array.from(document.querySelectorAll(`[data-panduan="${key}"]`)).find(kelihatan);
        // A target taller than the screen (the full-height sidebar, a long table) makes a poor,
        // mostly off-screen highlight — narrow it to its first visible child instead.
        while (match && match.getBoundingClientRect().height > window.innerHeight * 0.9) {
            const child = Array.from(match.children).find(kelihatan);
            if (!child) break;
            match = child;
        }
        if (match) return match;
    }
    return null;
}

function binaLangkah(langkah: LangkahPanduan[], can: KonteksPanduan['can']): DriveStep[] {
    const steps: DriveStep[] = [];
    for (const l of langkah) {
        if (l.kebenaran && !can(...l.kebenaran)) continue;
        const el = cariSasaran(l.sasaran);
        if (l.sasaran && !el && !l.tengahJikaTiada) continue; // not on screen for this user → leave it out
        steps.push({
            element: el ?? undefined,
            popover: { title: l.tajuk, description: l.teks, side: l.side, align: l.align ?? 'start' },
        });
    }
    return steps;
}

function modulDalamMenu(): string[] {
    return Array.from(document.querySelectorAll('[data-panduan="sidebar"] [data-panduan^="nav-"]'))
        .map((el) => el.textContent?.trim() ?? '')
        .filter((t) => t && t !== 'Papan Pemuka' && t !== 'Perihal SISDATA' && t !== 'Notifikasi');
}

/** Wait until the tour's content is on screen and no dialog is open (polls; gives up after `ms`). */
function tungguSedia(tour: Panduan, batal: { v: boolean }, ms = 8000): Promise<boolean> {
    const until = Date.now() + ms;
    return new Promise((resolve) => {
        const semak = () => {
            if (batal.v) return resolve(false);
            const ada = !tour.tunggu || tour.tunggu.some((k) => cariSasaran(k));
            if (ada && !modalTerbuka() && document.visibilityState === 'visible') return resolve(true);
            if (Date.now() > until) return resolve(ada && !modalTerbuka());
            window.setTimeout(semak, 300);
        };
        semak();
    });
}

export function PanduanInteraktif({ user }: { user: User }) {
    const pathname = usePathname();
    const tetapan = usePanduanStore((s) => s.tetapan);
    const setTetapan = usePanduanStore((s) => s.setTetapan);
    const setTersedia = usePanduanStore((s) => s.setTersedia);
    const ulang = usePanduanStore((s) => s.ulang);
    const aktif = useRef<Driver | null>(null);
    const ulangTerakhir = useRef(ulang);

    // Seed from the /api/v1/user payload once.
    useEffect(() => {
        if (!usePanduanStore.getState().tetapan) setTetapan(user.tetapan_panduan ?? TETAPAN_LALAI);
    }, [user.tetapan_panduan, setTetapan]);

    useEffect(() => {
        setTersedia(panduanUntuk(pathname).map((t) => t.kunci));
    }, [pathname, setTersedia]);

    // One effect owns the tour lifecycle for the current page: auto-start and replay requests.
    useEffect(() => {
        if (!tetapan) return;
        const batal = { v: false };
        const tours = panduanUntuk(pathname);
        const perms = user.permissions ?? [];
        const can = (...p: string[]) => p.some((x) => perms.includes(x));
        const kurangGerakan = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let pemerhati: MutationObserver | null = null;

        const tandaSelesai = (tour: Panduan) => {
            const semasa = usePanduanStore.getState().tetapan ?? TETAPAN_LALAI;
            if (semasa.selesai[tour.kunci] === tour.versi) return;
            const optimis: TetapanPanduan = { ...semasa, selesai: { ...semasa.selesai, [tour.kunci]: tour.versi } };
            usePanduanStore.getState().setTetapan(optimis);
            panduanApi
                .kemasKini({ selesai: { kunci: tour.kunci, versi: tour.versi } })
                .then((t) => usePanduanStore.getState().setTetapan(t))
                .catch(() => undefined); // best effort — at worst the tour shows once more
        };

        const mula = async (senarai: Panduan[]) => {
            const [tour, ...baki] = senarai;
            if (!tour || batal.v) return;
            if (!(await tungguSedia(tour, batal))) return;

            const k: KonteksPanduan = { nama: user.name, peranan: user.peranan ?? [], can, modul: modulDalamMenu() };
            const steps = binaLangkah(tour.langkah(k), can);
            if (steps.length < 2) {
                // Nothing meaningful for this user here; try the next tour on the page.
                if (baki.length) void mula(baki);
                return;
            }

            aktif.current?.destroy();
            let dilangkau = false;
            const d = driver({
                steps,
                // driver.js's animated stage transition left the overlay unpainted (page not
                // dimmed) in Chromium; an instant move between steps is reliable — and calmer.
                animate: false,
                smoothScroll: !kurangGerakan,
                showProgress: true,
                progressText: '{{current}} daripada {{total}}',
                nextBtnText: 'Seterusnya',
                prevBtnText: 'Sebelum',
                doneBtnText: 'Selesai',
                closeBtnLabel: 'Tutup panduan',
                popoverClass: 'sisdata-panduan',
                stagePadding: 6,
                stageRadius: 8,
                overlayOpacity: 0.55,
                onPopoverRender: (popover, opts) => {
                    // "Langkau" (skip) on every step but the last.
                    if (opts.driver.isLastStep()) return;
                    const langkau = document.createElement('button');
                    langkau.type = 'button';
                    langkau.className = 'sisdata-panduan-langkau';
                    langkau.textContent = 'Langkau';
                    langkau.addEventListener('click', () => {
                        dilangkau = true;
                        opts.driver.destroy();
                    });
                    popover.footer.insertBefore(langkau, popover.footer.firstChild);
                },
                onDestroyed: (_el, _step, opts) => {
                    pemerhati?.disconnect();
                    pemerhati = null;
                    if (aktif.current === d) aktif.current = null;
                    const sebab: DestroyReason | undefined = opts.reason;
                    // "api" = stopped by us (route change / dialog opened): show it again next time.
                    if (batal.v || (sebab === 'api' && !dilangkau)) return;
                    tandaSelesai(tour);
                    if (sebab === 'done' && baki.length) window.setTimeout(() => void mula(baki), 500);
                },
            });
            aktif.current = d;
            d.drive();

            // Step aside the moment any real dialog (idle warning, forced password change…) opens.
            pemerhati = new MutationObserver(() => {
                if (modalTerbuka()) d.destroy();
            });
            pemerhati.observe(document.body, { childList: true, subtree: true });
        };

        // Replay ("?" button / user menu) — works even when auto-start is off.
        if (ulang !== ulangTerakhir.current) {
            ulangTerakhir.current = ulang;
            void mula(tours);
        } else if (tetapan.aktif && !user.perlu_tukar_kata_laluan && !autoMulaDisekat()) {
            const belum = tours.filter((t) => tetapan.selesai[t.kunci] !== t.versi);
            if (belum.length) {
                const t = window.setTimeout(() => void mula(belum), 900);
                return () => {
                    window.clearTimeout(t);
                    batal.v = true;
                    pemerhati?.disconnect();
                    aktif.current?.destroy();
                };
            }
        }

        return () => {
            batal.v = true;
            pemerhati?.disconnect();
            aktif.current?.destroy();
        };
        // tetapan.aktif (not the whole object) so marking a tour done doesn't restart the page's tours.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pathname, tetapan?.aktif, tetapan === null, ulang, user.id]);

    return null;
}

/** Small "?" in the top bar — replays the current page's tour (shown only where one exists). */
export function UlangPanduanButton() {
    const tersedia = usePanduanStore((s) => s.tersedia);
    const minta = usePanduanStore((s) => s.minta);
    if (tersedia.length === 0) return null;

    return (
        <button
            type="button"
            onClick={minta}
            data-panduan="ulang-panduan"
            aria-label="Ulang panduan halaman ini"
            title="Ulang panduan halaman ini"
            className="hidden sm:inline-flex h-8 w-8 items-center justify-center rounded-full border border-otl-gray-300 text-body-sm font-semibold text-txt-black-700 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary"
        >
            ?
        </button>
    );
}
