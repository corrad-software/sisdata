import { expect } from "@playwright/test";
import type { Api, Json, UserKey } from "./helpers";
import { apiAs, rows } from "./helpers";

/**
 * Helpers shared by the PER / TSU / PWS UAT specs (a, e, i).
 *
 * approveAll() is a paging-safe variant of helpers.approveInstance(): the demo
 * approvers (mohd.faizal especially) hold well over 100 open WFE tasks after
 * the demo seed, and /wfe/tugasan orders by SLA date, so a freshly created
 * task is often not on the first page of 100.
 *
 * Sessions: RBC allows 3 concurrent sessions per user (sesi_had_serentak) and
 * several specs sign in as the same demo users at the same time, so every
 * session opened here is short-lived and LOGGED OUT afterwards (disposing a
 * request context alone leaves the server session alive and the 4th sign-in
 * ends the oldest one with 401 SESSION_ENDED).
 */

/** A minimal valid PDF (passes DMS magic-number + antivirus checks). */
export const PDF = Buffer.from(
    "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 200 200]/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n",
);

export function pdfFile(name: string) {
    return { name, mimeType: "application/pdf", buffer: PDF };
}

export function today(): string {
    return new Date(Date.now() + 8 * 3600_000).toISOString().slice(0, 10); // Asia/Kuala_Lumpur date
}

/** Log a session out (frees its concurrent-session slot); never throws. */
export async function logout(api: Api): Promise<void> {
    try {
        await api.raw("POST", "/logout", {});
    } catch {
        // already gone
    }
}

/** Run `fn` with a fresh, short-lived API session as `who`, logged out afterwards. */
export async function asUser<T>(
    who: UserKey,
    fn: (api: Api) => Promise<T>,
): Promise<T> {
    const { api, dispose } = await apiAs(who);
    try {
        return await fn(api);
    } finally {
        await logout(api);
        await dispose();
    }
}

/** Every open task of `instanceId` the user may act on, across all pages of /wfe/tugasan. */
export async function openTasks(api: Api, instanceId: string): Promise<Json[]> {
    const found: Json[] = [];
    for (let page = 1; page <= 50; page++) {
        const body = await api.get(`/wfe/tugasan?per_page=100&page=${page}`);
        found.push(
            ...rows(body).filter(
                (t: Json) =>
                    String(t.instans?.id) === String(instanceId) &&
                    t.boleh_bertindak &&
                    t.jenis !== "susulan",
            ),
        );
        if (page >= (body.meta?.last_page ?? 1)) break;
    }
    return found;
}

/**
 * Decide every open task of a WFE instance, stage by stage, as whichever of
 * `approvers` holds it. Returns the decisions made as "<user>:<stage code>".
 */
export async function approveAll(
    instanceId: string,
    approvers: UserKey[] = ["siti", "faizal", "huda"],
    action: "lulus" | "tolak" = "lulus",
    justification = "Disemak dan diluluskan (UAT).",
): Promise<string[]> {
    const sessions = await Promise.all(
        approvers.map(async (who) => ({ who, ...(await apiAs(who)) })),
    );
    const decided: string[] = [];
    try {
        for (let round = 0; round < 10; round++) {
            let progressed = false;
            for (const s of sessions) {
                for (const t of await openTasks(s.api, instanceId)) {
                    await s.api.post(`/wfe/tugasan/${t.id}/tindakan`, {
                        tindakan: action,
                        justifikasi: justification,
                    });
                    decided.push(`${s.who}:${t.peringkat?.kod ?? t.id}`);
                    progressed = true;
                }
            }
            if (!progressed) break;
        }
    } finally {
        await Promise.all(
            sessions.map(async (s) => {
                await logout(s.api);
                await s.dispose();
            }),
        );
    }
    expect(
        decided.length,
        `no WFE task of instance ${instanceId} was open for ${approvers.join(", ")}`,
    ).toBeGreaterThan(0);
    return decided;
}
