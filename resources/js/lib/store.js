/*
 * Client-side commerce state: the bag and the body profile. Both live in
 * localStorage so a guest can design, add to bag, measure and check out
 * without an account; the server re-prices and validates everything at
 * checkout. `subscribe` lets any component follow changes (same tab via a
 * custom event, other tabs via the storage event).
 */
import { useEffect, useState } from "react";

const CART_KEY = "custom-tailor.cart.v1";
const PROFILE_KEY = "custom-tailor.body-profile.v1";
const EVENT = "custom-tailor:store";

const read = (key, fallback) => {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
};

const write = (key, value) => {
    try {
        if (value == null) window.localStorage.removeItem(key);
        else window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        /* storage unavailable */
    }
    window.dispatchEvent(new CustomEvent(EVENT, { detail: key }));
};

export const money = (value) => `$${Math.round(Number(value) || 0)}`;

/* ---------- Bag ---------- */
export const getCart = () => {
    const items = read(CART_KEY, []);
    return Array.isArray(items) ? items : [];
};

export function addToCart(item) {
    const items = getCart();
    const id = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
    write(CART_KEY, [...items, { ...item, id, quantity: 1, addedAt: new Date().toISOString() }]);
    return id;
}

export const removeFromCart = (id) => write(CART_KEY, getCart().filter((i) => i.id !== id));

export const setCartQuantity = (id, quantity) =>
    write(CART_KEY, getCart().map((i) => (i.id === id ? { ...i, quantity: Math.max(1, Math.min(5, quantity)) } : i)));

export const clearCart = () => write(CART_KEY, null);

export const cartCount = (items = getCart()) => items.reduce((n, i) => n + (i.quantity || 1), 0);
export const cartSubtotal = (items = getCart()) => items.reduce((n, i) => n + (Number(i.price) || 0) * (i.quantity || 1), 0);

/* ---------- Body profile ---------- */
export const getBodyProfile = () => read(PROFILE_KEY, null);
export const setBodyProfile = (profile) => write(PROFILE_KEY, profile);

/* ---------- React ---------- */
function subscribe(listener) {
    const onStore = () => listener();
    const onStorage = (e) => {
        if (!e.key || e.key === CART_KEY || e.key === PROFILE_KEY) listener();
    };
    window.addEventListener(EVENT, onStore);
    window.addEventListener("storage", onStorage);
    return () => {
        window.removeEventListener(EVENT, onStore);
        window.removeEventListener("storage", onStorage);
    };
}

export function useCart() {
    const [items, setItems] = useState([]);
    useEffect(() => {
        setItems(getCart());
        return subscribe(() => setItems(getCart()));
    }, []);
    return items;
}

export function useBodyProfile() {
    const [profile, setProfile] = useState(null);
    useEffect(() => {
        setProfile(getBodyProfile());
        return subscribe(() => setProfile(getBodyProfile()));
    }, []);
    return profile;
}

/* ---------- Design summary ---------- */
/* The choices a designer intent carries, as {label, value} rows for the bag, checkout and order. */
export function summarizeDesign(intent) {
    if (!intent) return [];
    const rows = [];
    if (intent.body?.name) rows.push({ label: "Body", value: intent.body.name });
    if (intent.lapel?.categoryName) rows.push({ label: "Lapel", value: [intent.lapel.categoryName, intent.lapel.subcategoryName].filter(Boolean).join(" · ") });
    if (intent.sleeve?.name) rows.push({ label: "Shoulder", value: intent.sleeve.name });
    if (intent.sidePocket?.name) rows.push({ label: "Side pockets", value: intent.sidePocket.name });
    if (intent.chestPocket?.name) rows.push({ label: "Chest pocket", value: intent.chestPocket.name });
    rows.push({ label: "Buttons", value: intent.button?.name || "Default" });
    rows.push({ label: "Lining", value: intent.lining?.mode === "custom" && intent.lining.name ? intent.lining.name : "Default" });
    return rows;
}
