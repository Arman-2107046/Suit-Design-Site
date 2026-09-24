import React, { useState, useEffect, useLayoutEffect, useMemo, useCallback, useRef, memo } from "react";
import { Head, Link } from "@inertiajs/react";
import { addToCart, cartCount, summarizeDesign, useCart } from "@/lib/store";
import { Loader2, Layers, Scissors, Palette, Menu, X, RotateCcw, Maximize2, ArrowLeft, ArrowRight, ShoppingBag, Check, Info, Images, Share2, Heart, ChevronLeft, ChevronRight, Plus } from "lucide-react";

const STORAGE_KEY = "custom-tailor.design.v1";
const CANVAS_TIMEOUT_MS = 8000;

/* ------------------------------------------------------------------ */
/*  Image URLs                                                         */
/*                                                                     */
/*  Layer renders are stored as ~2-4 MB PNGs (2291x2727). Cloudinary   */
/*  can transcode + resize on the fly, so we ask for a device-sized    */
/*  WebP/AVIF instead (~50-200 KB) — that is what turns a fabric swap  */
/*  from a long buffer into a short one. Non-Cloudinary URLs and URLs  */
/*  that already carry a transformation are left untouched.            */
/* ------------------------------------------------------------------ */
const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;

function optimizeUrl(url, width) {
    if (!url) return url;
    const m = CLOUDINARY_UPLOAD.exec(url);
    if (!m) return url;
    return `${m[1]}f_auto,q_auto${width ? `,w_${width}` : ""}/${m[2]}`;
}

/* Largest the suit can be drawn on this device, in physical pixels, rounded to a cache-friendly step. */
function pickLayerWidth() {
    if (typeof window === "undefined") return 1600;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const longest = Math.max(window.screen?.width || 0, window.screen?.height || 0, window.innerHeight || 0);
    return Math.min(1600, Math.max(800, Math.ceil((longest * dpr) / 400) * 400));
}

const LAYER_WIDTH = pickLayerWidth();
/* The lightbox zooms in, so it gets a sharper set of layers (the source renders are 2291 px wide). */
const HIRES_WIDTH = LAYER_WIDTH >= 1600 ? 2000 : 1600;
const LIGHTBOX_ZOOM = 2.5;
const THUMB_WIDTH = 320;

const layerUrl = (url) => optimizeUrl(url, LAYER_WIDTH);
const hiresUrl = (url) => optimizeUrl(url, HIRES_WIDTH);
const thumbUrl = (url) => optimizeUrl(url, THUMB_WIDTH);

/* Native render aspect (width / height) — used until the first layer is decoded. */
const DEFAULT_STAGE_ASPECT = 2291 / 2727;

/* ------------------------------------------------------------------ */
/*  Image cache                                                        */
/*                                                                     */
/*  Layer renders are fetched as Blobs (tiny: 50-200 KB each), which   */
/*  is what lets createImageBitmap() decode them off the main thread.  */
/*  Hosts without CORS fall back to a plain <img>. Foreground loads    */
/*  (`load` / `loadAll`) are what a commit waits on; background        */
/*  prefetch (`prefetch`) runs through a small concurrency gate so     */
/*  warming up other fabrics never starves the current view.          */
/* ------------------------------------------------------------------ */
class ImageCache {
    constructor() {
        this.cache = new Map();     // url -> Blob | HTMLImageElement
        this.inflight = new Map();  // url -> Promise
        this.failed = new Set();
        this.queue = [];
        this.queued = new Set();
        this.running = 0;
        this.maxBackground = 6;
    }

    has(url) {
        return this.cache.has(url);
    }

    get(url) {
        return this.cache.get(url) || null;
    }

    settled(url) {
        return !url || this.cache.has(url) || this.failed.has(url);
    }

    allSettled(urls) {
        return urls.every((u) => this.settled(u));
    }

    fetchBlob(url) {
        if (typeof fetch !== "function") return Promise.reject(new Error("no fetch"));
        return fetch(url, { mode: "cors", credentials: "omit" }).then((res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.blob();
        });
    }

    loadElement(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.decoding = "async";
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error("image error"));
            img.src = url;
        });
    }

    load(url) {
        if (!url) return Promise.resolve(null);
        if (this.cache.has(url)) return Promise.resolve(this.cache.get(url));
        if (this.inflight.has(url)) return this.inflight.get(url);

        const promise = this.fetchBlob(url)
            .catch(() => this.loadElement(url))
            .then((source) => {
                this.cache.set(url, source);
                return source;
            })
            .catch(() => {
                console.warn(`Failed to load image: ${url}`);
                this.failed.add(url);
                return null;
            })
            .finally(() => this.inflight.delete(url));

        this.inflight.set(url, promise);
        return promise;
    }

    loadAll(urls) {
        return Promise.all([...new Set(urls.filter(Boolean))].map((u) => this.load(u)));
    }

    prefetch(urls, { front = false } = {}) {
        const fresh = [...new Set(urls.filter(Boolean))].filter(
            (u) => !this.settled(u) && !this.inflight.has(u) && !this.queued.has(u)
        );
        if (fresh.length === 0) return;
        fresh.forEach((u) => this.queued.add(u));
        if (front) this.queue.unshift(...fresh);
        else this.queue.push(...fresh);
        this.pump();
    }

    clearQueue() {
        this.queue = [];
        this.queued.clear();
    }

    pump() {
        while (this.running < this.maxBackground && this.queue.length > 0) {
            const url = this.queue.shift();
            this.queued.delete(url);
            if (this.settled(url)) continue;
            this.running += 1;
            this.load(url).finally(() => {
                this.running -= 1;
                this.pump();
            });
        }
    }
}

const imageCache = new ImageCache();

/* ------------------------------------------------------------------ */
/*  Bitmap cache                                                       */
/*                                                                     */
/*  A cached Blob is not a decoded image. createImageBitmap(blob)      */
/*  decodes off the main thread and pins the pixels, so a commit       */
/*  prepares bitmaps while it is "buffering" and the swap itself is a  */
/*  few sub-millisecond blits in one frame. Kept under a memory budget */
/*  as an LRU; the layers currently on screen are pinned so a window   */
/*  resize never has to wait.                                          */
/* ------------------------------------------------------------------ */

/* ------------------------------------------------------------------ */
/*  Colour pipeline                                                    */
/*                                                                     */
/*  The renders were authored in Adobe RGB (1998), but only some of    */
/*  them (sleeve, lapel, pockets, buttons, custom lining) were exported */
/*  with the profile embedded; the body, default lining and every      */
/*  swatch are untagged. A browser colour-manages the tagged ones and  */
/*  shows the rest raw, which is why they never matched.               */
/*                                                                     */
/*  So the embedded profile is ignored on decode and *every* image is  */
/*  treated as Adobe RGB and converted to sRGB for display — the same  */
/*  richer look the tagged layers had, now applied uniformly. The      */
/*  per-pixel work runs in Web Workers while a change is buffering,    */
/*  and the converted bitmaps are what the cache keeps.                */
/* ------------------------------------------------------------------ */
const COLOR_PROFILE = "adobe-rgb"; // "adobe-rgb" | "raw"

const RAW_DECODE = { colorSpaceConversion: "none", premultiplyAlpha: "none" };

/* Self-contained (no outer references) so its source can be shipped to a worker. */
function makeAdobeRgbToSrgb() {
    const GAMMA = 563 / 256; // Adobe RGB (1998) transfer curve
    const toLinear = new Float32Array(256);
    for (let i = 0; i < 256; i++) toLinear[i] = Math.pow(i / 255, GAMMA);
    const N = 4096;
    const toSrgb = new Uint8ClampedArray(N + 1);
    for (let i = 0; i <= N; i++) {
        const v = i / N;
        toSrgb[i] = Math.round((v <= 0.0031308 ? 12.92 * v : 1.055 * Math.pow(v, 1 / 2.4) - 0.055) * 255);
    }
    const enc = (v) => toSrgb[v <= 0 ? 0 : v >= 1 ? N : (v * N + 0.5) | 0];
    // Linear Adobe RGB -> linear sRGB (both D65).
    return function convert(data) {
        for (let i = 0; i < data.length; i += 4) {
            if (data[i + 3] === 0) continue;
            const r = toLinear[data[i]];
            const g = toLinear[data[i + 1]];
            const b = toLinear[data[i + 2]];
            data[i] = enc(1.39835 * r - 0.39835 * g);
            data[i + 1] = enc(g);
            data[i + 2] = enc(-0.04292 * g + 1.04292 * b);
        }
    };
}

const COLOR_WORKER_SOURCE = `
const convert = (${makeAdobeRgbToSrgb.toString()})();
self.onmessage = async (e) => {
    const { id, blob } = e.data;
    try {
        const bmp = await createImageBitmap(blob, { colorSpaceConversion: "none", premultiplyAlpha: "none" });
        // No willReadFrequently: a GPU-backed canvas hands back a GPU-backed bitmap,
        // so drawing it on the main canvas is a blit rather than a 12 MB upload.
        const canvas = new OffscreenCanvas(bmp.width, bmp.height);
        const ctx = canvas.getContext("2d");
        ctx.drawImage(bmp, 0, 0);
        bmp.close();
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        convert(img.data);
        ctx.putImageData(img, 0, 0);
        const out = canvas.transferToImageBitmap();
        self.postMessage({ id, bitmap: out }, [out]);
    } catch (err) {
        self.postMessage({ id, error: String(err) });
    }
};
`;

class ColorPipeline {
    constructor() {
        this.mode = COLOR_PROFILE;
        this.workers = [];
        this.next = 0;
        this.seq = 0;
        this.pending = new Map();
        this.convertMain = null;
        const canWork =
            typeof Worker === "function" && typeof OffscreenCanvas === "function" && typeof createImageBitmap === "function";
        if (this.mode === "adobe-rgb" && canWork) {
            try {
                const url = URL.createObjectURL(new Blob([COLOR_WORKER_SOURCE], { type: "text/javascript" }));
                const n = Math.max(1, Math.min(3, (navigator.hardwareConcurrency || 2) - 1));
                for (let i = 0; i < n; i++) {
                    const w = new Worker(url);
                    w.onmessage = (e) => this.settle(e.data);
                    w.onerror = () => this.failAll();
                    this.workers.push(w);
                }
            } catch {
                this.workers = [];
            }
        }
    }

    settle({ id, bitmap, error }) {
        const p = this.pending.get(id);
        if (!p) return;
        this.pending.delete(id);
        if (bitmap) p.resolve(bitmap);
        else p.reject(new Error(error || "colour worker failed"));
    }

    failAll() {
        this.workers.forEach((w) => w.terminate());
        this.workers = [];
        for (const [id, p] of this.pending) {
            this.pending.delete(id);
            p.reject(new Error("colour worker crashed"));
        }
    }

    /* Blob | HTMLImageElement -> ImageBitmap in display (sRGB) colour. */
    async decode(source) {
        if (this.mode !== "adobe-rgb") return createImageBitmap(source, RAW_DECODE);

        if (source instanceof Blob && this.workers.length > 0) {
            try {
                return await this.inWorker(source);
            } catch {
                /* fall through to the main thread */
            }
        }

        const bmp = await createImageBitmap(source, RAW_DECODE);
        try {
            const canvas = document.createElement("canvas");
            canvas.width = bmp.width;
            canvas.height = bmp.height;
            const ctx = canvas.getContext("2d", { willReadFrequently: true });
            ctx.drawImage(bmp, 0, 0);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height); // throws on a tainted (non-CORS) canvas
            if (!this.convertMain) this.convertMain = makeAdobeRgbToSrgb();
            this.convertMain(img.data);
            ctx.putImageData(img, 0, 0);
            const out = await createImageBitmap(canvas);
            bmp.close();
            return out;
        } catch {
            return bmp; // shown unconverted rather than not at all
        }
    }

    inWorker(blob) {
        return new Promise((resolve, reject) => {
            const id = ++this.seq;
            this.pending.set(id, { resolve, reject });
            const w = this.workers[this.next++ % this.workers.length];
            w.postMessage({ id, blob });
        });
    }
}

const colorPipeline = new ColorPipeline();

class BitmapCache {
    constructor(budgetBytes) {
        this.budget = budgetBytes;
        this.bytes = 0;
        this.map = new Map();      // url -> ImageBitmap, insertion order = LRU order
        this.inflight = new Map(); // url -> Promise
        this.pinned = new Set();
        this.supported = typeof createImageBitmap === "function";
    }

    has(url) {
        return this.map.has(url);
    }

    /* Returns the bitmap and marks it most-recently-used. */
    get(url) {
        const bmp = this.map.get(url);
        if (!bmp) return null;
        if (bmp.width === 0) { this.drop(url); return null; } // closed elsewhere
        this.map.delete(url);
        this.map.set(url, bmp);
        return bmp;
    }

    pin(urls) {
        this.pinned = new Set(urls.filter(Boolean));
        this.pinned.forEach((u) => this.get(u)); // also bump them to MRU
    }

    drop(url) {
        const bmp = this.map.get(url);
        if (!bmp) return;
        this.map.delete(url);
        this.bytes -= bmp.width * bmp.height * 4;
        try { bmp.close(); } catch { /* already closed */ }
    }

    put(url, bmp) {
        if (this.map.has(url)) this.drop(url);
        this.map.set(url, bmp);
        this.bytes += bmp.width * bmp.height * 4;
        for (const oldUrl of [...this.map.keys()]) {
            if (this.bytes <= this.budget) break;
            if (oldUrl === url || this.pinned.has(oldUrl)) continue;
            this.drop(oldUrl);
        }
    }

    make(url) {
        if (!url || !this.supported) return Promise.resolve(null);
        const hit = this.get(url);
        if (hit) return Promise.resolve(hit);
        if (this.inflight.has(url)) return this.inflight.get(url);

        const promise = imageCache
            .load(url)
            .then((source) => (source ? colorPipeline.decode(source) : null))
            .then((bmp) => {
                if (bmp) this.put(url, bmp);
                return bmp;
            })
            .catch(() => null)
            .finally(() => this.inflight.delete(url));

        this.inflight.set(url, promise);
        return promise;
    }

    makeAll(urls) {
        return Promise.all([...new Set(urls.filter(Boolean))].map((u) => this.make(u)));
    }

    allReady(urls) {
        return !this.supported || urls.every((u) => !u || this.map.has(u) || imageCache.failed.has(u));
    }
}

/* Enough for the suit on screen plus the last few looks; scaled with the layer size the device asked for. */
const bitmapCache = new BitmapCache(LAYER_WIDTH >= 1600 ? 320 * 1024 * 1024 : 200 * 1024 * 1024);

const runWhenIdle = (fn) => {
    if (typeof window !== "undefined" && "requestIdleCallback" in window) {
        return window.requestIdleCallback(fn, { timeout: 1500 });
    }
    return setTimeout(fn, 150);
};
const cancelIdle = (handle) => {
    if (typeof window !== "undefined" && "cancelIdleCallback" in window) window.cancelIdleCallback(handle);
    else clearTimeout(handle);
};

/* ------------------------------------------------------------------ */
/*  Selection resolution (pure)                                        */
/*                                                                     */
/*  `intent` is the user's semantic design: body type code, lapel      */
/*  category + width, sleeve / pocket type codes, button image, lining */
/*  choice. It is fabric-agnostic. `resolveSelection` maps it onto a   */
/*  concrete fabric: exact record -> same type/code -> is_default ->   */
/*  first available. Exact ids are globally unique so they only match  */
/*  within the fabric they belong to; codes/type ids carry across.     */
/* ------------------------------------------------------------------ */
const EMPTY_INTENT = {
    body: null,        // { id, typeCode, typeId, name }
    lapel: null,       // { id, categoryId, categoryName, subcategoryId, subcategoryName }
    sleeve: null,      // { id, typeCode, typeId, name }
    sidePocket: null,  // { id, typeCode, typeId, name }
    chestPocket: null, // { id, typeCode, typeId, name }
    button: null,      // { id, imageId, name } | null  (null = "Default", no overlay)
    lining: { mode: "default", id: null, fabricId: null, typeId: null, name: null },
};

const defaultOf = (items) => items.find((i) => i.is_default) || items[0] || null;
const byId = (items, id) => (id != null ? items.find((i) => i.id === id) : undefined);

function pickTyped(items, want) {
    if (!items || items.length === 0) return null;
    if (!want) return defaultOf(items);
    return (
        byId(items, want.id) ||
        (want.typeCode && items.find((i) => i.type?.code === want.typeCode)) ||
        (want.typeId != null && items.find((i) => i.type?.id === want.typeId)) ||
        defaultOf(items)
    );
}

function pickBody(bodies, want) {
    if (!bodies || bodies.length === 0) return null;
    if (!want) return defaultOf(bodies);
    return (
        byId(bodies, want.id) ||
        (want.typeCode && bodies.find((b) => b.body_type?.code === want.typeCode)) ||
        (want.typeId != null && bodies.find((b) => b.body_type?.id === want.typeId)) ||
        defaultOf(bodies)
    );
}

function pickLapel(lapels, want) {
    if (!lapels || lapels.length === 0) return null;

    const defaultIn = (group) =>
        group.find((l) => l.is_default) ||
        group.find((l) => l.subcategory?.is_default) ||
        group[0];

    const defaultCategoryId =
        (lapels.find((l) => l.is_default) || lapels.find((l) => l.category?.is_default))?.category?.id;

    if (!want) {
        const group = defaultCategoryId != null ? lapels.filter((l) => l.category?.id === defaultCategoryId) : lapels;
        return defaultIn(group.length ? group : lapels);
    }

    const exact = byId(lapels, want.id);
    if (exact) return exact;

    const withWidth = (group) =>
        (want.subcategoryId != null && group.find((l) => l.subcategory?.id === want.subcategoryId)) || defaultIn(group);

    // 1. Same category: exact width, else that category's default width.
    const sameCategory = want.categoryId != null ? lapels.filter((l) => l.category?.id === want.categoryId) : [];
    if (sameCategory.length) return withWidth(sameCategory);

    // 2. Category is missing on this body: default category, keeping the width if it exists.
    const defaultCategory = defaultCategoryId != null ? lapels.filter((l) => l.category?.id === defaultCategoryId) : [];
    if (defaultCategory.length) return withWidth(defaultCategory);

    // 3. Anything marked default, else the first lapel.
    return withWidth(lapels);
}

function pickButton(buttons, want) {
    if (!buttons || buttons.length === 0 || !want) return null;
    return (
        byId(buttons, want.id) ||
        (want.imageId != null && buttons.find((b) => b.button_image?.id === want.imageId)) ||
        null // the "Default" tile means no button overlay
    );
}

function pickCustomLining(linings, want) {
    if (!linings || linings.length === 0 || want?.mode !== "custom") return null;
    return (
        byId(linings, want.id) ||
        (want.fabricId != null &&
            want.typeId != null &&
            linings.find((l) => l.fabric?.id === want.fabricId && l.type?.id === want.typeId)) ||
        (want.fabricId != null && linings.find((l) => l.fabric?.id === want.fabricId)) ||
        defaultOf(linings)
    );
}

function resolveSelection(fabric, intent) {
    const body = pickBody(fabric.bodies, intent.body);
    const customLining = pickCustomLining(fabric.custom_linings, intent.lining);
    return {
        fabric,
        body,
        lapel: body ? pickLapel(body.lapels, intent.lapel) : null,
        sleeve: pickTyped(fabric.sleeves, intent.sleeve),
        sidePocket: pickTyped(fabric.side_pockets, intent.sidePocket),
        chestPocket: pickTyped(fabric.chest_pockets, intent.chestPocket),
        button: body ? pickButton(body.body_type?.body_buttons, intent.button) : null,
        liningMode: customLining ? "custom" : "default",
        customLining,
    };
}

/* Intent fragments built from concrete records (used when the user clicks). */
const bodyIntent = (b) => (b ? { id: b.id, typeCode: b.body_type?.code ?? null, typeId: b.body_type?.id ?? null, name: b.body_type?.name ?? null } : null);
const typedIntent = (i) => (i ? { id: i.id, typeCode: i.type?.code ?? null, typeId: i.type?.id ?? null, name: i.type?.name ?? null } : null);
const lapelIntent = (l) =>
    l
        ? {
              id: l.id,
              categoryId: l.category?.id ?? null,
              categoryName: l.category?.name ?? null,
              subcategoryId: l.subcategory?.id ?? null,
              subcategoryName: l.subcategory?.name ?? null,
          }
        : null;
const buttonIntent = (b) => (b ? { id: b.id, imageId: b.button_image?.id ?? null, name: b.button_image?.name ?? null } : null);
const liningIntent = (l) =>
    l
        ? { mode: "custom", id: l.id, fabricId: l.fabric?.id ?? null, typeId: l.type?.id ?? null, name: l.fabric?.name ?? null }
        : { mode: "default", id: null, fabricId: null, typeId: null, name: null };

/* Fill only the parts of the intent the user hasn't chosen yet, from what was resolved. */
function completeIntent(intent, sel) {
    return {
        body: intent.body ?? bodyIntent(sel.body),
        lapel: intent.lapel ?? lapelIntent(sel.lapel),
        sleeve: intent.sleeve ?? typedIntent(sel.sleeve),
        sidePocket: intent.sidePocket ?? typedIntent(sel.sidePocket),
        chestPocket: intent.chestPocket ?? typedIntent(sel.chestPocket),
        button: intent.button,
        lining: intent.lining,
    };
}

/* Human-readable list of where the new fabric couldn't honour the intent. */
function describeAdjustments(intent, sel) {
    const notes = [];
    const arrow = (from, to) => `${from} → ${to}`;

    if (intent.body?.typeCode && sel.body && sel.body.body_type?.code !== intent.body.typeCode) {
        notes.push(arrow(`${intent.body.name ?? "Body"} body`, sel.body.body_type?.name ?? "default"));
    }
    if (intent.lapel && sel.lapel) {
        if (intent.lapel.categoryId != null && sel.lapel.category?.id !== intent.lapel.categoryId) {
            notes.push(arrow(`${intent.lapel.categoryName ?? "Lapel"} lapel`, sel.lapel.category?.name ?? "default"));
        } else if (intent.lapel.subcategoryId != null && sel.lapel.subcategory?.id !== intent.lapel.subcategoryId) {
            notes.push(arrow(`${intent.lapel.subcategoryName ?? "Lapel"} width`, sel.lapel.subcategory?.name ?? "default"));
        }
    }
    const typed = (want, got, label) => {
        if (want?.typeCode && got && got.type?.code !== want.typeCode) {
            notes.push(arrow(`${want.name ?? label} ${label.toLowerCase()}`, got.type?.name ?? "default"));
        }
    };
    typed(intent.sleeve, sel.sleeve, "Sleeves");
    typed(intent.sidePocket, sel.sidePocket, "Side pockets");
    typed(intent.chestPocket, sel.chestPocket, "Chest pocket");

    if (intent.button?.imageId != null && sel.body && sel.button?.button_image?.id !== intent.button.imageId) {
        notes.push(arrow(`${intent.button.name ?? "Selected"} buttons`, sel.button?.button_image?.name ?? "Default"));
    }
    if (intent.lining.mode === "custom") {
        if (!sel.customLining) notes.push(arrow(`${intent.lining.name ?? "Custom"} lining`, "Default"));
        else if (intent.lining.fabricId != null && sel.customLining.fabric?.id !== intent.lining.fabricId) {
            notes.push(arrow(`${intent.lining.name ?? "Custom"} lining`, sel.customLining.fabric?.name ?? "default"));
        }
    }
    return notes;
}

/* ------------------------------------------------------------------ */
/*  Layers                                                             */
/* ------------------------------------------------------------------ */
function layersFrom(sel) {
    if (!sel) return [];
    const layers = [];

    const defaultLining = sel.body?.default_linings?.[0];
    if (defaultLining) {
        layers.push({ type: "defaultLining", image: defaultLining.image, z: defaultLining.layer_index || 0 });
    }
    if (sel.customLining) layers.push({ type: "lining", image: sel.customLining.image, z: sel.customLining.layer_index || 100 });
    if (sel.body) layers.push({ type: "body", image: sel.body.image, z: sel.body.layer_index || 100 });
    if (sel.sleeve) layers.push({ type: "sleeve", image: sel.sleeve.image, z: sel.sleeve.layer_index || 150 });
    if (sel.lapel) layers.push({ type: "lapel", image: sel.lapel.image, z: sel.lapel.layer_index || 150 });
    if (sel.sidePocket) layers.push({ type: "sidePocket", image: sel.sidePocket.image, z: sel.sidePocket.layer_index || 100 });
    if (sel.chestPocket) layers.push({ type: "chestPocket", image: sel.chestPocket.image, z: sel.chestPocket.layer_index || 100 });
    if (sel.button) layers.push({ type: "button", image: sel.button.image, z: sel.button.layer_index || 120 });

    return layers
        .filter((l) => l.image)
        .map((l) => ({ ...l, raw: l.image, image: layerUrl(l.image) }))
        .sort((a, b) => a.z - b.z);
}

const layerUrls = (sel) => layersFrom(sel).map((l) => l.image);

/* Every render image an option on this fabric could put on the canvas. */
function optionUrlsFor(sel) {
    const f = sel.fabric;
    const urls = [];
    (f.bodies || []).forEach((b) => urls.push(b.image, b.default_linings?.[0]?.image));
    (sel.body?.lapels || []).forEach((l) => urls.push(l.image));
    (f.sleeves || []).forEach((s) => urls.push(s.image));
    (f.side_pockets || []).forEach((p) => urls.push(p.image));
    (f.chest_pockets || []).forEach((p) => urls.push(p.image));
    (sel.body?.body_type?.body_buttons || []).forEach((b) => urls.push(b.image));
    (f.custom_linings || []).forEach((l) => urls.push(l.image));
    return urls.map(layerUrl);
}

/* ------------------------------------------------------------------ */
/*  Persistence                                                        */
/* ------------------------------------------------------------------ */
function loadSavedDesign() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== "object" || !parsed.intent) return null;
        return { fabricId: parsed.fabricId ?? null, intent: { ...EMPTY_INTENT, ...parsed.intent } };
    } catch {
        return null;
    }
}

function saveDesign(fabricId, intent) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ fabricId, intent }));
    } catch { /* storage unavailable */ }
}

function clearSavedDesign() {
    try { window.localStorage.removeItem(STORAGE_KEY); } catch { /* ignore */ }
}

/* ------------------------------------------------------------------ */
/*  Presentational components (module scope so they keep identity)     */
/* ------------------------------------------------------------------ */
const LazyImage = ({ src, alt, className, style, fallback = null }) => {
    const [failed, setFailed] = useState(false);
    useEffect(() => setFailed(false), [src]);
    if (!src || failed) return fallback;
    return (
        <img
            src={src}
            alt={alt || ""}
            loading="lazy"
            decoding="async"
            draggable={false}
            className={className}
            style={style}
            onError={() => setFailed(true)}
        />
    );
};

/*
 * Colour-bearing thumbnails (fabric and lining swatches) are drawn through
 * the same colour pipeline as the suit so they match it. Line-art diagrams
 * stay as plain <img>s — they are neutral grey, which the profile leaves alone.
 */
const SwatchImage = ({ src, alt, className, fallback = null }) => {
    const canvasRef = useRef(null);
    const [state, setState] = useState("loading");

    useEffect(() => {
        if (!src) return undefined;
        let alive = true;
        setState("loading");
        bitmapCache.make(src).then((bmp) => {
            if (!alive) return;
            const canvas = canvasRef.current;
            if (!bmp || !canvas) { setState("failed"); return; }
            canvas.width = bmp.width;
            canvas.height = bmp.height;
            canvas.getContext("2d").drawImage(bmp, 0, 0);
            setState("ready");
        });
        return () => { alive = false; };
    }, [src]);

    if (!src || state === "failed") return fallback;
    return (
        <canvas
            ref={canvasRef}
            role="img"
            aria-label={alt || ""}
            className={`${className} transition-opacity duration-300 ${state === "ready" ? "opacity-100" : "opacity-0"}`}
        />
    );
};

const SelectedBadge = () => (
    <div className="absolute z-20 flex items-center justify-center w-5 h-5 text-xs text-white bg-gray-900 rounded-full top-1.5 right-1.5 lg:top-2 lg:right-2 animate-pop">
        ✓
    </div>
);

const TileSpinner = () => (
    <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/60 backdrop-blur-[1px]">
        <Loader2 className="w-5 h-5 text-gray-900 animate-spin" />
    </div>
);

/*
 * Tiles live in a horizontally scrolling strip below `lg` (the panel is a
 * short bottom sheet there) and in a grid on larger screens. `onHover`
 * doubles as a pre-click prefetch: mouse enter on desktop, pointer down
 * on touch, so the click that follows is usually instant.
 */
const TILE_BASE =
    "relative shrink-0 snap-start p-2 lg:p-3 rounded-lg transition-all duration-300 ease-out " +
    "focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/40";
const TILE_W = "w-[6.75rem] sm:w-[7.5rem] lg:w-auto";
const TILE_W_WIDE = "w-[8.5rem] sm:w-[9.5rem] lg:w-auto";

const tileClass = (isSelected, width = TILE_W) =>
    `${TILE_BASE} ${width} ${isSelected ? "shadow-md scale-[1.02] ring-1 ring-gray-900/80" : "hover:shadow-md hover:scale-[1.02] active:scale-[0.98]"}`;

/* A fabric swatch is the card: the cloth runs edge to edge, its name and price sit underneath. */
const FABRIC_TILE_BASE =
    "relative w-full transition-transform duration-300 ease-out " +
    "focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/40 focus-visible:rounded-lg";

const fabricTileClass = (isSelected) => `${FABRIC_TILE_BASE} ${isSelected ? "" : "hover:-translate-y-0.5 active:scale-[0.99]"}`;

const hoverHandlers = (onHover) => (onHover ? { onMouseEnter: onHover, onFocus: onHover, onPointerDown: onHover } : {});

/*
 * Swatch with the name set in script over the cloth. On hover (or focus) the
 * script fades to a plain name and a "more info" link, like a label turning
 * over. `onInfo` opens the fabric overlay; tiles without it (linings) keep
 * the caption underneath.
 */
const FabricOptionTile = memo(function FabricOptionTile({ isSelected, onClick, onHover, onInfo, image, label, price, isNew = false, isLoading }) {
    const overlay = Boolean(onInfo);
    return (
        <div className={`${overlay ? fabricTileClass(isSelected) : tileClass(isSelected, "w-full")} group`} {...hoverHandlers(onHover)}>
            {isSelected && !overlay && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <button type="button" onClick={onClick} aria-pressed={isSelected} aria-label={label} className="block w-full focus:outline-none">
                <div
                    className={`relative w-full flex items-center justify-center bg-[#efece6] overflow-hidden ${
                        overlay
                            ? `aspect-[40/27] rounded-lg ${isSelected ? "ring-1 ring-gray-900 ring-offset-2 ring-offset-white" : ""}`
                            : "aspect-[4/3] rounded-md"
                    }`}
                >
                    <SwatchImage
                        src={thumbUrl(image)}
                        alt=""
                        className="object-cover w-full h-full"
                        fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{label || "No image"}</div>}
                    />
                    {overlay && isNew && (
                        <span className="absolute top-0 left-0 px-2 py-0.5 text-[9px] font-bold tracking-widest text-white uppercase bg-red-600 rounded-br-md">
                            New
                        </span>
                    )}
                </div>
                {overlay ? (
                    <div className="flex items-baseline justify-between gap-2 mt-2">
                        <span className="text-sm font-semibold leading-tight text-gray-900 truncate">{label}</span>
                        {price != null && <span className="text-sm text-gray-700 shrink-0">${Math.round(Number(price))}</span>}
                    </div>
                ) : (
                    <div className="mt-2">
                        <div className="text-[11px] lg:text-xs font-medium leading-tight text-center text-gray-700 line-clamp-2 lg:line-clamp-none">{label}</div>
                        {price && <div className="text-[11px] lg:text-xs text-center text-gray-500 mt-0.5">${price}</div>}
                    </div>
                )}
            </button>
            {/*
             * The chosen cloth names itself: veil, script name and "more info" sit over
             * the swatch only while selected, which is also what a tap produces on touch,
             * where hover never fires.
             */}
            {overlay && isSelected && (
                <div className="absolute inset-x-0 top-0 aspect-[40/27] flex flex-col items-center justify-center gap-1 rounded-lg pointer-events-none bg-black/55 animate-fade-in">
                    <span className="px-2 text-center text-white font-script text-[26px] leading-none">{label}</span>
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); onInfo(); }}
                        className="text-[11px] font-medium text-white/90 pointer-events-auto hover:text-white"
                    >
                        more info
                    </button>
                </div>
            )}
        </div>
    );
});

/* ------------------------------------------------------------------ */
/*  Fabric overlay — pictures, badges, and the info card                */
/* ------------------------------------------------------------------ */

/* Large reading panel for a detail's bracketed note: "Weave" / "Twill" / the explanation. */
const NotePanel = ({ note, onClose }) => {
    useEffect(() => {
        const onKey = (e) => e.key === "Escape" && (e.stopPropagation(), onClose());
        window.addEventListener("keydown", onKey, true);
        return () => window.removeEventListener("keydown", onKey, true);
    }, [onClose]);

    return (
        <div className="absolute inset-0 z-30 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div role="dialog" aria-modal="true" aria-labelledby="note-title" className="relative w-full max-w-2xl p-8 bg-white shadow-2xl rounded-2xl sm:p-10 animate-pop">
                <button type="button" onClick={onClose} aria-label="Close" className="absolute p-2 text-gray-500 transition-colors rounded-full top-4 right-4 hover:bg-gray-100 hover:text-gray-900">
                    <X className="w-5 h-5" strokeWidth={1.5} />
                </button>
                <p id="note-title" className="text-2xl font-light tracking-tight text-gray-900 sm:text-3xl">{note.label}</p>
                <p className="mt-4 text-xl font-medium tracking-tight text-gray-900">{note.value}</p>
                <p className="mt-4 text-[15px] leading-relaxed text-gray-600 whitespace-pre-line">{note.note}</p>
            </div>
        </div>
    );
};

const FabricOverlay = ({ fabric, fabrics, onClose, onNavigate, onChoose }) => {
    const [kind, setKind] = useState("preview");
    const [index, setIndex] = useState(0);
    const [details, setDetails] = useState(false);
    const [note, setNote] = useState(null);

    const previews = fabric.preview_images || [];
    const realLife = (fabric.real_life_images || []).map((p) => (typeof p === "string" ? { url: p, caption: null } : p));
    const pictures = previews;
    const info = fabric.info || null;
    const badges = info?.badges || [];
    const columns = (info?.columns || []).filter((c) => c.length > 0);
    const hasDetails = Boolean(info?.description) || columns.length > 0;
    const position = fabrics.findIndex((f) => f.id === fabric.id);

    useEffect(() => { setIndex(0); setKind("preview"); setDetails(false); setNote(null); }, [fabric.id]);
    useEffect(() => {
        const onKey = (e) => {
            if (e.key === "Escape") onClose();
            if (!pictures.length) return;
            if (e.key === "ArrowRight") setIndex((i) => (i + 1) % pictures.length);
            if (e.key === "ArrowLeft") setIndex((i) => (i - 1 + pictures.length) % pictures.length);
        };
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [pictures.length, onClose]);

    const step = (dir) => pictures.length && setIndex((i) => (i + dir + pictures.length) % pictures.length);

    return (
        <div role="dialog" aria-label={`${fabric.name} fabric`} className="fixed inset-0 z-[40] bg-[#0b0b0b] animate-fade-in lg:absolute" onClick={(e) => e.target === e.currentTarget && onClose()}>
            {/* Picture */}
            <div className="absolute inset-0">
                {pictures.map((src, i) => (
                    <img
                        key={src}
                        src={hiresUrl(src)}
                        alt=""
                        className={`absolute inset-0 w-full h-full object-cover transition-opacity duration-500 ${i === index ? "opacity-100" : "opacity-0"}`}
                        draggable={false}
                    />
                ))}
                <div className="absolute inset-0 bg-gradient-to-b from-black/30 via-transparent to-black/40 pointer-events-none" />
                {pictures.length === 0 && (
                    <p className="absolute inset-x-0 top-[28%] text-center text-[11px] font-semibold tracking-[0.24em] text-white/30 uppercase pointer-events-none">
                        No preview pictures yet
                    </p>
                )}
            </div>

            {/* Chrome */}
            <button type="button" onClick={onClose} aria-label="Close" className="absolute z-10 flex items-center justify-center w-11 h-11 text-white transition-colors rounded-full top-4 right-4 hover:bg-white/15">
                <X className="w-6 h-6" strokeWidth={1.5} />
            </button>
            {pictures.length > 1 && (
                <>
                    <button type="button" onClick={() => step(-1)} aria-label="Previous picture" className="absolute z-10 flex items-center justify-center w-11 h-11 text-white -translate-y-1/2 border rounded-full left-4 top-[38%] border-white/60 bg-black/20 backdrop-blur hover:bg-white hover:text-gray-900 transition-colors">
                        <ArrowLeft className="w-4 h-4" />
                    </button>
                    <button type="button" onClick={() => step(1)} aria-label="Next picture" className="absolute z-10 flex items-center justify-center w-11 h-11 text-white -translate-y-1/2 border rounded-full right-4 top-[38%] border-white/60 bg-black/20 backdrop-blur hover:bg-white hover:text-gray-900 transition-colors">
                        <ArrowRight className="w-4 h-4" />
                    </button>
                    <div className="absolute z-10 flex gap-1.5 -translate-x-1/2 left-1/2 top-4">
                        {pictures.map((_, i) => (
                            <button key={i} type="button" onClick={() => setIndex(i)} aria-label={`Picture ${i + 1}`} className={`h-1 rounded-full transition-all ${i === index ? "w-6 bg-white" : "w-2 bg-white/50"}`} />
                        ))}
                    </div>
                </>
            )}
            {fabrics.length > 1 && (
                <div className="absolute z-10 flex items-center gap-2 text-white/80 top-5 left-5 text-[11px] font-semibold tracking-[0.2em] uppercase">
                    <button type="button" onClick={() => onNavigate(-1)} className="hover:text-white">‹</button>
                    <span>{position + 1} / {fabrics.length}</span>
                    <button type="button" onClick={() => onNavigate(1)} className="hover:text-white">›</button>
                </div>
            )}

            {note && <NotePanel note={note} onClose={() => setNote(null)} />}

            {/* Card */}
            <div className="absolute inset-x-0 bottom-0 z-10 flex justify-center px-3 pb-3 sm:px-6 sm:pb-6 pointer-events-none">
                <div className="w-full max-w-4xl max-h-[62vh] overflow-y-auto thin-scrollbar bg-white rounded-2xl shadow-2xl pointer-events-auto animate-slide-up p-6 sm:p-8">
                    <div className="flex items-start justify-between gap-6">
                        <div className="min-w-0">
                            <h2 className="text-2xl font-medium tracking-tight sm:text-3xl">{kind === "real_life" ? fabric.name : info?.title || fabric.name}<span className="text-gray-400">.</span></h2>
                            {kind !== "real_life" && details && info?.description && <p className="mt-3 text-[15px] leading-relaxed text-gray-600 animate-fade-in">{info.description}</p>}
                        </div>
                        <div className="flex gap-5 shrink-0 text-[11px] text-gray-500">
                            {hasDetails && (
                                <button type="button" onClick={() => { if (kind === "real_life") { setKind("preview"); setDetails(true); } else setDetails((v) => !v); }} className={`flex flex-col items-center gap-1 w-14 transition-colors hover:text-gray-900 ${details && kind !== "real_life" ? "text-gray-900 font-medium" : ""}`}>
                                    <Info className="w-5 h-5" strokeWidth={1.5} /> {details && kind !== "real_life" ? "hide details" : "More info"}
                                </button>
                            )}
                            {realLife.length > 0 && (
                                <button type="button" onClick={() => { setKind((k) => (k === "real_life" ? "preview" : "real_life")); }} className={`flex flex-col items-center gap-1 w-14 transition-colors hover:text-gray-900 ${kind === "real_life" ? "text-gray-900 font-medium" : ""}`}>
                                    <Images className="w-5 h-5" strokeWidth={1.5} /> Real life pictures
                                </button>
                            )}
                        </div>
                    </div>

                    {kind === "real_life" && (
                        <div className="mt-6 overflow-x-auto overscroll-x-contain snap-x snap-mandatory thin-scrollbar-x animate-fade-in">
                            <ul className="flex gap-5 pb-4 w-max">
                                {realLife.map((picture, i) => (
                                    <li key={i} className="w-40 shrink-0 snap-start sm:w-44">
                                        <div className="relative overflow-hidden aspect-[3/4] bg-[#efece6]">
                                            <img src={thumbUrl(picture.url)} alt={picture.caption || ""} loading="lazy" className="absolute inset-0 object-cover w-full h-full transition-transform duration-700 hover:scale-105" />
                                        </div>
                                        {picture.caption && <p className="mt-3 text-sm text-gray-600">{picture.caption}</p>}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {kind !== "real_life" && badges.length > 0 && (
                        <ul className="flex flex-wrap justify-start gap-x-8 gap-y-5 mt-7 sm:gap-x-10">
                            {badges.map((badge, i) => (
                                <li key={i} className="flex flex-col items-center gap-2 text-center w-20 group/badge">
                                    <span className="flex items-center justify-center w-12 h-12 transition-transform duration-300 group-hover/badge:-translate-y-0.5">
                                        {badge.icon && <img src={thumbUrl(badge.icon)} alt="" className="object-contain w-10 h-10" style={{ mixBlendMode: "multiply" }} loading="lazy" />}
                                    </span>
                                    <span className="text-xs text-gray-700 sm:text-[13px] leading-tight">{badge.name}</span>
                                </li>
                            ))}
                        </ul>
                    )}

                    {kind !== "real_life" && details && columns.length > 0 && (
                        <div className="grid gap-x-8 gap-y-6 pt-6 mt-8 text-sm border-t border-gray-100 sm:grid-cols-2 lg:grid-cols-4 animate-fade-in">
                            {columns.map((entries, c) => (
                                <dl key={c} className="space-y-2.5">
                                    {entries.map((entry, i) => (
                                        <div key={i}>
                                            <dt className="inline font-semibold text-gray-900">{entry.label}: </dt>
                                            <dd className="inline text-gray-600">
                                                {entry.value}
                                                {entry.note && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setNote(entry)}
                                                        aria-label={`More about ${entry.label}`}
                                                        className="inline-flex items-center justify-center w-4 h-4 ml-1.5 text-[10px] font-bold leading-none text-white align-middle bg-gray-900 rounded-full transition-transform hover:scale-110"
                                                    >
                                                        i
                                                    </button>
                                                )}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            ))}
                        </div>
                    )}

                    {kind !== "real_life" && (
                    <div className="flex flex-wrap items-center justify-between gap-3 mt-8">
                        <span className="text-sm text-gray-500">From ${Math.round(Number(fabric.price) || 0)} · tailored in about 3 weeks</span>
                        <button type="button" onClick={() => { onChoose(fabric); onClose(); }} className="btn-ink bg-gray-900 text-white hover:bg-gray-700">
                            Design in {fabric.name} <ArrowRight className="w-4 h-4" />
                        </button>
                    </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const StyleOptionTile = memo(function StyleOptionTile({ isSelected, onClick, onHover, image, label, aspect = "aspect-[3/4]", isLoading }) {
    return (
        <button
            type="button"
            onClick={onClick}
            {...hoverHandlers(onHover)}
            className={`${TILE_BASE} ${TILE_W} ${isSelected ? "bg-transparent scale-[1.03]" : "hover:scale-[1.03] active:scale-[0.98]"}`}
            aria-pressed={isSelected}
        >
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className={`w-full ${aspect} flex items-center justify-center bg-transparent rounded-md overflow-hidden`}>
                <LazyImage
                    src={thumbUrl(image)}
                    alt={label}
                    className="object-contain w-full h-full p-1 lg:p-2"
                    style={{ mixBlendMode: "multiply" }}
                    fallback={<div className="flex items-center justify-center w-full h-full p-2 text-xs text-center text-gray-400">{label}</div>}
                />
            </div>
            <div className="mt-1.5 lg:mt-2">
                <div className="text-[11px] lg:text-xs font-medium leading-tight text-center text-gray-700 line-clamp-2 lg:line-clamp-none">{label}</div>
            </div>
        </button>
    );
});

const LiningOptionTile = memo(function LiningOptionTile({ isSelected, onClick, image, label, isLoading }) {
    return (
        <button type="button" onClick={onClick} className={tileClass(isSelected, TILE_W_WIDE)} aria-pressed={isSelected}>
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className="w-full aspect-[4/3] flex items-center justify-center bg-transparent rounded-md overflow-hidden">
                <LazyImage
                    src={thumbUrl(image)}
                    alt={label}
                    className="object-contain w-full h-full p-1 lg:p-2"
                    style={{ mixBlendMode: "multiply" }}
                    fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{label}</div>}
                />
            </div>
            <div className="mt-1.5 lg:mt-2">
                <div className="text-[11px] lg:text-xs font-medium leading-tight text-center text-gray-700 line-clamp-2 lg:line-clamp-none">{label}</div>
            </div>
        </button>
    );
});

const ButtonOptionTile = memo(function ButtonOptionTile({ isSelected, onClick, onHover, image, label, isLoading, isDefault = false }) {
    return (
        <button type="button" onClick={onClick} {...hoverHandlers(onHover)} className={tileClass(isSelected)} aria-pressed={isSelected}>
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className="flex items-center justify-center w-full overflow-hidden rounded-md aspect-square">
                {isDefault ? (
                    <div className="flex flex-col items-center justify-center w-full h-full p-2">
                        <div className="flex items-center justify-center w-10 h-10 mb-1 border-4 border-gray-900 rounded-full lg:w-12 lg:h-12">
                            <span className="text-xs text-gray-400">−</span>
                        </div>
                    </div>
                ) : (
                    <LazyImage
                        src={thumbUrl(image)}
                        alt={label}
                        className="object-contain w-full h-full p-1 lg:p-2"
                        style={{ mixBlendMode: "multiply" }}
                        fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{label}</div>}
                    />
                )}
            </div>
            <div className="mt-1.5 lg:mt-2">
                <div className="text-[11px] lg:text-xs font-medium leading-tight text-center text-gray-700 line-clamp-2 lg:line-clamp-none">{label}</div>
            </div>
        </button>
    );
});

const SectionHeader = ({ title }) => (
    <div className="px-4 pt-4 pb-2 lg:px-5 lg:pt-6 lg:pb-3">
        <span className="text-[11px] lg:text-xs font-bold tracking-wider text-gray-400 uppercase">{title}</span>
    </div>
);

/* Strip on small screens, grid on lg+. */
const OptionRow = ({ children, cols = 3, className = "" }) => (
    <div
        className={`flex gap-2 px-4 pb-2 overflow-x-auto overscroll-x-contain snap-x snap-proximity no-scrollbar after:block after:shrink-0 after:w-2 lg:after:hidden lg:grid lg:gap-3 lg:px-5 lg:pb-0 lg:overflow-visible ${
            cols === 2 ? "lg:grid-cols-2" : "lg:grid-cols-3"
        } ${className}`}
    >
        {children}
    </div>
);

const RailTab = ({ id, icon: Icon, label, isActive, onSelect }) => (
    <button
        type="button"
        onClick={() => onSelect(id)}
        aria-current={isActive ? "page" : undefined}
        className="flex flex-col items-center justify-center flex-1 lg:flex-none gap-1 lg:gap-1.5 py-1.5 lg:py-3 group w-full transition-transform duration-200 lg:hover:scale-105 focus:outline-none"
    >
        <div
            className={`flex items-center justify-center w-9 h-9 lg:w-11 lg:h-11 rounded-full border-2 transition-all duration-300 ${
                isActive
                    ? "bg-gray-900 border-gray-900 text-white shadow-md lg:scale-110"
                    : "bg-white border-gray-200 text-gray-400 group-hover:border-gray-400 group-hover:text-gray-600"
            }`}
        >
            <Icon className="w-4 h-4 lg:w-5 lg:h-5" />
        </div>
        <span className={`text-[10px] lg:text-[11px] font-semibold tracking-wide uppercase transition-colors duration-300 ${isActive ? "text-gray-900" : "text-gray-400"}`}>
            {label}
        </span>
    </button>
);

/* ------------------------------------------------------------------ */
/*  Suit stage                                                         */
/*                                                                     */
/*  All layers are composited onto ONE <canvas> in a single synchronous */
/*  pass from already-decoded images, so a fabric or option change     */
/*  lands in exactly one frame — never a half-drawn suit. The canvas   */
/*  sizes itself to the largest box of the render's aspect that fits   */
/*  the available area, on any screen.                                 */
/* ------------------------------------------------------------------ */
function stageAspectOf(layers) {
    for (const layer of layers) {
        const bmp = bitmapCache.get(layer.image);
        if (bmp?.width && bmp?.height) return bmp.width / bmp.height;
        const el = imageCache.get(layer.image);
        if (el instanceof HTMLImageElement && el.naturalWidth && el.naturalHeight) return el.naturalWidth / el.naturalHeight;
    }
    return DEFAULT_STAGE_ASPECT;
}

/*
 * `zoom` > 1 draws the suit that many times larger than would fit, inside a
 * scrollable area that starts centred and can be dragged with the mouse
 * (touch scrolls natively). Used by the lightbox.
 */
/* `fill` > 1 draws the suit that much larger than fits; the renders carry generous transparent margins, so a little overscan reads as "bigger", not cropped. */
const SuitStage = memo(function SuitStage({ layers, dimmed, zoom = 1, fill = 1 }) {
    const wrapRef = useRef(null);
    const canvasRef = useRef(null);
    const dragRef = useRef(null);
    const [area, setArea] = useState({ w: 0, h: 0 });
    const [tick, setTick] = useState(0);

    const aspect = useMemo(() => stageAspectOf(layers), [layers]);

    const box = useMemo(() => {
        if (!area.w || !area.h) return { w: 0, h: 0 };
        const w = Math.min(area.w, area.h * aspect) * zoom * fill;
        return { w: Math.floor(w), h: Math.floor(w / aspect) };
    }, [area, aspect, zoom, fill]);

    /* Start a zoomed view centred on the suit. */
    useLayoutEffect(() => {
        const el = wrapRef.current;
        if (!el || zoom <= 1 || !box.w) return;
        el.scrollLeft = Math.max(0, (box.w - el.clientWidth) / 2);
        el.scrollTop = Math.max(0, (box.h - el.clientHeight) / 2);
    }, [zoom, box.w, box.h]);

    const onPointerDown = (e) => {
        if (zoom <= 1 || e.pointerType === "touch" || e.button !== 0) return;
        const el = wrapRef.current;
        dragRef.current = { x: e.clientX, y: e.clientY, left: el.scrollLeft, top: el.scrollTop };
        el.setPointerCapture?.(e.pointerId);
        el.classList.add("cursor-grabbing");
    };
    const onPointerMove = (e) => {
        const d = dragRef.current;
        if (!d) return;
        const el = wrapRef.current;
        el.scrollLeft = d.left - (e.clientX - d.x);
        el.scrollTop = d.top - (e.clientY - d.y);
    };
    const onPointerUp = (e) => {
        if (!dragRef.current) return;
        dragRef.current = null;
        const el = wrapRef.current;
        el.releasePointerCapture?.(e.pointerId);
        el.classList.remove("cursor-grabbing");
    };

    /* Track the available area. */
    useLayoutEffect(() => {
        const el = wrapRef.current;
        if (!el) return undefined;

        const measure = () => {
            const rect = el.getBoundingClientRect();
            setArea((prev) => (prev.w === rect.width && prev.h === rect.height ? prev : { w: rect.width, h: rect.height }));
        };
        measure();

        if (typeof ResizeObserver !== "undefined") {
            const ro = new ResizeObserver(measure);
            ro.observe(el);
            return () => ro.disconnect();
        }
        window.addEventListener("resize", measure);
        return () => window.removeEventListener("resize", measure);
    }, []);

    /* Composite. Runs before paint, so the swap is atomic. */
    useLayoutEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || !box.w || !box.h) return undefined;

        // Never allocate more canvas pixels than the sharpest layer can fill.
        const sourceW = layers.reduce((m, l) => Math.max(m, bitmapCache.get(l.image)?.width || 0), 0) || HIRES_WIDTH;
        const dpr = Math.min(window.devicePixelRatio || 1, 2, sourceW / box.w);
        const W = Math.round(box.w * dpr);
        const H = Math.round(box.h * dpr);
        if (canvas.width !== W || canvas.height !== H) {
            canvas.width = W;
            canvas.height = H;
        }

        const ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, W, H);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = "medium";

        const missing = [];
        for (const layer of layers) {
            const bmp = bitmapCache.get(layer.image);
            const cached = bmp ? null : imageCache.get(layer.image);
            const src = bmp || (cached instanceof HTMLImageElement ? cached : null);
            if (!src) {
                if (!imageCache.failed.has(layer.image)) missing.push(layer.image);
                continue;
            }
            const sw = bmp ? bmp.width : src.naturalWidth;
            const sh = bmp ? bmp.height : src.naturalHeight;
            // object-fit: contain
            const s = Math.min(W / sw, H / sh);
            const dw = sw * s;
            const dh = sh * s;
            ctx.drawImage(src, (W - dw) / 2, (H - dh) / 2, dw, dh);
        }

        // Only reachable after the commit timeout let a slow layer through: redraw once it lands.
        if (missing.length === 0) return undefined;
        let alive = true;
        bitmapCache.makeAll(missing).then(() => alive && setTick((t) => t + 1));
        return () => { alive = false; };
    }, [layers, box, tick]);

    if (zoom > 1) {
        return (
            <div
                ref={wrapRef}
                className="relative w-full h-full overflow-auto overscroll-contain no-scrollbar cursor-grab touch-pan-x touch-pan-y"
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={onPointerUp}
            >
                <div style={{ width: box.w || undefined, height: box.h || undefined }}>
                    <canvas
                        ref={canvasRef}
                        role="img"
                        aria-label="Your suit, zoomed in"
                        className={`stage-canvas block select-none transition-opacity duration-300 ease-out ${dimmed ? "opacity-60" : "opacity-100"}`}
                        style={{ width: box.w || undefined, height: box.h || undefined }}
                    />
                </div>
            </div>
        );
    }

    return (
        <div ref={wrapRef} className="relative w-full h-full">
            <canvas
                ref={canvasRef}
                role="img"
                aria-label="Your suit"
                className={`stage-canvas absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 select-none transition-opacity duration-300 ease-out ${
                    dimmed ? "opacity-60" : "opacity-100"
                }`}
                style={{ width: box.w || undefined, height: box.h || undefined }}
            />
        </div>
    );
});

const PENDING_LABELS = {
    fabric: "Changing fabric",
    body: "Updating body",
    lapelCategory: "Updating lapel",
    lapel: "Updating lapel",
    sleeve: "Updating sleeves",
    sidePocket: "Updating pockets",
    chestPocket: "Updating pocket",
    button: "Updating buttons",
    lining: "Updating lining",
    reset: "Resetting design",
};

const TAB_COPY = {
    fabric: { title: "Fabric" },
    style: { title: "Style" },
    accents: { title: "Accents & lining" },
};

/* ------------------------------------------------------------------ */
/*  Loading screen                                                     */
/*                                                                     */
/*  Shown while the catalogue downloads (stage 0) and while the first  */
/*  look's layers are decoded (stage 1). A hairline progress bar       */
/*  creeps forward per stage and completes when the designer mounts.   */
/* ------------------------------------------------------------------ */
const LOADING_STEPS = ["Opening the atelier", "Preparing your suit"];

const LoadingScreen = ({ stage }) => (
    <div className="fixed inset-0 z-[950] flex flex-col bg-[#f7f6f3] text-gray-900 animate-fade-in" role="status" aria-live="polite">
        <div className="loading-hairline" aria-hidden="true">
            <span style={{ transform: `scaleX(${stage === 0 ? 0.35 : 0.8})` }} />
        </div>

        <div className="flex flex-col items-center justify-center flex-1 px-6 text-center">
            <p className="text-[11px] font-semibold tracking-[0.28em] text-gray-400 uppercase animate-slide-up">Made to measure</p>
            <p className="mt-4 text-4xl font-light tracking-tight sm:text-5xl animate-slide-up [animation-delay:80ms]">Custom Tailor</p>

            <div className="relative w-56 h-px mt-10 overflow-hidden bg-gray-200 animate-slide-up [animation-delay:160ms]" aria-hidden="true">
                <span className="loading-sweep" />
            </div>

            <div className="relative h-6 mt-6 text-sm text-gray-500 animate-slide-up [animation-delay:240ms]">
                {LOADING_STEPS.map((label, i) => (
                    <span
                        key={label}
                        className={`absolute inset-x-0 transition-all duration-500 ${
                            i === stage ? "opacity-100 translate-y-0" : "opacity-0 " + (i < stage ? "-translate-y-2" : "translate-y-2")
                        }`}
                    >
                        {label}…
                    </span>
                ))}
            </div>
        </div>

        <p className="pb-8 text-[11px] tracking-wide text-center text-gray-400">Every fabric, every detail — rendered for you</p>
    </div>
);

/* ------------------------------------------------------------------ */
/*  Suit designer                                                      */
/* ------------------------------------------------------------------ */
const SuitDesigner = () => {
    const [fabrics, setFabrics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // The committed, fully-cached selection currently on the canvas.
    const [selection, setSelection] = useState(null);
    // { kind, id } while a change is being prepared (images loading).
    const [pending, setPending] = useState(null);
    const [notice, setNotice] = useState(null);
    const [bagNotice, setBagNotice] = useState(false);
    const bagTimerRef = useRef(null);
    const bagItems = useCart();

    const [activeTab, setActiveTab] = useState("fabric");
    const [showLiningPanel, setShowLiningPanel] = useState(false);
    const [showLightbox, setShowLightbox] = useState(false);
    const [infoFabricId, setInfoFabricId] = useState(null);
    // Below lg the options live in a slide-over drawer, so the suit gets the whole screen.
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [isWide, setIsWide] = useState(() => typeof window !== "undefined" && window.matchMedia("(min-width: 1024px)").matches);
    useEffect(() => {
        const mq = window.matchMedia("(min-width: 1024px)");
        const onChange = (e) => setIsWide(e.matches);
        mq.addEventListener("change", onChange);
        return () => mq.removeEventListener("change", onChange);
    }, []);
    const [stageNotice, setStageNotice] = useState(null);
    const stageNoticeRef = useRef(null);
    // true once the lightbox's sharper layers are decoded; until then it shows the on-screen ones, enlarged.
    const [hiresReady, setHiresReady] = useState(false);

    const intentRef = useRef(EMPTY_INTENT);
    const targetFabricRef = useRef(null);
    const commitTokenRef = useRef(0);
    const noticeTimerRef = useRef(null);
    const panelScrollRef = useRef(null);

    /* ---------- commit: prepare images, then swap the whole view at once ---------- */
    const commit = useCallback(async (next, pendingInfo, { adjustments = [] } = {}) => {
        const token = ++commitTokenRef.current;
        const urls = layerUrls(next);

        if (!bitmapCache.allReady(urls)) {
            // Network needed -> say so right away. Decode only -> only if it runs long enough to notice.
            let grace = null;
            if (!imageCache.allSettled(urls)) setPending(pendingInfo);
            else grace = setTimeout(() => token === commitTokenRef.current && setPending(pendingInfo), 120);

            await Promise.race([
                bitmapCache.makeAll(urls),
                new Promise((resolve) => setTimeout(resolve, CANVAS_TIMEOUT_MS)),
            ]);
            if (grace) clearTimeout(grace);
        }

        if (token !== commitTokenRef.current) return; // superseded by a newer change

        intentRef.current = completeIntent(intentRef.current, next);
        saveDesign(next.fabric.id, intentRef.current);

        bitmapCache.pin(urls);
        setSelection(next);
        setPending(null);

        if (noticeTimerRef.current) clearTimeout(noticeTimerRef.current);
        if (adjustments.length > 0) {
            setNotice({ fabric: next.fabric.name, items: adjustments });
            noticeTimerRef.current = setTimeout(() => setNotice(null), 4500);
        } else {
            setNotice(null);
        }
    }, []);

    /* ---------- initial fetch ---------- */
    const fetchSuitData = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            const response = await fetch("/api/configurator", { headers: { Accept: "application/json" } });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const payload = await response.json();
            const list = payload?.success ? payload.data || [] : [];
            setFabrics(list);

            if (list.length > 0) {
                const saved = loadSavedDesign();
                const linked = Number(new URLSearchParams(window.location.search).get("fabric"));
                const fabric =
                    (linked && list.find((f) => f.id === linked)) ||
                    (saved?.fabricId != null && list.find((f) => f.id === saved.fabricId)) ||
                    list.find((f) => f.is_default) ||
                    list[0];

                intentRef.current = saved?.intent || EMPTY_INTENT;
                targetFabricRef.current = fabric;
                await commit(resolveSelection(fabric, intentRef.current), { kind: "initial" });
            }
        } catch (err) {
            console.error("Error fetching suit data:", err);
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [commit]);

    useEffect(() => {
        fetchSuitData();
    }, [fetchSuitData]);

    /* ---------- background warm-up after every commit ---------- */
    useEffect(() => {
        if (!selection || !fabrics) return undefined;

        const handle = runWhenIdle(() => {
            imageCache.clearQueue();
            const intent = intentRef.current;
            const others = fabrics.filter((f) => f.id !== selection.fabric.id);
            // What every other fabric would look like with this exact design -> fabric switches feel instant.
            const otherLooks = () => others.forEach((f) => imageCache.prefetch(layerUrls(resolveSelection(f, intent))));
            // Everything the user can click on this fabric -> option changes feel instant.
            const thisOptions = () => imageCache.prefetch(optionUrlsFor(selection));
            // Whichever the open tab makes the likelier next click goes first.
            if (activeTab === "fabric") { otherLooks(); thisOptions(); } else { thisOptions(); otherLooks(); }
            // Then every option on every other fabric, so nothing buffers after the first minute.
            others.forEach((f) => imageCache.prefetch(optionUrlsFor(resolveSelection(f, intent))));
        });

        return () => cancelIdle(handle);
    }, [selection, fabrics, activeTab]);

    useEffect(() => () => noticeTimerRef.current && clearTimeout(noticeTimerRef.current), []);

    /* Leaving the tab takes the lining panel with it; the panel starts at the top. */
    useEffect(() => {
        setShowLiningPanel(false);
        panelScrollRef.current?.scrollTo({ top: 0 });
    }, [activeTab]);

    /* On a phone a tab opens the drawer; tapping the tab already open closes it again. */
    const selectTab = (id) => {
        setDrawerOpen((open) => (isWide ? false : !(open && id === activeTab)));
        setActiveTab(id);
    };

    /* Close the lightbox / lining panel / drawer on Escape, outermost first. */
    useEffect(() => {
        if (!showLiningPanel && !showLightbox && !drawerOpen) return undefined;
        const onKey = (e) => {
            if (e.key !== "Escape") return;
            if (showLightbox) setShowLightbox(false);
            else if (showLiningPanel) setShowLiningPanel(false);
            else setDrawerOpen(false);
        };
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [showLiningPanel, showLightbox, drawerOpen]);

    /* ---------- change handlers ---------- */
    const changeFabric = useCallback(
        (fabric) => {
            if (!fabric || fabric.id === targetFabricRef.current?.id) return;
            targetFabricRef.current = fabric;
            const next = resolveSelection(fabric, intentRef.current);
            commit(next, { kind: "fabric", id: fabric.id }, { adjustments: describeAdjustments(intentRef.current, next) });
        },
        [commit]
    );

    const choose = useCallback(
        (kind, id, patch) => {
            const fabric = targetFabricRef.current;
            if (!fabric) return;
            intentRef.current = { ...intentRef.current, ...patch };
            commit(resolveSelection(fabric, intentRef.current), { kind, id });
        },
        [commit]
    );

    const handleBodyChange = (body) => choose("body", body.id, { body: bodyIntent(body) });

    const handleLapelCategoryChange = (category) =>
        choose("lapelCategory", category.id, {
            lapel: {
                id: null,
                categoryId: category.id,
                categoryName: category.name,
                // keep the chosen width when moving between categories
                subcategoryId: intentRef.current.lapel?.subcategoryId ?? null,
                subcategoryName: intentRef.current.lapel?.subcategoryName ?? null,
            },
        });

    const handleLapelSelect = (lapel) => choose("lapel", lapel.id, { lapel: lapelIntent(lapel) });
    const handleSleeveSelect = (sleeve) => choose("sleeve", sleeve.id, { sleeve: typedIntent(sleeve) });
    const handleSidePocketSelect = (pocket) => choose("sidePocket", pocket.id, { sidePocket: typedIntent(pocket) });
    const handleChestPocketSelect = (pocket) => choose("chestPocket", pocket.id, { chestPocket: typedIntent(pocket) });
    const handleButtonSelect = (button) => choose("button", button.id, { button: buttonIntent(button) });
    const handleDefaultButtonClick = () => choose("button", "default", { button: null });

    const handleDefaultLiningClick = () => {
        setShowLiningPanel(false);
        choose("lining", "default", { lining: liningIntent(null) });
    };

    const handleCustomLiningClick = () => {
        setShowLiningPanel(true);
        const linings = targetFabricRef.current?.custom_linings || [];
        imageCache.prefetch(linings.flatMap((l) => [layerUrl(l.image), thumbUrl(l.fabric?.image)]), { front: true });
    };

    /* The panel stays open so linings can be compared one after another. */
    const handleLiningSelect = (lining) => choose("lining", lining.id, { lining: liningIntent(lining) });

    const handleAddToBag = () => {
        if (!selection) return;
        addToCart({
            fabricId: selection.fabric.id,
            fabricName: selection.fabric.name,
            fabricImage: selection.fabric.image,
            price: Number(selection.fabric.price) || 0,
            design: intentRef.current,
            summary: summarizeDesign(intentRef.current),
        });
        if (bagTimerRef.current) clearTimeout(bagTimerRef.current);
        setBagNotice(true);
        bagTimerRef.current = setTimeout(() => setBagNotice(false), 4500);
    };

    const flashStage = (text) => {
        if (stageNoticeRef.current) clearTimeout(stageNoticeRef.current);
        setStageNotice(text);
        stageNoticeRef.current = setTimeout(() => setStageNotice(null), 3000);
    };

    const handleShare = async () => {
        const url = window.location.href;
        try {
            if (navigator.share) await navigator.share({ title: "My custom suit", url });
            else {
                await navigator.clipboard.writeText(url);
                flashStage("Link copied");
            }
        } catch {
            /* dismissed */
        }
    };

    const stepFabric = (dir) => {
        if (!fabrics?.length || !selection) return;
        const i = fabrics.findIndex((f) => f.id === selection.fabric.id);
        setInfoFabricId(null);
        changeFabric(fabrics[(i + dir + fabrics.length) % fabrics.length]);
    };

    const handleReset = () => {
        if (!fabrics?.length) return;
        clearSavedDesign();
        intentRef.current = EMPTY_INTENT;
        const fabric = fabrics.find((f) => f.is_default) || fabrics[0];
        targetFabricRef.current = fabric;
        setShowLiningPanel(false);
        commit(resolveSelection(fabric, EMPTY_INTENT), { kind: "reset" });
    };

    /* Hover / touch-down = high-priority prefetch, so the click that follows is usually instant. */
    const prefetchFabric = useCallback((fabric) => {
        imageCache.prefetch(layerUrls(resolveSelection(fabric, intentRef.current)), { front: true });
    }, []);
    const prefetchImage = useCallback((url) => imageCache.prefetch([layerUrl(url)], { front: true }), []);

    /* ---------- derived ---------- */
    const layers = useMemo(() => layersFrom(selection), [selection]);
    const hiresLayers = useMemo(() => layers.map((l) => ({ ...l, image: hiresUrl(l.raw) })), [layers]);

    /* Lightbox: decode the sharper layers while it is open, then swap them in all at once. */
    useEffect(() => {
        if (!showLightbox) {
            setHiresReady(false);
            bitmapCache.pin(layers.map((l) => l.image));
            return undefined;
        }
        const urls = hiresLayers.map((l) => l.image);
        bitmapCache.pin([...layers.map((l) => l.image), ...urls]);
        if (bitmapCache.allReady(urls)) {
            setHiresReady(true);
            return undefined;
        }
        setHiresReady(false);
        let alive = true;
        bitmapCache.makeAll(urls).then(() => alive && setHiresReady(true));
        return () => { alive = false; };
    }, [showLightbox, layers, hiresLayers]);

    const lapelCategories = useMemo(() => {
        const lapels = selection?.body?.lapels;
        if (!lapels || lapels.length === 0) return [];
        const categories = new Map();
        lapels.forEach((lapel) => {
            const c = lapel.category;
            if (c?.id != null && !categories.has(c.id)) {
                categories.set(c.id, { id: c.id, name: c.name, diagram: c.diagram, is_default: Boolean(c.is_default) });
            }
        });
        return [...categories.values()];
    }, [selection?.body]);

    const filteredLapels = useMemo(() => {
        const categoryId = selection?.lapel?.category?.id;
        if (categoryId == null) return [];
        return (selection?.body?.lapels || []).filter((l) => l.category?.id === categoryId);
    }, [selection?.body, selection?.lapel]);

    const isPending = (kind, id) => pending?.kind === kind && pending.id === id;

    /* ---------- loading / error states ---------- */
    if (loading || (!error && fabrics?.length > 0 && !selection)) {
        return (
            <>
                <Head title="Design your suit" />
                <LoadingScreen stage={fabrics ? 1 : 0} />
            </>
        );
    }

    if (error) {
        return (
            <div className="flex items-center justify-center px-4 h-dvh bg-gray-50">
                <Head title="Design your suit" />
                <div className="w-full max-w-md p-6 text-center bg-white shadow-lg sm:p-8 rounded-xl animate-slide-up">
                    <div className="mb-4 text-5xl">⚠️</div>
                    <h2 className="mb-2 text-xl font-bold text-gray-800 sm:text-2xl">Error Loading Data</h2>
                    <p className="mb-4 text-sm text-gray-600 sm:text-base">{error}</p>
                    <button
                        type="button"
                        onClick={fetchSuitData}
                        className="px-6 py-2 text-white transition-all duration-200 bg-gray-900 rounded-lg hover:bg-gray-800 hover:shadow-md active:scale-95"
                    >
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    if (!selection) {
        return (
            <div className="flex items-center justify-center px-6 h-dvh bg-gray-50">
                <Head title="Design your suit" />
                <p className="text-gray-600">No data available</p>
            </div>
        );
    }

    const { fabric: selectedFabric, body: selectedBody, lapel: selectedLapel } = selection;
    const bodyButtons = selectedBody?.body_type?.body_buttons || [];
    const copy = TAB_COPY[activeTab];

    /* ---------- main render ---------- */
    return (
        <div className="flex flex-col overflow-hidden bg-[#f7f6f3] h-dvh lg:flex-row animate-fade-in">
            <Head title="Design your suit" />

            {/* STAGE SIDE — header, suit, and the summary column; on top below lg, on the right from lg */}
            <main className="relative flex flex-col flex-1 order-1 min-w-0 min-h-0 lg:order-2">
            <header className="absolute inset-x-0 top-0 z-30 flex items-center justify-between h-12 px-4 pointer-events-none lg:h-16 lg:px-8 [&>*]:pointer-events-auto">
                <Link href="/" className="flex items-center gap-3 text-gray-900" aria-label="Back to home">
                    <span className="text-[22px] font-semibold tracking-tight lg:text-[26px]">Custom Tailor</span>
                </Link>
                <div className="flex items-center gap-5 text-gray-900 lg:gap-7">
                    <button type="button" onClick={handleShare} title="Share" aria-label="Share this design" className="transition-opacity hover:opacity-60">
                        <Share2 className="w-5 h-5 lg:w-6 lg:h-6" strokeWidth={1.5} />
                    </button>
                    <button type="button" onClick={() => flashStage("Saved to this browser")} title="Saved" aria-label="Your design is saved" className="transition-opacity hover:opacity-60">
                        <Heart className="w-5 h-5 lg:w-6 lg:h-6" strokeWidth={1.5} />
                    </button>
                    <Link href={route("cart")} title="View bag" aria-label="View bag" className="relative transition-opacity hover:opacity-60">
                        <ShoppingBag className="w-5 h-5 lg:w-6 lg:h-6" strokeWidth={1.5} />
                        {cartCount(bagItems) > 0 && (
                            <span className="absolute -top-1 -right-1.5 flex items-center justify-center min-w-[1rem] h-4 px-1 text-[10px] font-semibold text-white bg-[#ff8a00] rounded-full">
                                {cartCount(bagItems)}
                            </span>
                        )}
                    </Link>
                </div>
            </header>

            {/* FABRIC OVERLAY — pictures and details for one cloth, over the whole right side */}
            {infoFabricId != null && fabrics.find((f) => f.id === infoFabricId) && (
                <FabricOverlay
                    fabric={fabrics.find((f) => f.id === infoFabricId)}
                    fabrics={fabrics}
                    onClose={() => setInfoFabricId(null)}
                    onNavigate={(dir) => {
                        const i = fabrics.findIndex((f) => f.id === infoFabricId);
                        setInfoFabricId(fabrics[(i + dir + fabrics.length) % fabrics.length].id);
                    }}
                    onChoose={(fabric) => { changeFabric(fabric); }}
                />
            )}

            <div className="relative flex flex-1 min-h-0 pt-12 lg:pt-0">
                <div className="relative flex-1 min-w-0 min-h-0 p-2 sm:p-3 lg:px-6 lg:pt-3 lg:pb-10">
                <SuitStage layers={layers} dimmed={Boolean(pending)} fill={isWide ? 1.14 : 1} />

                {fabrics.length > 1 && (
                    <>
                        <button type="button" onClick={() => stepFabric(-1)} title="Previous fabric" aria-label="Previous fabric" className="absolute z-30 items-center justify-center hidden w-9 h-9 text-gray-700 bg-white border border-gray-200 rounded-full shadow-sm lg:flex bottom-3 left-6 hover:bg-gray-900 hover:text-white hover:border-gray-900 transition-colors">
                            <ChevronLeft className="w-4 h-4" />
                        </button>
                        <button type="button" onClick={() => stepFabric(1)} title="Next fabric" aria-label="Next fabric" className="absolute z-30 items-center justify-center hidden w-9 h-9 text-gray-700 bg-white border border-gray-200 rounded-full shadow-sm lg:flex bottom-3 right-6 hover:bg-gray-900 hover:text-white hover:border-gray-900 transition-colors">
                            <ChevronRight className="w-4 h-4" />
                        </button>
                    </>
                )}

                {stageNotice && (
                    <div role="status" className="absolute z-30 px-4 py-2 text-sm text-white -translate-x-1/2 bg-gray-900 rounded-full shadow-lg top-3 left-1/2 animate-slide-down">
                        {stageNotice}
                    </div>
                )}

                <button
                    type="button"
                    onClick={() => setShowLightbox(true)}
                    title="View full screen"
                    aria-label="View full screen"
                    className="absolute z-30 flex items-center justify-center w-10 h-10 text-gray-700 transition-all duration-200 bg-white border border-gray-200 rounded-full shadow-md bottom-3 right-3 lg:hidden hover:bg-gray-900 hover:text-white hover:border-gray-900 active:scale-95"
                >
                    <Maximize2 className="w-4 h-4" />
                </button>

                {pending && (
                    <div className="absolute z-30 flex items-center gap-2 px-3 py-1.5 rounded-full shadow-lg top-3 right-3 lg:top-4 lg:right-4 bg-white/90 backdrop-blur-sm animate-slide-down">
                        <Loader2 className="w-3.5 h-3.5 text-gray-900 animate-spin" />
                        <span className="text-xs font-medium text-gray-700">{PENDING_LABELS[pending.kind] || "Updating"}…</span>
                    </div>
                )}

                {bagNotice && (
                    <div role="status" className="absolute z-30 flex items-center gap-3 px-4 py-3 text-white bg-gray-900 rounded-2xl shadow-xl top-3 left-3 lg:top-4 lg:left-4 animate-slide-down">
                        <span className="flex items-center justify-center w-6 h-6 rounded-full bg-white/15">
                            <Check className="w-3.5 h-3.5" />
                        </span>
                        <span className="text-sm">Added to your bag</span>
                        <Link href={route("cart")} className="ml-2 text-sm font-medium underline underline-offset-4 hover:no-underline">
                            View bag
                        </Link>
                    </div>
                )}

                {notice && (
                    <div
                        role="status"
                        className="absolute z-30 bottom-3 left-1/2 -translate-x-1/2 w-[calc(100%-1.5rem)] max-w-sm px-4 py-3 bg-gray-900/95 text-white rounded-xl shadow-xl backdrop-blur-sm animate-slide-up"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-xs font-semibold">Adjusted for {notice.fabric}</p>
                                <ul className="mt-1 space-y-0.5 text-[11px] text-gray-300">
                                    {notice.items.map((item) => (
                                        <li key={item}>{item}</li>
                                    ))}
                                </ul>
                            </div>
                            <button
                                type="button"
                                onClick={() => setNotice(null)}
                                aria-label="Dismiss"
                                className="p-1 -m-1 text-gray-400 transition-colors rounded hover:text-white"
                            >
                                <X className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                )}
                </div>

                {/* SUMMARY — desktop only */}
                <div className="relative flex-col items-end justify-center hidden w-64 pr-6 pl-2 text-right shrink-0 lg:flex xl:w-72 xl:pr-10">
                    <h1 className="text-4xl font-light leading-[1.08] tracking-tight text-gray-900 xl:text-5xl">
                        Your<br />Custom Suit
                    </h1>
                    <p className="mt-8 text-3xl font-light tracking-tight text-gray-900 xl:text-4xl tabular-nums">${Math.round(Number(selectedFabric?.price) || 0)}</p>
                    <p className="mt-1 text-xs text-gray-500">VAT incl.</p>
                    <button
                        type="button"
                        onClick={handleAddToBag}
                        disabled={Boolean(pending)}
                        className="w-full max-w-[11.5rem] py-3.5 mt-8 text-lg font-medium text-white transition-all duration-200 bg-[#ff8a00] rounded-full shadow-[0_10px_24px_-10px_rgba(255,138,0,0.7)] hover:bg-[#f07f00] active:scale-[0.98] disabled:opacity-60"
                    >
                        Add to cart
                    </button>
                    <p className="mt-8 text-[15px] text-gray-700">Order today, receive in 3 weeks.</p>
                    <p className="mt-4 text-[15px] font-semibold text-gray-900">Free shipping</p>

                    <button
                        type="button"
                        onClick={() => setShowLightbox(true)}
                        aria-label="Zoom"
                        className="absolute flex flex-col items-center gap-1 text-gray-700 bottom-4 right-8 xl:right-12 group"
                    >
                        <span className="flex items-center justify-center w-10 h-10 transition-colors bg-white border border-gray-300 rounded-full group-hover:bg-gray-900 group-hover:text-white group-hover:border-gray-900">
                            <Plus className="w-4 h-4" />
                        </span>
                        <span className="text-[10px] font-semibold tracking-[0.18em] uppercase">Zoom</span>
                    </button>
                </div>
            </div>

            {/*
             * Purchase bar — the phone counterpart of the desktop summary column.
             * It sits in the layout rather than floating, so it can never cover the
             * suit no matter how tall the render is.
             */}
            <div className="flex items-center justify-between gap-4 px-4 pt-1 pb-3 shrink-0 lg:hidden">
                <div className="min-w-0">
                    <p className="text-[10px] font-semibold tracking-[0.2em] text-gray-400 uppercase">Your custom suit</p>
                    <p className="flex items-baseline gap-2 mt-0.5">
                        <span className="text-xl font-light tracking-tight text-gray-900 tabular-nums">${Math.round(Number(selectedFabric?.price) || 0)}</span>
                        <span className="text-xs text-gray-500 truncate">{selectedFabric?.name}</span>
                    </p>
                </div>
                <button
                    type="button"
                    onClick={handleAddToBag}
                    disabled={Boolean(pending)}
                    className="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium text-white bg-[#ff8a00] rounded-full shrink-0 shadow-[0_8px_20px_-8px_rgba(255,138,0,0.8)] transition-all duration-200 hover:bg-[#f07f00] active:scale-[0.98] disabled:opacity-60"
                >
                    <ShoppingBag className="w-4 h-4" />
                    Add to cart
                </button>
            </div>
            </main>

            {/* MOBILE — tapping the backdrop (the sliver of suit still showing) closes the drawer */}
            {drawerOpen && (
                <div
                    className="fixed inset-0 z-40 bg-gray-900/30 lg:hidden animate-fade-in"
                    onClick={() => setDrawerOpen(false)}
                    aria-hidden="true"
                />
            )}

            {/* MOBILE — the tabs stay on screen; each one slides the drawer open */}
            <nav className="flex items-center bg-white border-t border-gray-100 shrink-0 order-3 pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Sections">
                <RailTab id="fabric" icon={Layers} label="Fabric" isActive={drawerOpen && activeTab === "fabric"} onSelect={selectTab} />
                <RailTab id="style" icon={Scissors} label="Style" isActive={drawerOpen && activeTab === "style"} onSelect={selectTab} />
                <RailTab id="accents" icon={Palette} label="Accents" isActive={drawerOpen && activeTab === "accents"} onSelect={selectTab} />
            </nav>

            {/* PANEL — slide-over drawer below lg; rail + options from lg */}
            <aside
                className={`flex flex-col bg-white shrink-0 order-2 max-lg:fixed max-lg:inset-y-0 max-lg:left-0 max-lg:z-50 max-lg:w-[86vw] max-lg:max-w-sm max-lg:shadow-2xl max-lg:transition-transform max-lg:duration-300 max-lg:ease-out ${
                    drawerOpen ? "max-lg:translate-x-0" : "max-lg:-translate-x-full"
                } lg:static lg:z-10 lg:order-1 lg:flex-row lg:h-full lg:translate-x-0 lg:border-r lg:border-gray-100 lg:shadow-lg ${
                    activeTab === "fabric" ? "lg:w-[34rem] xl:w-[38rem]" : "lg:w-[26rem] xl:w-[29rem]"
                }`}
                aria-label="Customization options"
                aria-hidden={!isWide && !drawerOpen}
            >
                {/* content */}
                <div className="relative flex flex-col flex-1 min-w-0 min-h-0">
                    <div className="flex items-center justify-between gap-3 px-4 py-2 border-b border-gray-100 lg:px-5 lg:py-3 shrink-0">
                        <h1 className="text-[11px] font-semibold tracking-[0.24em] text-gray-500 uppercase">
                            <span className="sr-only">Custom Tailor — </span>{copy.title}
                        </h1>
                        <div className="flex items-center gap-1 shrink-0 lg:gap-2">
                            <Link
                                href={route("cart")}
                                title="View bag"
                                aria-label="View bag"
                                className="relative p-2 text-gray-400 transition-all duration-200 rounded-lg hover:text-gray-700 hover:bg-gray-100 active:scale-95"
                            >
                                <ShoppingBag className="w-4 h-4" />
                                {cartCount(bagItems) > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 text-[10px] font-semibold text-white bg-gray-900 rounded-full">
                                        {cartCount(bagItems)}
                                    </span>
                                )}
                            </Link>
                            <button
                                type="button"
                                onClick={handleReset}
                                title="Reset design"
                                aria-label="Reset design"
                                className="p-2 text-gray-400 transition-all duration-200 rounded-lg hover:text-gray-700 hover:bg-gray-100 active:scale-95"
                            >
                                <RotateCcw className="w-4 h-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setDrawerOpen(false)}
                                title="Close"
                                aria-label="Close options"
                                className="p-2 text-gray-500 transition-all duration-200 rounded-lg lg:hidden hover:text-gray-900 hover:bg-gray-100 active:scale-95"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <div ref={panelScrollRef} className="flex-1 min-h-0 overflow-y-auto overscroll-y-contain thin-scrollbar">
                        {activeTab === "fabric" && (
                            <div key="fabric" className="p-4 lg:p-5 animate-slide-in-left">
                                <div className="grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 lg:gap-x-4 lg:gap-y-7">
                                    {fabrics.map((fabric) => (
                                        <FabricOptionTile
                                            key={fabric.id}
                                            isSelected={fabric.id === selectedFabric.id}
                                            onClick={() => { setInfoFabricId(null); changeFabric(fabric); }}
                                            onHover={() => prefetchFabric(fabric)}
                                            onInfo={() => setInfoFabricId(fabric.id)}
                                            image={fabric.image}
                                            label={fabric.name}
                                            price={fabric.price}
                                            isNew={fabric.is_new}
                                            isLoading={isPending("fabric", fabric.id)}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}

                        {activeTab === "style" && (
                            <div key="style" className="pb-4 lg:pb-5 animate-slide-in-left">
                                {selectedFabric.bodies?.length > 0 && (
                                    <>
                                        <SectionHeader title="Body Style" />
                                        <OptionRow>
                                            {selectedFabric.bodies.map((body) => (
                                                <StyleOptionTile
                                                    key={body.id}
                                                    isSelected={body.id === selectedBody?.id}
                                                    onClick={() => handleBodyChange(body)}
                                                    onHover={() => prefetchImage(body.image)}
                                                    image={body.body_type?.diagram || body.image}
                                                    label={body.body_type?.name || "Body"}
                                                    isLoading={isPending("body", body.id)}
                                                />
                                            ))}
                                        </OptionRow>
                                    </>
                                )}

                                {lapelCategories.length > 0 && (
                                    <>
                                        <SectionHeader title="Lapel Type" />
                                        <OptionRow>
                                            {lapelCategories.map((category) => (
                                                <StyleOptionTile
                                                    key={category.id}
                                                    isSelected={selectedLapel?.category?.id === category.id}
                                                    onClick={() => handleLapelCategoryChange(category)}
                                                    image={category.diagram}
                                                    label={category.name}
                                                    isLoading={isPending("lapelCategory", category.id)}
                                                />
                                            ))}
                                        </OptionRow>

                                        {filteredLapels.length > 0 && (
                                            <>
                                                <SectionHeader title={`${selectedLapel.category?.name ?? "Lapel"} Width`} />
                                                <OptionRow>
                                                    {filteredLapels.map((lapel) => (
                                                        <StyleOptionTile
                                                            key={lapel.id}
                                                            isSelected={lapel.id === selectedLapel?.id}
                                                            onClick={() => handleLapelSelect(lapel)}
                                                            onHover={() => prefetchImage(lapel.image)}
                                                            image={lapel.subcategory?.diagram || lapel.image}
                                                            label={lapel.subcategory?.name || "Width"}
                                                            isLoading={isPending("lapel", lapel.id)}
                                                        />
                                                    ))}
                                                </OptionRow>
                                            </>
                                        )}
                                    </>
                                )}

                                {selectedFabric.sleeves?.length > 0 && (
                                    <>
                                        <SectionHeader title="Sleeves" />
                                        <OptionRow>
                                            {selectedFabric.sleeves.map((sleeve) => (
                                                <StyleOptionTile
                                                    key={sleeve.id}
                                                    isSelected={sleeve.id === selection.sleeve?.id}
                                                    onClick={() => handleSleeveSelect(sleeve)}
                                                    onHover={() => prefetchImage(sleeve.image)}
                                                    image={sleeve.type?.diagram || sleeve.image}
                                                    label={sleeve.type?.name || "Sleeve"}
                                                    isLoading={isPending("sleeve", sleeve.id)}
                                                />
                                            ))}
                                        </OptionRow>
                                    </>
                                )}

                                {selectedFabric.side_pockets?.length > 0 && (
                                    <>
                                        <SectionHeader title="Side Pockets" />
                                        <OptionRow>
                                            {selectedFabric.side_pockets.map((pocket) => (
                                                <StyleOptionTile
                                                    key={pocket.id}
                                                    isSelected={pocket.id === selection.sidePocket?.id}
                                                    onClick={() => handleSidePocketSelect(pocket)}
                                                    onHover={() => prefetchImage(pocket.image)}
                                                    image={pocket.type?.diagram || pocket.image}
                                                    label={pocket.type?.name || "Pocket"}
                                                    isLoading={isPending("sidePocket", pocket.id)}
                                                />
                                            ))}
                                        </OptionRow>
                                    </>
                                )}

                                {selectedFabric.chest_pockets?.length > 0 && (
                                    <>
                                        <SectionHeader title="Chest Pockets" />
                                        <OptionRow>
                                            {selectedFabric.chest_pockets.map((pocket) => (
                                                <StyleOptionTile
                                                    key={pocket.id}
                                                    isSelected={pocket.id === selection.chestPocket?.id}
                                                    onClick={() => handleChestPocketSelect(pocket)}
                                                    onHover={() => prefetchImage(pocket.image)}
                                                    image={pocket.type?.diagram || pocket.image}
                                                    label={pocket.type?.name || "Pocket"}
                                                    isLoading={isPending("chestPocket", pocket.id)}
                                                />
                                            ))}
                                        </OptionRow>
                                    </>
                                )}
                            </div>
                        )}

                        {activeTab === "accents" && (
                            <div key="accents" className="pb-4 lg:pb-5 animate-slide-in-left">
                                <SectionHeader title="Lining Type" />
                                <OptionRow cols={3}>
                                    {selectedBody?.default_linings?.map((defaultLining) => (
                                        <LiningOptionTile
                                            key={`default-${defaultLining.id}`}
                                            isSelected={selection.liningMode === "default"}
                                            onClick={handleDefaultLiningClick}
                                            image={defaultLining.type?.diagram || defaultLining.image}
                                            label={defaultLining.type?.name || "Default"}
                                            isLoading={isPending("lining", "default")}
                                        />
                                    ))}

                                    {selectedFabric.custom_linings?.length > 0 && (
                                        <LiningOptionTile
                                            key="custom-type"
                                            isSelected={selection.liningMode === "custom"}
                                            onClick={handleCustomLiningClick}
                                            image={selectedFabric.custom_linings[0].type?.diagram}
                                            label={
                                                selection.customLining
                                                    ? `${selectedFabric.custom_linings[0].type?.name || "Custom"} · ${selection.customLining.fabric?.name ?? ""}`
                                                    : selectedFabric.custom_linings[0].type?.name || "Custom"
                                            }
                                            isLoading={pending?.kind === "lining" && pending.id !== "default"}
                                        />
                                    )}
                                </OptionRow>

                                {bodyButtons.length > 0 && (
                                    <>
                                        <SectionHeader title="Buttons" />
                                        <OptionRow>
                                            <ButtonOptionTile
                                                key="default-button"
                                                isSelected={!selection.button}
                                                onClick={handleDefaultButtonClick}
                                                image={null}
                                                label="Default"
                                                isLoading={isPending("button", "default")}
                                                isDefault
                                            />

                                            {bodyButtons.map((button) => (
                                                <ButtonOptionTile
                                                    key={button.id}
                                                    isSelected={selection.button?.id === button.id}
                                                    onClick={() => handleButtonSelect(button)}
                                                    onHover={() => prefetchImage(button.image)}
                                                    image={button.button_image?.diagram || button.image}
                                                    label={button.button_image?.name || `Button ${button.id}`}
                                                    isLoading={isPending("button", button.id)}
                                                />
                                            ))}
                                        </OptionRow>
                                    </>
                                )}
                            </div>
                        )}
                    </div>

                    {/* CUSTOM LINING — slides over the panel, rail stays reachable */}
                    {showLiningPanel && (
                        <div
                            role="region"
                            aria-label="Custom lining"
                            className="absolute inset-0 z-20 flex flex-col bg-white animate-slide-in-left"
                        >
                            <div className="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-100 lg:px-5 lg:py-4 shrink-0">
                                <div className="min-w-0">
                                    <h2 className="text-base font-semibold text-gray-900 truncate lg:text-lg">Custom Lining</h2>
                                    <p className="hidden mt-0.5 text-xs text-gray-500 truncate sm:block">
                                        {selection.customLining?.fabric?.name ?? "Choose a lining fabric"}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowLiningPanel(false)}
                                    title="Back"
                                    aria-label="Back to accents"
                                    className="flex items-center justify-center text-gray-500 transition-all duration-200 border border-gray-200 rounded-full shrink-0 w-9 h-9 hover:text-gray-900 hover:border-gray-400 active:scale-95"
                                >
                                    <ArrowLeft className="w-4 h-4" />
                                </button>
                            </div>

                            <div className="flex-1 min-h-0 overflow-y-auto overscroll-y-contain thin-scrollbar">
                                <div className="grid grid-cols-2 gap-2 p-4 lg:gap-3 lg:p-5 pb-[max(1rem,env(safe-area-inset-bottom))]">
                                    {selectedFabric.custom_linings?.map((lining) => (
                                        <FabricOptionTile
                                            key={lining.id}
                                            isSelected={selection.customLining?.id === lining.id}
                                            onClick={() => handleLiningSelect(lining)}
                                            onHover={() => prefetchImage(lining.image)}
                                            image={lining.fabric?.image || lining.image}
                                            label={lining.fabric?.name || "Lining"}
                                            isLoading={isPending("lining", lining.id)}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* rail — vertical, desktop only; phones use the bottom tab bar */}
                <nav
                    className="items-center hidden shrink-0 bg-gray-50 lg:flex lg:flex-col lg:w-20 lg:gap-4 lg:py-6 lg:border-l lg:border-gray-100"
                    aria-label="Sections"
                >
                    <Link
                        href="/"
                        title="Back to home"
                        aria-label="Back to home"
                        className="items-center justify-center hidden w-10 h-10 mb-2 text-gray-500 transition-colors rounded-lg lg:flex hover:bg-gray-100"
                    >
                        <Menu className="w-5 h-5" />
                    </Link>
                    <RailTab id="fabric" icon={Layers} label="Fabric" isActive={activeTab === "fabric"} onSelect={setActiveTab} />
                    <RailTab id="style" icon={Scissors} label="Style" isActive={activeTab === "style"} onSelect={setActiveTab} />
                    <RailTab id="accents" icon={Palette} label="Accents" isActive={activeTab === "accents"} onSelect={setActiveTab} />
                </nav>
            </aside>

            {/* LIGHTBOX — the current design, as large as the screen allows */}
            {showLightbox && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label="Your suit, full screen"
                    className="fixed inset-0 z-[900] bg-white animate-fade-in"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) setShowLightbox(false);
                    }}
                >
                    <div className="absolute inset-0">
                        <SuitStage layers={hiresReady ? hiresLayers : layers} dimmed={false} zoom={LIGHTBOX_ZOOM} />
                    </div>

                    {!hiresReady && (
                        <div className="absolute z-10 flex items-center gap-2 px-3 py-1.5 rounded-full shadow-lg top-4 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur-sm animate-slide-down">
                            <Loader2 className="w-3.5 h-3.5 text-gray-900 animate-spin" />
                            <span className="text-xs font-medium text-gray-700">Loading full detail…</span>
                        </div>
                    )}

                    <p className="absolute z-10 px-3 py-1 text-[11px] text-gray-500 -translate-x-1/2 rounded-full bottom-[max(0.75rem,env(safe-area-inset-bottom))] left-1/2 bg-white/80 backdrop-blur-sm pointer-events-none select-none">
                        Drag or scroll to look around
                    </p>
                    <button
                        type="button"
                        onClick={() => setShowLightbox(false)}
                        title="Close"
                        aria-label="Close full screen view"
                        className="absolute z-10 flex items-center justify-center text-gray-700 transition-all duration-200 bg-white border border-gray-200 rounded-full shadow-md w-11 h-11 top-3 right-3 lg:top-6 lg:right-6 hover:bg-gray-900 hover:text-white hover:border-gray-900 active:scale-95"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
            )}

        </div>
    );
};

export default SuitDesigner;
