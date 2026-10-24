/*
 * MOD-PTN field app offline store (SRS-PTN-05, security control "data lawatan
 * pada peranti … disulitkan dan dipadam automatik selepas penyegerakan").
 *
 * Everything written to IndexedDB is encrypted with AES-GCM (256-bit, random
 * 96-bit IV per record). The key is derived per session from the officer's
 * PIN with PBKDF2-SHA-256 (310 000 iterations, random per-device salt), is
 * non-extractable and lives only in memory — after a reload the app is
 * locked until the PIN is entered again. Only opaque ids and the record kind
 * are stored in clear. Downloaded tasks, visit drafts and photos are all
 * encrypted; synced visits are deleted from the device.
 */

const DB_NAME = 'sisdata-ptn-lapangan';
const STORE = 'rekod';
const META = 'meta';
const ITERATIONS = 310_000;
const PENGESAH = 'SISDATA-PTN-LAPANGAN';

export type Jenis = 'bundle' | 'lawatan' | 'gambar';

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
        const s = t.objectStore(store);
        const req = fn(s);
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
    const data = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv as BufferSource }, key, bytes as BufferSource);
    return { iv, data };
}

async function nyah(key: CryptoKey, s: Sulit): Promise<ArrayBuffer> {
    return crypto.subtle.decrypt({ name: 'AES-GCM', iv: s.iv as BufferSource }, key, s.data);
}

export async function adaPin(): Promise<boolean> {
    const m = await tx<MetaKunci>(META, 'readonly', (s) => s.get('kunci') as IDBRequest<MetaKunci>);
    return !!m;
}

/** First use on this device: set the PIN (≥ 6 digits). */
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

export async function simpan<T>(key: CryptoKey, id: string, jenis: Jenis, obj: T, induk?: string): Promise<void> {
    const row: Baris = { id, jenis, induk, isi: await sulit(key, enc.encode(JSON.stringify(obj))) };
    await tx(STORE, 'readwrite', (s) => s.put(row));
}

export async function baca<T>(key: CryptoKey, id: string): Promise<T | null> {
    const row = await tx<Baris>(STORE, 'readonly', (s) => s.get(id) as IDBRequest<Baris>);
    if (!row) return null;
    return JSON.parse(dec.decode(await nyah(key, row.isi))) as T;
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

/** Delete a visit and all its photos from the device (after a successful sync). */
export async function padamLawatan(id: string): Promise<void> {
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

/** Ray-casting point-in-polygon for the on-device geofence hint (the server check is authoritative). */
export function dalamPoligon(lng: number, lat: number, geom: { type: string; coordinates: unknown } | null): boolean | null {
    if (!geom) return null;
    const polys = (geom.type === 'MultiPolygon' ? geom.coordinates : [geom.coordinates]) as number[][][][];
    for (const poly of polys) {
        let inside = false;
        const ring = poly[0] ?? [];
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const [xi, yi] = ring[i];
            const [xj, yj] = ring[j];
            if (yi > lat !== yj > lat && lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) inside = !inside;
        }
        if (inside) return true;
    }
    return false;
}

/** Downscale a camera photo to ≤ 2048 px JPEG (keeps device storage small). Gallery files are kept as-is (EXIF). */
export async function kecilkan(file: File, max = 2048): Promise<Blob> {
    try {
        const bmp = await createImageBitmap(file);
        const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
        if (scale === 1 && file.type === 'image/jpeg') return file;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bmp.width * scale);
        canvas.height = Math.round(bmp.height * scale);
        canvas.getContext('2d')?.drawImage(bmp, 0, 0, canvas.width, canvas.height);
        return await new Promise<Blob>((resolve) => canvas.toBlob((b) => resolve(b ?? file), 'image/jpeg', 0.85));
    } catch {
        return file;
    }
}

export function uuid(): string {
    return crypto.randomUUID();
}
