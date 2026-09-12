import React, { useState, useEffect, useMemo, useCallback, useRef, memo } from "react";
import { Head } from "@inertiajs/react";
import { Loader2, Layers, Scissors, Palette, Menu, X, RotateCcw, Sparkles } from "lucide-react";

const STORAGE_KEY = "hockerty.premium.design.v1";
const CANVAS_TIMEOUT_MS = 8000;

/* ------------------------------------------------------------------ */
/*  Image cache                                                        */
/*                                                                     */
/*  Foreground loads (`load` / `loadAll`) are what a commit waits on.  */
/*  Background prefetch (`prefetch`) runs through a small concurrency  */
/*  gate so warming up other fabrics never starves the current view.   */
/* ------------------------------------------------------------------ */
class ImageCache {
    constructor() {
        this.cache = new Map();     // url -> decoded HTMLImageElement
        this.inflight = new Map();  // url -> Promise
        this.failed = new Set();
        this.queue = [];
        this.queued = new Set();
        this.running = 0;
        this.maxBackground = 3;
    }

    has(url) {
        return this.cache.has(url);
    }

    settled(url) {
        return !url || this.cache.has(url) || this.failed.has(url);
    }

    allSettled(urls) {
        return urls.every((u) => this.settled(u));
    }

    load(url) {
        if (!url) return Promise.resolve(null);
        if (this.cache.has(url)) return Promise.resolve(this.cache.get(url));
        if (this.inflight.has(url)) return this.inflight.get(url);

        const promise = new Promise((resolve) => {
            const img = new Image();
            img.decoding = "async";
            img.onload = async () => {
                // Decode off the main thread now so the swap doesn't jank.
                try { await img.decode(); } catch { /* still usable */ }
                this.cache.set(url, img);
                this.inflight.delete(url);
                resolve(img);
            };
            img.onerror = () => {
                console.warn(`Failed to load image: ${url}`);
                this.failed.add(url);
                this.inflight.delete(url);
                resolve(null);
            };
            img.src = url;
        });

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

    return layers.filter((l) => l.image).sort((a, b) => a.z - b.z);
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
    return urls;
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
    <div className="absolute z-20 flex items-center justify-center w-5 h-5 text-xs text-white duration-200 bg-gray-900 rounded-full top-2 right-2 animate-in fade-in zoom-in">
        ✓
    </div>
);

const TileSpinner = () => (
    <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/60 backdrop-blur-[1px]">
        <Loader2 className="w-5 h-5 text-gray-900 animate-spin" />
    </div>
);

const FabricOptionTile = memo(function FabricOptionTile({ isSelected, onClick, onHover, image, label, price, isLoading }) {
    return (
        <button
            type="button"
            onClick={onClick}
            onMouseEnter={onHover}
            onFocus={onHover}
            className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                isSelected ? "shadow-md scale-[1.02]" : "hover:shadow-md hover:scale-[1.02]"
            }`}
        >
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className="w-full aspect-[4/3] flex items-center justify-center bg-transparent rounded-md overflow-hidden">
                <LazyImage
                    src={image}
                    alt={label}
                    className="object-contain w-full h-full"
                    fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{label || "No image"}</div>}
                />
            </div>
            <div className="mt-2">
                <div className="text-xs font-medium leading-tight text-center text-gray-700">{label}</div>
                {price && <div className="text-xs text-center text-gray-500 mt-0.5">${price}</div>}
            </div>
        </button>
    );
});

const StyleOptionTile = memo(function StyleOptionTile({ isSelected, onClick, onHover, image, label, aspect = "aspect-[3/4]", isLoading }) {
    return (
        <button
            type="button"
            onClick={onClick}
            onMouseEnter={onHover}
            onFocus={onHover}
            className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                isSelected ? "bg-transparent scale-[1.03]" : "hover:scale-[1.03]"
            }`}
        >
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className={`w-full ${aspect} flex items-center justify-center bg-transparent rounded-md overflow-hidden`}>
                <LazyImage
                    src={image}
                    alt={label}
                    className="object-contain w-full h-full p-2"
                    style={{ mixBlendMode: "multiply" }}
                    fallback={<div className="flex items-center justify-center w-full h-full p-2 text-xs text-center text-gray-400">{label}</div>}
                />
            </div>
            <div className="mt-2">
                <div className="text-xs font-medium leading-tight text-center text-gray-700">{label}</div>
            </div>
        </button>
    );
});

const LiningOptionTile = memo(function LiningOptionTile({ isSelected, onClick, image, label, isLoading }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${isSelected ? "scale-[1.02]" : "hover:scale-[1.02]"}`}
        >
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className="w-full aspect-[4/3] flex items-center justify-center bg-transparent rounded-md overflow-hidden">
                <LazyImage
                    src={image}
                    alt={label}
                    className="object-contain w-full h-full p-2"
                    style={{ mixBlendMode: "multiply" }}
                    fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{label}</div>}
                />
            </div>
            <div className="mt-2">
                <div className="text-xs font-medium leading-tight text-center text-gray-700">{label}</div>
            </div>
        </button>
    );
});

const ButtonOptionTile = memo(function ButtonOptionTile({ isSelected, onClick, onHover, image, label, isLoading, isDefault = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            onMouseEnter={onHover}
            onFocus={onHover}
            className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${isSelected ? "scale-[1.02]" : "hover:scale-[1.02]"}`}
        >
            {isSelected && <SelectedBadge />}
            {isLoading && <TileSpinner />}
            <div className="flex items-center justify-center w-full overflow-hidden rounded-md aspect-square">
                {isDefault ? (
                    <div className="flex flex-col items-center justify-center w-full h-full p-2">
                        <div className="flex items-center justify-center w-12 h-12 mb-1 border-4 border-gray-900 rounded-full">
                            <span className="text-xs text-gray-400">−</span>
                        </div>
                    </div>
                ) : (
                    <LazyImage
                        src={image}
                        alt={label}
                        className="object-contain w-full h-full p-2"
                        style={{ mixBlendMode: "multiply" }}
                        fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{label}</div>}
                    />
                )}
            </div>
            <div className="mt-2">
                <div className="text-xs font-medium leading-tight text-center text-gray-700">{label}</div>
            </div>
        </button>
    );
});

const SectionHeader = ({ title }) => (
    <div className="px-5 pt-6 pb-3">
        <span className="text-xs font-bold tracking-wider text-gray-400 uppercase">{title}</span>
    </div>
);

const RailTab = ({ id, icon: Icon, label, isActive, onSelect }) => (
    <button
        type="button"
        onClick={() => onSelect(id)}
        className="flex flex-col items-center gap-1.5 py-3 group w-full transition-transform duration-200 hover:scale-105"
    >
        <div
            className={`flex items-center justify-center w-11 h-11 rounded-full border-2 transition-all duration-300 ${
                isActive
                    ? "bg-gray-900 border-gray-900 text-white shadow-md scale-110"
                    : "bg-white border-gray-200 text-gray-400 group-hover:border-gray-400 group-hover:text-gray-600"
            }`}
        >
            <Icon className="w-5 h-5" />
        </div>
        <span className={`text-[11px] font-semibold tracking-wide uppercase transition-colors duration-300 ${isActive ? "text-gray-900" : "text-gray-400"}`}>
            {label}
        </span>
    </button>
);

/*
 * Canvas layers are keyed by *type*, not record id, so a fabric or option
 * change swaps the `src` of an existing <img> instead of remounting it.
 * Every src reaching here has already been loaded + decoded by the cache,
 * so the swap paints in the same frame — no spinner, no half-drawn suit.
 */
const CanvasLayer = memo(function CanvasLayer({ layer }) {
    return (
        <img
            src={layer.image}
            alt=""
            aria-hidden="true"
            draggable={false}
            decoding="sync"
            className="absolute inset-0 object-contain w-full h-full pointer-events-none select-none"
            style={{ zIndex: layer.z }}
            onError={(e) => {
                console.error(`Failed to render ${layer.type}:`, layer.image);
                e.currentTarget.style.visibility = "hidden";
            }}
        />
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

    /* ---------- commit: prepare images, then swap the whole view at once ---------- */
    const commit = useCallback(async (next, pendingInfo, { adjustments = [] } = {}) => {
        const token = ++commitTokenRef.current;
        const urls = layerUrls(next);

        if (!imageCache.allSettled(urls)) {
            setPending(pendingInfo);
            await Promise.race([
                imageCache.loadAll(urls),
                new Promise((resolve) => setTimeout(resolve, CANVAS_TIMEOUT_MS)),
            ]);
        }

        if (token !== commitTokenRef.current) return; // superseded by a newer change

        intentRef.current = completeIntent(intentRef.current, next);
        saveDesign(next.fabric.id, intentRef.current);

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
        if (!selection || !fabrics) return;

        const handle = runWhenIdle(() => {
            imageCache.clearQueue();
            // 1. Everything the user can click on this fabric -> option changes feel instant.
            imageCache.prefetch(optionUrlsFor(selection));
            // 2. What every other fabric would look like with this exact design -> fabric switches feel instant.
            const intent = intentRef.current;
            fabrics
                .filter((f) => f.id !== selection.fabric.id)
                .forEach((f) => imageCache.prefetch(layerUrls(resolveSelection(f, intent))));
        });

        return () => cancelIdle(handle);
    }, [selection, fabrics]);

    useEffect(() => () => noticeTimerRef.current && clearTimeout(noticeTimerRef.current), []);

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
        imageCache.prefetch(linings.flatMap((l) => [l.image, l.fabric?.image]), { front: true });
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

    /* Hover = high-priority prefetch, so the click that follows is usually instant. */
    const prefetchFabric = useCallback((fabric) => {
        imageCache.prefetch(layerUrls(resolveSelection(fabric, intentRef.current)), { front: true });
    }, []);
    const prefetchImage = useCallback((url) => imageCache.prefetch([url], { front: true }), []);

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
            <div className="flex items-center justify-center h-screen bg-gray-50">
                <div className="text-center duration-500 animate-in fade-in zoom-in">
                    <Loader2 className="w-12 h-12 mx-auto mb-4 text-gray-900 animate-spin" />
                    <p className="text-lg text-gray-600">{fabrics ? "Preparing your suit…" : "Loading customization options…"}</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center justify-center h-screen bg-gray-50">
                <div className="max-w-md p-8 text-center duration-500 bg-white shadow-lg rounded-xl animate-in fade-in slide-in-from-bottom-4">
                    <div className="mb-4 text-5xl">⚠️</div>
                    <h2 className="mb-2 text-2xl font-bold text-gray-800">Error Loading Data</h2>
                    <p className="mb-4 text-gray-600">{error}</p>
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
            <div className="flex items-center justify-center h-screen bg-gray-50">
                <p className="text-gray-600">No data available</p>
            </div>
        );
    }

    const { fabric: selectedFabric, body: selectedBody, lapel: selectedLapel } = selection;
    const bodyButtons = selectedBody?.body_type?.body_buttons || [];

    /* ---------- main render ---------- */
    return (
        <div className="flex h-screen bg-white">
            <Head title="Design your suit" />
            <style>{`
                .loader {
                    --color-1: #d1d5db;
                    --size: 2px;
                    width: calc(48 * var(--size));
                    height: calc(48 * var(--size));
                    border: calc(5 * var(--size)) dotted var(--color-1);
                    border-radius: 50%;
                    display: inline-block;
                    position: relative;
                    box-sizing: border-box;
                    animation: rotation 2s linear infinite;
                }
                @keyframes rotation {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `}</style>

            {/* SIDEBAR */}
            <div className="z-10 flex shadow-lg shrink-0">
                <div className="overflow-y-auto bg-white border-r border-gray-100 w-96">
                    <div className="flex items-start justify-between gap-3 px-5 py-5 border-b border-gray-100">
                        <div>
                            <h1 className="text-xl font-semibold text-gray-900 transition-all duration-300">
                                {activeTab === "fabric" && "Choose your fabric"}
                                {activeTab === "style" && "Customize your style"}
                                {activeTab === "accents" && "Accents & lining"}
                            </h1>
                            <p className="mt-1 text-sm text-gray-500">
                                {activeTab === "fabric" && "Your design carries over to every fabric"}
                                {activeTab === "style" && "Personalize the cut and details"}
                                {activeTab === "accents" && "Add the finishing touches"}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={handleReset}
                            title="Reset design"
                            aria-label="Reset design"
                            className="p-2 mt-0.5 text-gray-400 transition-all duration-200 rounded-lg hover:text-gray-700 hover:bg-gray-100 active:scale-95"
                        >
                            <RotateCcw className="w-4 h-4" />
                        </button>
                    </div>

                    {activeTab === "fabric" && (
                        <div className="p-5 duration-300 animate-in fade-in slide-in-from-left-2">
                            <div className="grid grid-cols-2 gap-3">
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
                        <div className="pb-5 duration-300 animate-in fade-in slide-in-from-left-2">
                            {selectedFabric.bodies?.length > 0 && (
                                <>
                                    <SectionHeader title="Body Style" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
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
                                    </div>
                                </>
                            )}

                            {lapelCategories.length > 0 && (
                                <>
                                    <SectionHeader title="Lapel Type" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
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
                                    </div>

                                    {filteredLapels.length > 0 && (
                                        <>
                                            <SectionHeader title={`${selectedLapel.category?.name ?? "Lapel"} Width`} />
                                            <div className="grid grid-cols-3 gap-3 px-5">
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
                                            </div>
                                        </>
                                    )}
                                </>
                            )}

                            {selectedFabric.sleeves?.length > 0 && (
                                <>
                                    <SectionHeader title="Sleeves" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
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
                                    </div>
                                </>
                            )}

                            {selectedFabric.side_pockets?.length > 0 && (
                                <>
                                    <SectionHeader title="Side Pockets" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
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
                                    </div>
                                </>
                            )}

                            {selectedFabric.chest_pockets?.length > 0 && (
                                <>
                                    <SectionHeader title="Chest Pockets" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
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
                                    </div>
                                </>
                            )}
                        </div>
                    )}

                    {activeTab === "accents" && (
                        <div className="p-5 duration-300 animate-in fade-in slide-in-from-left-2">
                            <div className="mb-6">
                                <h3 className="mb-4 text-sm font-semibold tracking-wide text-gray-900 uppercase">Lining Type</h3>

                                <div className="grid grid-cols-2 gap-3">
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
                                </div>
                            </div>

                            {bodyButtons.length > 0 && (
                                <div className="mb-6">
                                    <h3 className="mb-4 text-sm font-semibold tracking-wide text-gray-900 uppercase">Buttons</h3>

                                    <div className="grid grid-cols-3 gap-3">
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
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex flex-col items-center w-20 gap-4 py-6 border-l border-gray-100 bg-gray-50">
                    <button
                        type="button"
                        className="flex items-center justify-center w-10 h-10 mb-2 text-gray-500 transition-colors rounded-lg hover:bg-gray-100"
                        aria-label="Menu"
                    >
                        <Menu className="w-5 h-5" />
                    </button>
                    <RailTab id="fabric" icon={Layers} label="Fabric" isActive={activeTab === "fabric"} onSelect={setActiveTab} />
                    <RailTab id="style" icon={Scissors} label="Style" isActive={activeTab === "style"} onSelect={setActiveTab} />
                    <RailTab id="accents" icon={Palette} label="Accents" isActive={activeTab === "accents"} onSelect={setActiveTab} />
                </div>
            </div>

            {/* CANVAS */}
            <div className="relative flex items-center justify-center flex-1 overflow-hidden bg-transparent">
                <div className="flex items-center justify-center w-full h-full">
                    <div
                        className={`relative overflow-hidden bg-transparent rounded-xl transition-opacity duration-300 ease-out ${
                            pending ? "opacity-60" : "opacity-100"
                        }`}
                        style={{ width: "600px", height: "800px" }}
                    >
                        {layers.map((layer) => (
                            <CanvasLayer key={layer.type} layer={layer} />
                        ))}
                    </div>
                </div>

                {pending && (
                    <div className="absolute z-50 flex items-center gap-2 px-3 py-1.5 rounded-full shadow-lg top-4 right-4 bg-white/90 backdrop-blur-sm animate-in fade-in slide-in-from-top-2">
                        <Loader2 className="w-3.5 h-3.5 text-gray-900 animate-spin" />
                        <span className="text-xs font-medium text-gray-700">{PENDING_LABELS[pending.kind] || "Updating"}…</span>
                    </div>
                )}


            </div>

            {/* CUSTOM LINING MODAL */}
            {showLiningModal && (
                <div
                    className="fixed inset-0 z-[1000] flex items-center justify-center bg-black/40 backdrop-blur-sm animate-in fade-in duration-200"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) setShowLiningModal(false);
                    }}
                >
                    <div className="w-full max-w-md p-6 mx-4 duration-300 bg-white shadow-2xl rounded-xl animate-in zoom-in-95 slide-in-from-bottom-4">
                        <div className="flex items-center justify-between mb-5">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">Select Custom Lining</h2>
                                <p className="mt-1 text-xs text-gray-500">Choose your preferred lining</p>
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

                        <div className="grid grid-cols-3 gap-3">
                            {selectedFabric.custom_linings?.map((lining) => {
                                const isSelected = selection.customLining?.id === lining.id;
                                return (
                                    <button
                                        key={lining.id}
                                        type="button"
                                        onClick={() => handleLiningSelect(lining)}
                                        onMouseEnter={() => prefetchImage(lining.image)}
                                        className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                                            isSelected ? "shadow-md scale-[1.02]" : "hover:shadow-md hover:scale-[1.02]"
                                        }`}
                                    >
                                        {isSelected && <SelectedBadge />}
                                        {isPending("lining", lining.id) && <TileSpinner />}
                                        <div className="flex items-center justify-center w-full overflow-hidden bg-transparent rounded-md aspect-[4/3]">
                                            <LazyImage
                                                src={lining.fabric?.image || lining.image}
                                                alt={lining.fabric?.name || "Lining"}
                                                className="object-contain w-full h-full"
                                                fallback={<div className="flex items-center justify-center w-full h-full text-xs text-gray-400">{lining.fabric?.name || "Lining"}</div>}
                                            />
                                        </div>
                                        <div className="mt-2">
                                            <div className="text-xs font-medium leading-tight text-center text-gray-700">{lining.fabric?.name || "Lining"}</div>
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
