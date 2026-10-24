/*
 * MOD-AST field app offline store (SRS-AST-20, security control "gambar dan
 * borang pemeriksaan yang disimpan luar talian dalam PWA disulitkan pada
 * peranti sebelum penyegerakan"). Same design as MOD-PTN's field app
 * (lib/ptn-offline.ts), in its own IndexedDB database so the two apps never
 * share keys or records:
 *
 *  - AES-GCM 256-bit, random 96-bit IV per record;
 *  - key derived from the officer's device PIN with PBKDF2-SHA-256
 *    (310 000 iterations, random per-device salt), non-extractable, kept in
 *    memory only — after a reload the app is locked until the PIN is entered;
 *  - only opaque ids and the record kind are stored in clear;
 *  - synced inspections (and their photos) are deleted from the device.
 */

export { kecilkan, uuid } from './ptn-offline';

const DB_NAME = 'sisdata-ast-lapangan';
const STORE = 'rekod';
const META = 'meta';
const ITERATIONS = 310_000;
const PENGESAH = 'SISDATA-AST-LAPANGAN';

export type Jenis = 'bundle' | 'pemeriksaan' | 'gambar';

interface Sulit {
    iv: Uint8Array;
    data: ArrayBuffer;
}

interface Baris {
    id: string;
    jenis: Jenis;
    induk?: string;
    isi: Sulit;
    blob?: Sulit;
}

interface MetaKunci {
    id: 'kunci';
    salt: Uint8Array;
    iterasi: number;
    pengesah: Sulit;
}

function bukaDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const s = db.createObjectStore(STORE, { keyPath: 'id' });
                s.createIndex('jenis', 'jenis');
                s.createIndex('induk', 'induk');
            }
            if (!db.objectStoreNames.contains(META)) db.createObjectStore(META, { keyPath: 'id' });
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function tx<T>(store: string, mode: IDBTransactionMode, fn: (s: IDBObjectStore) => IDBRequest<T> | void): Promise<T | undefined> {
    const db = await bukaDb();
    return new Promise((resolve, reject) => {
        const t = db.transaction(store, mode);
        const req = fn(t.objectStore(store));
        let result: T | undefined;
        if (req) req.onsuccess = () => (result = req.result);
        t.oncomplete = () => {
            db.close();
            resolve(result);
        };
        t.onerror = () => reject(t.error);
        t.onabort = () => reject(t.error);
    });
}

const enc = new TextEncoder();
const dec = new TextDecoder();

async function terbit(pin: string, salt: Uint8Array, iterasi: number): Promise<CryptoKey> {
    const bahan = await crypto.subtle.importKey('raw', enc.encode(pin), 'PBKDF2', false, ['deriveKey']);
    return crypto.subtle.deriveKey({ name: 'PBKDF2', hash: 'SHA-256', salt: salt as BufferSource, iterations: iterasi }, bahan, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
}

async function sulit(key: CryptoKey, bytes: ArrayBuffer | Uint8Array): Promise<Sulit> {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    return { iv, data: await crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv as BufferSource }, key, bytes as BufferSource) };
}

async function nyah(key: CryptoKey, s: Sulit): Promise<ArrayBuffer> {
    return crypto.subtle.decrypt({ name: 'AES-GCM', iv: s.iv as BufferSource }, key, s.data);
}

export async function adaPin(): Promise<boolean> {
    return !!(await tx<MetaKunci>(META, 'readonly', (s) => s.get('kunci') as IDBRequest<MetaKunci>));
}

/** First use on this device: set the PIN (6–12 digits). */
export async function tetapkanPin(pin: string): Promise<CryptoKey> {
    if (!/^\d{6,12}$/.test(pin)) throw new Error('PIN mesti 6 hingga 12 digit.');
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const key = await terbit(pin, salt, ITERATIONS);
    const meta: MetaKunci = { id: 'kunci', salt, iterasi: ITERATIONS, pengesah: await sulit(key, enc.encode(PENGESAH)) };
    await tx(META, 'readwrite', (s) => s.put(meta));
    return key;
}

/** Unlock: derive the session key from the PIN and check it against the stored verifier. */
export async function buka(pin: string): Promise<CryptoKey> {
    const m = await tx<MetaKunci>(META, 'readonly', (s) => s.get('kunci') as IDBRequest<MetaKunci>);
    if (!m) throw new Error('PIN belum ditetapkan.');
    const key = await terbit(pin, m.salt, m.iterasi);
    try {
        if (dec.decode(await nyah(key, m.pengesah)) !== PENGESAH) throw new Error();
    } catch {
        throw new Error('PIN tidak sah.');
    }
    return key;
}

export async function simpan<T>(key: CryptoKey, id: string, jenis: Jenis, obj: T): Promise<void> {
    const row: Baris = { id, jenis, isi: await sulit(key, enc.encode(JSON.stringify(obj))) };
    await tx(STORE, 'readwrite', (s) => s.put(row));
}

export async function baca<T>(key: CryptoKey, id: string): Promise<T | null> {
    const row = await tx<Baris>(STORE, 'readonly', (s) => s.get(id) as IDBRequest<Baris>);
    return row ? (JSON.parse(dec.decode(await nyah(key, row.isi))) as T) : null;
}

export async function senarai<T>(key: CryptoKey, jenis: Jenis): Promise<T[]> {
    const rows = (await tx<Baris[]>(STORE, 'readonly', (s) => s.index('jenis').getAll(jenis) as IDBRequest<Baris[]>)) ?? [];
    return Promise.all(rows.map(async (r) => JSON.parse(dec.decode(await nyah(key, r.isi))) as T));
}

export async function simpanGambar<M>(key: CryptoKey, id: string, induk: string, meta: M, blob: Blob): Promise<void> {
    const row: Baris = { id, jenis: 'gambar', induk, isi: await sulit(key, enc.encode(JSON.stringify(meta))), blob: await sulit(key, await blob.arrayBuffer()) };
    await tx(STORE, 'readwrite', (s) => s.put(row));
}

export async function kemaskiniMetaGambar<M>(key: CryptoKey, id: string, meta: M): Promise<void> {
    const row = await tx<Baris>(STORE, 'readonly', (s) => s.get(id) as IDBRequest<Baris>);
    if (!row) return;
    row.isi = await sulit(key, enc.encode(JSON.stringify(meta)));
    await tx(STORE, 'readwrite', (s) => s.put(row));
}

export async function gambarUntuk<M>(key: CryptoKey, induk: string): Promise<Array<{ id: string; meta: M; blob: Blob }>> {
    const rows = (await tx<Baris[]>(STORE, 'readonly', (s) => s.index('induk').getAll(induk) as IDBRequest<Baris[]>)) ?? [];
    return Promise.all(
        rows.filter((r) => r.jenis === 'gambar' && r.blob).map(async (r) => ({
            id: r.id,
            meta: JSON.parse(dec.decode(await nyah(key, r.isi))) as M,
            blob: new Blob([await nyah(key, r.blob as Sulit)], { type: 'image/jpeg' }),
        })),
    );
}

export async function padam(id: string): Promise<void> {
    await tx(STORE, 'readwrite', (s) => s.delete(id));
}

/** Delete an inspection and all its photos from the device (after a successful sync). */
export async function padamPemeriksaan(id: string): Promise<void> {
    const rows = (await tx<Baris[]>(STORE, 'readonly', (s) => s.index('induk').getAll(id) as IDBRequest<Baris[]>)) ?? [];
    await tx(STORE, 'readwrite', (s) => {
        rows.forEach((r) => s.delete(r.id));
        s.delete(id);
    });
}

/** Wipe everything (forgotten PIN / hand-over of the device). */
export async function kosongkan(): Promise<void> {
    await tx(STORE, 'readwrite', (s) => s.clear());
    await tx(META, 'readwrite', (s) => s.clear());
}

/** Great-circle distance in metres (on-device "di lokasi aset" hint; the server check is authoritative). */
export function jarakM(lat1: number, lng1: number, lat2: number, lng2: number): number {
    const r = 6_371_000;
    const rad = (d: number) => (d * Math.PI) / 180;
    const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
    return 2 * r * Math.asin(Math.sqrt(a));
}

/** Pull the QR reference out of a scanned value (full public URL or the bare reference). */
export function rujukanQr(nilai: string): string | null {
    const m = nilai.trim().match(/(?:\/aset\/q\/)?([0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{12})\/?$/);
    return m ? m[1].toUpperCase() : null;
}
