import React, { useState, useEffect, useLayoutEffect, useMemo, useCallback, useRef, memo } from "react";
import { Head } from "@inertiajs/react";
import { Loader2, Layers, Scissors, Palette, Menu, X, RotateCcw } from "lucide-react";

const STORAGE_KEY = "hockerty.premium.design.v1";
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
const THUMB_WIDTH = 320;

const layerUrl = (url) => optimizeUrl(url, LAYER_WIDTH);
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
            .then((source) => (source ? createImageBitmap(source) : null))
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
const bitmapCache = new BitmapCache(LAYER_WIDTH >= 1600 ? 220 * 1024 * 1024 : 120 * 1024 * 1024);

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
    if (sel.button) layers.push({ type: "button", image: sel.button.image, z: sel.button.layer_index || 160 });

    return layers
        .filter((l) => l.image)
        .map((l) => ({ ...l, image: layerUrl(l.image) }))
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
    `${TILE_BASE} ${width} ${isSelected ? "shadow-md scale-[1.02]" : "hover:shadow-md hover:scale-[1.02] active:scale-[0.98]"}`;

const hoverHandlers = (onHover) => (onHover ? { onMouseEnter: onHover, onFocus: onHover, onPointerDown: onHover } : {});

const FabricOptionTile = memo(function FabricOptionTile({ isSelected, onClick, onHover, image, label, price, isLoading }) {
    return (
        <button type="button" onClick={onClick} {...hoverHandlers(onHover)} className={tileClass(isSelected, "w-full")} aria-pressed={isSelected}>
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className="w-full aspect-[4/3] flex items-center justify-center bg-transparent rounded-md overflow-hidden">
                <LazyImage
                    src={thumbUrl(image)}
                    alt={label}
                    className="object-contain w-full h-full"
                    fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{label || "No image"}</div>}
                />
            </div>
            <div className="mt-2">
                <div className="text-[11px] lg:text-xs font-medium leading-tight text-center text-gray-700 line-clamp-2 lg:line-clamp-none">{label}</div>
                {price && <div className="text-[11px] lg:text-xs text-center text-gray-500 mt-0.5">${price}</div>}
            </div>
        </button>
    );
});

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

const SuitStage = memo(function SuitStage({ layers, dimmed }) {
    const wrapRef = useRef(null);
    const canvasRef = useRef(null);
    const [area, setArea] = useState({ w: 0, h: 0 });
    const [tick, setTick] = useState(0);

    const aspect = useMemo(() => stageAspectOf(layers), [layers]);

    const box = useMemo(() => {
        if (!area.w || !area.h) return { w: 0, h: 0 };
        const w = Math.min(area.w, area.h * aspect);
        return { w: Math.floor(w), h: Math.floor(w / aspect) };
    }, [area, aspect]);

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

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
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
    fabric: { title: "Choose your fabric", subtitle: "Your design carries over to every fabric" },
    style: { title: "Customize your style", subtitle: "Personalize the cut and details" },
    accents: { title: "Accents & lining", subtitle: "Add the finishing touches" },
};

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

    const [activeTab, setActiveTab] = useState("fabric");
    const [showLiningModal, setShowLiningModal] = useState(false);

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
                const fabric =
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

    /* Scroll the panel back to the top when switching tabs. */
    useEffect(() => {
        panelScrollRef.current?.scrollTo({ top: 0 });
    }, [activeTab]);

    /* Close the lining modal on Escape. */
    useEffect(() => {
        if (!showLiningModal) return undefined;
        const onKey = (e) => e.key === "Escape" && setShowLiningModal(false);
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [showLiningModal]);

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
        setShowLiningModal(false);
        choose("lining", "default", { lining: liningIntent(null) });
    };

    const handleCustomLiningClick = () => {
        setShowLiningModal(true);
        const linings = targetFabricRef.current?.custom_linings || [];
        imageCache.prefetch(linings.flatMap((l) => [layerUrl(l.image), thumbUrl(l.fabric?.image)]), { front: true });
    };

    const handleLiningSelect = (lining) => {
        setShowLiningModal(false);
        choose("lining", lining.id, { lining: liningIntent(lining) });
    };

    const handleReset = () => {
        if (!fabrics?.length) return;
        clearSavedDesign();
        intentRef.current = EMPTY_INTENT;
        const fabric = fabrics.find((f) => f.is_default) || fabrics[0];
        targetFabricRef.current = fabric;
        setShowLiningModal(false);
        commit(resolveSelection(fabric, EMPTY_INTENT), { kind: "reset" });
    };

    /* Hover / touch-down = high-priority prefetch, so the click that follows is usually instant. */
    const prefetchFabric = useCallback((fabric) => {
        imageCache.prefetch(layerUrls(resolveSelection(fabric, intentRef.current)), { front: true });
    }, []);
    const prefetchImage = useCallback((url) => imageCache.prefetch([layerUrl(url)], { front: true }), []);

    /* ---------- derived ---------- */
    const layers = useMemo(() => layersFrom(selection), [selection]);

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
            <div className="flex items-center justify-center h-dvh px-6 bg-gray-50">
                <Head title="Design your suit" />
                <div className="text-center animate-fade-in">
                    <Loader2 className="w-10 h-10 mx-auto mb-4 text-gray-900 sm:w-12 sm:h-12 animate-spin" />
                    <p className="text-base text-gray-600 sm:text-lg">{fabrics ? "Preparing your suit…" : "Loading customization options…"}</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center justify-center h-dvh px-4 bg-gray-50">
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
            <div className="flex items-center justify-center h-dvh px-6 bg-gray-50">
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
        <div className="flex flex-col h-dvh overflow-hidden bg-white max-lg:landscape:flex-row lg:flex-row">
            <Head title="Design your suit" />

            {/* STAGE — on top below lg, on the right from lg */}
            <main className="relative flex-1 min-w-0 min-h-0 order-1 max-lg:landscape:order-2 lg:order-2 p-3 sm:p-5 lg:p-8">
                <SuitStage layers={layers} dimmed={Boolean(pending)} />

                {pending && (
                    <div className="absolute z-30 flex items-center gap-2 px-3 py-1.5 rounded-full shadow-lg top-3 right-3 lg:top-4 lg:right-4 bg-white/90 backdrop-blur-sm animate-slide-down">
                        <Loader2 className="w-3.5 h-3.5 text-gray-900 animate-spin" />
                        <span className="text-xs font-medium text-gray-700">{PENDING_LABELS[pending.kind] || "Updating"}…</span>
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
            </main>

            {/* PANEL — bottom sheet below lg, sidebar from lg */}
            <aside
                className="z-10 flex flex-col order-2 w-full shrink-0 h-[44dvh] md:h-[40dvh] max-lg:landscape:order-1 max-lg:landscape:h-full max-lg:landscape:w-[min(55vw,24rem)] max-lg:landscape:border-t-0 max-lg:landscape:border-r lg:order-1 lg:flex-row lg:h-full lg:w-[26rem] xl:w-[29rem] bg-white border-t border-gray-100 lg:border-t-0 lg:border-r shadow-[0_-6px_24px_rgba(0,0,0,0.06)] lg:shadow-lg"
                aria-label="Customization options"
            >
                {/* content */}
                <div className="flex flex-col flex-1 min-w-0 min-h-0">
                    <div className="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-100 lg:items-start lg:px-5 lg:py-5 shrink-0">
                        <div className="min-w-0">
                            <h1 className="text-base font-semibold text-gray-900 truncate lg:text-xl">{copy.title}</h1>
                            <p className="hidden mt-0.5 text-xs text-gray-500 sm:block lg:mt-1 lg:text-sm">{copy.subtitle}</p>
                        </div>
                        <button
                            type="button"
                            onClick={handleReset}
                            title="Reset design"
                            aria-label="Reset design"
                            className="p-2 text-gray-400 transition-all duration-200 rounded-lg shrink-0 hover:text-gray-700 hover:bg-gray-100 active:scale-95"
                        >
                            <RotateCcw className="w-4 h-4" />
                        </button>
                    </div>

                    <div ref={panelScrollRef} className="flex-1 min-h-0 overflow-y-auto overscroll-y-contain thin-scrollbar">
                        {activeTab === "fabric" && (
                            <div key="fabric" className="p-4 lg:p-5 animate-slide-in-left">
                                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-2 lg:gap-3">
                                    {fabrics.map((fabric) => (
                                        <FabricOptionTile
                                            key={fabric.id}
                                            isSelected={fabric.id === selectedFabric.id}
                                            onClick={() => changeFabric(fabric)}
                                            onHover={() => prefetchFabric(fabric)}
                                            image={fabric.image}
                                            label={fabric.name}
                                            price={fabric.price}
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
                                <OptionRow cols={2}>
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
                </div>

                {/* rail — bottom tab bar below lg, vertical rail from lg */}
                <nav
                    className="flex items-center shrink-0 border-t border-gray-100 bg-gray-50 pb-[env(safe-area-inset-bottom)] lg:flex-col lg:w-20 lg:gap-4 lg:py-6 lg:border-t-0 lg:border-l"
                    aria-label="Sections"
                >
                    <button
                        type="button"
                        className="items-center justify-center hidden w-10 h-10 mb-2 text-gray-500 transition-colors rounded-lg lg:flex hover:bg-gray-100"
                        aria-label="Menu"
                    >
                        <Menu className="w-5 h-5" />
                    </button>
                    <RailTab id="fabric" icon={Layers} label="Fabric" isActive={activeTab === "fabric"} onSelect={setActiveTab} />
                    <RailTab id="style" icon={Scissors} label="Style" isActive={activeTab === "style"} onSelect={setActiveTab} />
                    <RailTab id="accents" icon={Palette} label="Accents" isActive={activeTab === "accents"} onSelect={setActiveTab} />
                </nav>
            </aside>

            {/* CUSTOM LINING MODAL — bottom sheet on phones, dialog from sm */}
            {showLiningModal && (
                <div
                    className="fixed inset-0 z-[1000] flex items-end justify-center sm:items-center bg-black/40 backdrop-blur-sm animate-fade-in"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) setShowLiningModal(false);
                    }}
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="lining-modal-title"
                        className="w-full sm:max-w-md max-h-[85dvh] flex flex-col bg-white shadow-2xl rounded-t-2xl sm:rounded-xl sm:mx-4 animate-slide-up"
                    >
                        <div className="flex items-center justify-between px-5 pt-4 pb-3 border-b border-gray-100 sm:pt-5 shrink-0">
                            <div>
                                <h2 id="lining-modal-title" className="text-lg font-semibold text-gray-900">Select Custom Lining</h2>
                                <p className="mt-0.5 text-xs text-gray-500">Choose your preferred lining</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowLiningModal(false)}
                                className="p-1.5 text-gray-400 transition-all duration-200 rounded-lg hover:text-gray-700 hover:bg-gray-100"
                                aria-label="Close"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="grid grid-cols-3 gap-2 p-4 overflow-y-auto sm:gap-3 sm:p-5 pb-[max(1rem,env(safe-area-inset-bottom))] thin-scrollbar">
                            {selectedFabric.custom_linings?.map((lining) => {
                                const isSelected = selection.customLining?.id === lining.id;
                                return (
                                    <button
                                        key={lining.id}
                                        type="button"
                                        onClick={() => handleLiningSelect(lining)}
                                        {...hoverHandlers(() => prefetchImage(lining.image))}
                                        aria-pressed={isSelected}
                                        className={`relative p-2 sm:p-3 rounded-lg transition-all duration-300 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/40 ${
                                            isSelected ? "shadow-md scale-[1.02]" : "hover:shadow-md hover:scale-[1.02] active:scale-[0.98]"
                                        }`}
                                    >
                                        {isSelected && <SelectedBadge />}
                                        {isPending("lining", lining.id) && <TileSpinner />}
                                        <div className="flex items-center justify-center w-full overflow-hidden bg-transparent rounded-md aspect-[4/3]">
                                            <LazyImage
                                                src={thumbUrl(lining.fabric?.image || lining.image)}
                                                alt={lining.fabric?.name || "Lining"}
                                                className="object-contain w-full h-full"
                                                fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{lining.fabric?.name || "Lining"}</div>}
                                            />
                                        </div>
                                        <div className="mt-2">
                                            <div className="text-[11px] sm:text-xs font-medium leading-tight text-center text-gray-700 line-clamp-2 lg:line-clamp-none">{lining.fabric?.name || "Lining"}</div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default SuitDesigner;
