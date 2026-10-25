import { apiAs, rows } from './helpers';
import type { Json, UserKey } from './helpers';

/**
 * Like helpers.approveInstance, but pages through the whole WFE inbox: the
 * inbox is ordered by SLA deadline, so on a busy UAT DB (seeded tasks + other
 * runs) a fresh task can sit beyond the first 100 rows and approveInstance
 * would silently miss it.
 */
export async function luluskanInstans(instanceId: string, approvers: UserKey[] = ['siti', 'faizal', 'huda'], justifikasi = 'Disemak dan diluluskan (UAT).'): Promise<{ who: UserKey; peringkat: string }[]> {
    const sessions = await Promise.all(approvers.map(async (who) => ({ who, ...(await apiAs(who)) })));
    const log: { who: UserKey; peringkat: string }[] = [];
    try {
        for (let round = 0; round < 10; round++) {
            let progressed = false;
            for (const s of sessions) {
                const mine: Json[] = [];
                for (let page = 1, last = 1; page <= last && page <= 50; page++) {
                    const body = await s.api.get(`/wfe/tugasan?per_page=100&page=${page}`);
                    last = body.meta?.last_page ?? 1;
                    mine.push(...rows(body).filter((t: Json) => String(t.instans?.id) === String(instanceId) && t.boleh_bertindak && t.jenis !== 'susulan'));
                }
                for (const t of mine) {
                    await s.api.post(`/wfe/tugasan/${t.id}/tindakan`, { tindakan: 'lulus', justifikasi });
                    log.push({ who: s.who, peringkat: t.peringkat?.kod ?? '' });
                    progressed = true;
                }
            }
            if (!progressed) break;
        }
    } finally {
        await Promise.all(sessions.map((s) => s.dispose()));
    }
    return log;
}
