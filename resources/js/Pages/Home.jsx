import React, { useState, useEffect, useRef, useCallback } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { cartCount, useCart } from "@/lib/store";
import { Menu, X, Globe, User, ShoppingBag, ChevronLeft, ChevronRight, ArrowRight, ArrowUpRight, Instagram, Facebook } from "lucide-react";

/* ------------------------------------------------------------------ */
/*  Photography                                                        */
/*                                                                     */
/*  Editorial photos are managed in the admin (Homepage page) and      */
/*  served from Cloudinary. A slot without a photo renders a toned     */
/*  panel so the layout reads as intended.                             */
/* ------------------------------------------------------------------ */
const TONES = {
    stone: "bg-[linear-gradient(150deg,#ece7df_0%,#d9d2c6_55%,#c9c0b2_100%)]",
    graphite: "bg-[linear-gradient(160deg,#2e2e2e_0%,#151515_60%,#0b0b0b_100%)]",
    sand: "bg-[linear-gradient(160deg,#d8c7ad_0%,#b89d78_100%)]",
};

/* `className` must position the wrapper itself (relative / absolute inset-0). */
const Photo = ({ src, alt = "", className = "", tone = "stone", position = "object-center", eager = false, placeholder = null, children }) => {
    const [loaded, setLoaded] = useState(false);
    const [failed, setFailed] = useState(false);
    return (
        <div className={`overflow-hidden ${className}`}>
            <div className={`absolute inset-0 ${TONES[tone]}`} aria-hidden="true" />
            {!loaded && placeholder}
            {src && !failed && (
                <img
                    src={src}
                    alt={alt}
                    loading={eager ? "eager" : "lazy"}
                    onLoad={() => setLoaded(true)}
                    onError={() => setFailed(true)}
                    className={`absolute inset-0 w-full h-full object-cover ${position} transition-opacity duration-700 ${loaded ? "opacity-100" : "opacity-0"}`}
                />
            )}
            {children}
        </div>
    );
};

/* Cloudinary URLs get a device-sized, auto-format variant; anything else is served as-is. */
const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
const swatchUrl = (url, width) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_${width}/${m[2]}` : url;
};

const Brand = ({ d, className }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden="true">
        <path d={d} />
    </svg>
);

const SOCIAL_ICONS = {
    instagram: (cls) => <Instagram className={cls} strokeWidth={1.5} />,
    facebook: (cls) => <Facebook className={cls} strokeWidth={1.5} />,
    x: (cls) => (
        <Brand
            className={cls}
            d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"
        />
    ),
    pinterest: (cls) => (
        <Brand
            className={cls}
            d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"
        />
    ),
    tiktok: (cls) => (
        <Brand
            className={cls}
            d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"
        />
    ),
};

const SOCIAL_LABELS = { instagram: "Instagram", facebook: "Facebook", x: "X", pinterest: "Pinterest", tiktok: "TikTok" };

const money = (value) => `$${Math.round(Number(value) || 0)}`;

/* Elements tagged data-reveal fade up the first time they scroll into view. */
function useReveal(deps) {
    useEffect(() => {
        const els = document.querySelectorAll("[data-reveal]:not(.is-visible)");
        if (els.length === 0) return undefined;
        const io = new IntersectionObserver(
            (entries) =>
                entries.forEach((e) => {
                    if (!e.isIntersecting) return;
                    e.target.classList.add("is-visible");
                    io.unobserve(e.target);
                }),
            { threshold: 0.12, rootMargin: "0px 0px -8% 0px" }
        );
        els.forEach((el) => io.observe(el));
        return () => io.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);
}

/* ------------------------------------------------------------------ */
/*  Header                                                             */
/* ------------------------------------------------------------------ */
const NAV = [
    { label: "Custom suits", href: "/design", inertia: true },
    { label: "Fabrics", href: "#fabrics" },
    { label: "How it works", href: "#how-it-works" },
];

const NavAnchor = ({ item, ...rest }) =>
    item.inertia ? (
        <Link href={item.href} {...rest}>
            {item.label}
        </Link>
    ) : (
        <a href={item.href} {...rest}>
            {item.label}
        </a>
    );

const Header = ({ user }) => {
    const [scrolled, setScrolled] = useState(false);
    const [open, setOpen] = useState(false);
    const bagCount = cartCount(useCart());

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 24);
        onScroll();
        window.addEventListener("scroll", onScroll, { passive: true });
        return () => window.removeEventListener("scroll", onScroll);
    }, []);

    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => e.key === "Escape" && setOpen(false);
        window.addEventListener("keydown", onKey);
        document.body.style.overflow = "hidden";
        return () => {
            window.removeEventListener("keydown", onKey);
            document.body.style.overflow = "";
        };
    }, [open]);

    const solid = scrolled || open;
    const ink = solid ? "text-gray-900" : "text-white";
    const link = `nav-link text-[15px] font-medium tracking-tight ${ink}`;

    return (
        <header className="fixed inset-x-0 top-0 z-50">
            <div
                className={`overflow-hidden text-center text-[13px] tracking-wide text-white bg-[#1c1c1c] transition-all duration-300 ${
                    scrolled ? "h-0" : "h-9"
                }`}
            >
                <p className="leading-9">Custom-tailored suits</p>
            </div>

            <div
                className={`relative z-10 transition-all duration-300 ${
                    solid ? "bg-white/95 backdrop-blur-md shadow-[0_1px_0_rgba(0,0,0,0.06)]" : "bg-gradient-to-b from-black/45 to-transparent"
                }`}
            >
                <div className={`relative flex items-center justify-between px-4 mx-auto max-w-[1600px] sm:px-6 lg:px-8 transition-[height] duration-300 ${scrolled ? "h-14" : "h-16 lg:h-20"}`}>
                    <div className="flex items-center gap-6 lg:gap-8">
                        <button
                            type="button"
                            onClick={() => setOpen((v) => !v)}
                            aria-label={open ? "Close menu" : "Open menu"}
                            aria-expanded={open}
                            className={`p-1 -m-1 transition-opacity hover:opacity-60 ${ink}`}
                        >
                            {open ? <X className="w-6 h-6" strokeWidth={1.5} /> : <Menu className="w-6 h-6" strokeWidth={1.5} />}
                        </button>
                        <nav className="items-center hidden gap-8 md:flex" aria-label="Primary">
                            {NAV.map((item) => (
                                <NavAnchor key={item.label} item={item} className={link} />
                            ))}
                        </nav>
                    </div>

                    <Link
                        href="/"
                        className={`absolute -translate-x-1/2 left-1/2 font-semibold tracking-tight transition-all duration-300 ${scrolled ? "text-[22px]" : "text-[26px]"} ${ink}`}
                        aria-label="Custom Tailor home"
                    >
                        Custom Tailor
                    </Link>

                    <div className={`flex items-center gap-5 lg:gap-6 ${ink}`}>
                        <button type="button" className="items-center hidden gap-1.5 text-[15px] transition-opacity md:flex hover:opacity-60">
                            <Globe className="w-5 h-5" strokeWidth={1.5} />
                            <span>Global</span>
                        </button>
                        <a
                            href={user ? route("dashboard") : route("login")}
                            className="flex items-center gap-1.5 text-[15px] transition-opacity hover:opacity-60"
                        >
                            <User className="w-5 h-5" strokeWidth={1.5} />
                            <span className="hidden sm:inline">{user ? "Account" : "Login"}</span>
                        </a>
                        <Link href={route("cart")} aria-label="Bag" className="relative transition-opacity hover:opacity-60">
                            <ShoppingBag className="w-5 h-5" strokeWidth={1.5} />
                            {bagCount > 0 && (
                                <span className={`absolute -top-1.5 -right-2 flex items-center justify-center min-w-[1rem] h-4 px-1 text-[10px] font-semibold rounded-full ${solid ? "bg-gray-900 text-white" : "bg-white text-gray-900"}`}>
                                    {bagCount}
                                </span>
                            )}
                        </Link>
                    </div>
                </div>
            </div>

            {open && (
                <div className="fixed inset-0 z-0 flex flex-col px-6 pt-32 bg-white animate-fade-in" onClick={() => setOpen(false)}>
                    <nav className="flex flex-col gap-2" aria-label="Menu" onClick={(e) => e.stopPropagation()}>
                        {NAV.map((item, i) => (
                            <NavAnchor
                                key={item.label}
                                item={item}
                                onClick={() => setOpen(false)}
                                className="py-3 text-3xl font-light tracking-tight text-gray-900 border-b border-gray-100 animate-slide-up"
                                style={{ animationDelay: `${i * 50}ms` }}
                            />
                        ))}
                        <Link
                            href="/design"
                            onClick={() => setOpen(false)}
                            className="inline-flex items-center self-start gap-2 px-6 py-3 mt-8 text-sm font-medium text-white bg-gray-900 rounded-full"
                        >
                            Design your suit <ArrowRight className="w-4 h-4" />
                        </Link>
                    </nav>
                </div>
            )}
        </header>
    );
};

/* ------------------------------------------------------------------ */
/*  Sections                                                           */
/* ------------------------------------------------------------------ */
/* Headline words rise one after another. */
const Words = ({ text, base = 0, step = 70, className = "" }) =>
    text.split(" ").map((word, i) => (
        <span key={i} className="inline-block overflow-hidden align-bottom">
            <span className={`inline-block animate-rise ${className}`} style={{ animationDelay: `${base + i * step}ms` }}>
                {word}
                {i < text.split(" ").length - 1 ? " " : ""}
            </span>
        </span>
    ));

const Hero = ({ image, texture }) => {
    const artRef = useRef(null);

    /* Photo drifts at a third of scroll speed; disabled for reduced-motion users. */
    useEffect(() => {
        const el = artRef.current;
        if (!el || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return undefined;
        let raf = 0;
        const onScroll = () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const y = Math.min(window.scrollY, window.innerHeight);
                el.style.transform = `translate3d(0, ${y * 0.3}px, 0)`;
            });
        };
        onScroll();
        window.addEventListener("scroll", onScroll, { passive: true });
        return () => {
            window.removeEventListener("scroll", onScroll);
            cancelAnimationFrame(raf);
        };
    }, []);

    return (
        <section className="relative h-[100svh] min-h-[560px] max-h-[1000px] overflow-hidden bg-[#141414]">
            <div ref={artRef} className="absolute inset-0 will-change-transform">
                <Photo
                    src={swatchUrl(image, 2400)}
                    alt=""
                    tone="graphite"
                    className="absolute inset-0 hero-drift"
                    position="object-[60%_center]"
                    eager
                    placeholder={
                        texture && (
                            <img
                                src={swatchUrl(texture, 600)}
                                alt=""
                                aria-hidden="true"
                                className="absolute inset-0 object-cover w-full h-full scale-125 opacity-40 blur-2xl mix-blend-luminosity"
                            />
                        )
                    }
                />
            </div>
            <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-black/25" aria-hidden="true" />

            <div className="absolute inset-x-0 bottom-0 px-5 pb-12 mx-auto max-w-[1600px] sm:px-8 sm:pb-16 lg:px-12 lg:pb-20">
                <p className="mb-5 text-[11px] font-semibold tracking-[0.28em] text-white/70 uppercase animate-fade-in [animation-delay:200ms]">
                    Made to measure · Designed by you
                </p>
                <h1 className="text-[15vw] sm:text-7xl lg:text-[6.5rem] font-light leading-[0.95] tracking-tight text-white text-balance">
                    <Words text="Dress the real you" base={120} />
                </h1>
                <p className="mt-4 text-lg font-light text-white/90 sm:mt-5 sm:text-2xl animate-slide-up [animation-delay:520ms]">
                    Suits made to fit you, not the other way around
                </p>
                <div className="flex flex-wrap items-center gap-3 mt-8 sm:mt-10 animate-slide-up [animation-delay:640ms]">
                    <Link href="/design" className="btn-ink btn-ink--light">
                        Design your suit <ArrowRight className="w-4 h-4" />
                    </Link>
                    <a
                        href="#fabrics"
                        className="inline-flex items-center px-7 py-3.5 text-sm font-medium text-white border rounded-full border-white/50 transition-colors duration-300 hover:bg-white/10"
                    >
                        Explore fabrics
                    </a>
                </div>
            </div>

            <a
                href="#fabrics"
                aria-label="Scroll to fabrics"
                className="absolute hidden -translate-x-1/2 bottom-8 left-1/2 lg:block animate-fade-in [animation-delay:1200ms]"
            >
                <span className="block w-px h-12 mx-auto bg-white/40 scroll-cue" />
            </a>
        </section>
    );
};

const Eyebrow = ({ children }) => <p className="text-[11px] font-semibold tracking-[0.2em] text-gray-400 uppercase">{children}</p>;

const Title = ({ children, className = "" }) => (
    <h2 className={`text-4xl font-light leading-[1.05] tracking-tight text-gray-900 sm:text-5xl lg:text-6xl ${className}`}>{children}</h2>
);

const Pill = ({ href, children, inertia = false, dark = false }) => {
    const cls = `btn-ink ${dark ? "btn-ink--light" : "btn-ink--outline"}`;
    return inertia ? (
        <Link href={href} className={cls}>{children}</Link>
    ) : (
        <a href={href} className={cls}>{children}</a>
    );
};

/* Fabrics photographed in real life, one card each; cloths without such pictures are left out. */
const realLifeOf = (fabric) => (fabric.real_life_images || []).map((p) => (typeof p === "string" ? { url: p, caption: null } : p));

const Collection = ({ fabrics, loaded }) => {
    const photographed = fabrics.filter((f) => realLifeOf(f).length > 0);
    const rowRef = useRef(null);
    const [edge, setEdge] = useState({ start: true, end: false });

    const update = useCallback(() => {
        const el = rowRef.current;
        if (!el) return;
        setEdge({ start: el.scrollLeft <= 4, end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 4 });
    }, []);

    useEffect(() => {
        update();
        window.addEventListener("resize", update);
        return () => window.removeEventListener("resize", update);
    }, [update, fabrics]);

    if (loaded && photographed.length === 0) return null;

    const scrollBy = (dir) => {
        const el = rowRef.current;
        if (!el) return;
        el.scrollBy({ left: dir * Math.max(240, el.clientWidth * 0.6), behavior: "smooth" });
    };

    const arrow = (dir, hidden) => (
        <button
            type="button"
            onClick={() => scrollBy(dir)}
            aria-label={dir < 0 ? "Previous" : "Next"}
            className={`absolute top-1/2 z-10 hidden -translate-y-1/2 md:flex items-center justify-center w-12 h-12 text-gray-800 rounded-full bg-white/90 shadow-lg backdrop-blur transition-all duration-300 hover:bg-gray-900 hover:text-white ${
                dir < 0 ? "left-2" : "right-2"
            } ${hidden ? "opacity-0 pointer-events-none" : "opacity-100"}`}
        >
            {dir < 0 ? <ChevronLeft className="w-5 h-5" /> : <ChevronRight className="w-5 h-5" />}
        </button>
    );

    const card = "relative shrink-0 snap-start w-[72vw] sm:w-[44vw] md:w-[31vw] lg:w-[23vw] xl:w-[19.5vw] max-w-[360px] group";

    return (
        <section id="fabrics" className="py-16 sm:py-20 lg:py-24 scroll-mt-16">
            <div className="px-5 mx-auto max-w-[1600px] sm:px-8 lg:px-12">
                <div className="flex items-end justify-between gap-6" data-reveal>
                    <div>
                        <Eyebrow>Custom suits</Eyebrow>
                        <Title className="mt-3">Bespoke from head to toe</Title>
                    </div>
                    <p className="hidden max-w-xs text-sm text-gray-500 sm:block">
                        One garment, made your way. Pick a cloth, then shape every detail in the designer.
                    </p>
                </div>
            </div>

            <div className="relative mt-10 sm:mt-12" data-reveal>
                {arrow(-1, edge.start)}
                {arrow(1, edge.end)}
                <div
                    ref={rowRef}
                    onScroll={update}
                    className="flex gap-4 px-5 overflow-x-auto snap-x snap-mandatory no-scrollbar sm:px-8 lg:px-12 sm:gap-5 after:block after:shrink-0 after:w-1"
                >
                    {photographed.map((fabric) => (
                        <Link key={fabric.id} href={`/design?fabric=${fabric.id}`} className={card}>
                            <div className="relative overflow-hidden aspect-[4/5] rounded-sm bg-[#efece6]">
                                <img
                                    src={swatchUrl(realLifeOf(fabric)[0].url, 720)}
                                    alt={realLifeOf(fabric)[0].caption || `${fabric.name} suit`}
                                    loading="lazy"
                                    className="absolute inset-0 object-cover w-full h-full transition-transform duration-[1200ms] ease-out group-hover:scale-110"
                                />
                                <div className="absolute inset-0 transition-colors duration-500 bg-black/0 group-hover:bg-black/15" />
                                <div className="absolute inset-x-0 bottom-0 p-4 transition-all duration-500 translate-y-3 opacity-0 group-hover:translate-y-0 group-hover:opacity-100">
                                    <span className="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-gray-900 rounded-full bg-white/95 shadow-lg">
                                        Design in this cloth <ArrowUpRight className="w-3.5 h-3.5" />
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-baseline justify-between mt-3">
                                <span className="text-[15px] font-medium text-gray-900">{fabric.name}</span>
                                <span className="text-sm text-gray-500">{money(fabric.price)}</span>
                            </div>
                        </Link>
                    ))}

                    {!loaded &&
                        [0, 1, 2, 3].map((i) => (
                            <div key={i} className={card} aria-hidden="true">
                                <div className="aspect-[4/5] rounded-sm bg-[#f1eee9] animate-pulse" />
                                <div className="w-1/2 h-3 mt-4 rounded bg-[#f1eee9]" />
                            </div>
                        ))}
                </div>
            </div>
        </section>
    );
};

/* ------------------------------------------------------------------ */
/*  Marquee                                                            */
/* ------------------------------------------------------------------ */
const Marquee = ({ items }) => {
    const row = [...items, ...items];
    return (
        <div className="py-5 overflow-hidden bg-white border-y border-gray-100" aria-hidden="true">
            <div className="flex w-max marquee-track">
                {row.map((item, i) => (
                    <span key={i} className="flex items-center gap-8 pr-8 text-[11px] font-semibold tracking-[0.28em] text-gray-400 uppercase whitespace-nowrap">
                        {item}
                        <span className="w-1 h-1 bg-gray-300 rounded-full" />
                    </span>
                ))}
            </div>
        </div>
    );
};

/* ------------------------------------------------------------------ */
/*  Details — built from the live catalogue                            */
/* ------------------------------------------------------------------ */
const uniqBy = (list, key) => {
    const seen = new Map();
    list.forEach((item) => {
        const k = key(item);
        if (k != null && !seen.has(k)) seen.set(k, item);
    });
    return [...seen.values()];
};

function detailGroups(fabric) {
    if (!fabric) return [];
    const body = fabric.bodies?.[0];
    const lapels = body?.lapels || [];
    const lapelCats = uniqBy(lapels.map((l) => l.category).filter(Boolean), (c) => c.id);
    const shoulders = uniqBy((fabric.sleeves || []).map((x) => x.type).filter(Boolean), (t) => t.id);
    const pockets = uniqBy((fabric.side_pockets || []).map((x) => x.type).filter(Boolean), (t) => t.id);
    const buttons = uniqBy((body?.body_type?.body_buttons || []).map((b) => b.button_image).filter(Boolean), (b) => b.id);
    const linings = fabric.custom_linings || [];

    return [
        { title: "Lapels", count: lapelCats.length, unit: "styles", note: lapels.length > lapelCats.length ? `${lapels.length} widths` : null, diagrams: lapelCats.map((c) => c.diagram) },
        { title: "Shoulders", count: shoulders.length, unit: "constructions", diagrams: shoulders.map((t) => t.diagram) },
        { title: "Pockets", count: pockets.length, unit: "styles", diagrams: pockets.map((t) => t.diagram) },
        { title: "Buttons", count: buttons.length, unit: "finishes", diagrams: buttons.map((b) => b.diagram) },
        { title: "Linings", count: linings.length, unit: "cloths", swatches: linings.map((l) => l.fabric?.image).filter(Boolean) },
    ].filter((g) => g.count > 0);
}

const Details = ({ fabric }) => {
    const groups = detailGroups(fabric);
    if (groups.length === 0) return null;

    return (
        <section className="py-16 bg-white sm:py-20 lg:py-28">
            <div className="px-5 mx-auto max-w-[1600px] sm:px-8 lg:px-12">
                <div className="max-w-2xl" data-reveal>
                    <Eyebrow>Every detail</Eyebrow>
                    <Title className="mt-3">Yours to decide</Title>
                    <p className="mt-5 text-[15px] leading-relaxed text-gray-600 sm:text-base">
                        Nothing on your suit is an afterthought. Each construction is drawn, then rendered on your cloth as you choose it.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-px mt-12 overflow-hidden bg-gray-100 rounded-2xl md:grid-cols-3 lg:grid-cols-5" data-reveal>
                    {groups.map((g) => (
                        <Link key={g.title} href="/design" className="relative flex flex-col p-6 bg-white group lg:p-7 transition-colors duration-500 hover:bg-[#f7f6f3]">
                            <div className="flex items-center h-16 gap-2 lg:h-20">
                                {g.swatches
                                    ? g.swatches.slice(0, 4).map((src, i) => (
                                          <img
                                              key={i}
                                              src={swatchUrl(src, 160)}
                                              alt=""
                                              loading="lazy"
                                              className="object-cover w-10 h-10 -ml-3 transition-transform duration-500 rounded-full shadow ring-2 ring-white first:ml-0 group-hover:translate-x-1"
                                              style={{ transitionDelay: `${i * 40}ms` }}
                                          />
                                      ))
                                    : g.diagrams.slice(0, 3).map((src, i) => (
                                          <img
                                              key={i}
                                              src={swatchUrl(src, 160)}
                                              alt=""
                                              loading="lazy"
                                              className="object-contain transition-transform duration-500 w-14 h-14 lg:w-16 lg:h-16 group-hover:-translate-y-1"
                                              style={{ mixBlendMode: "multiply", transitionDelay: `${i * 40}ms` }}
                                          />
                                      ))}
                            </div>
                            <div className="mt-6">
                                <p className="text-3xl font-light tracking-tight text-gray-900">{g.count}</p>
                                <p className="mt-1 text-sm font-medium text-gray-900">{g.title}</p>
                                <p className="text-xs text-gray-500">
                                    {g.unit}
                                    {g.note ? ` · ${g.note}` : ""}
                                </p>
                            </div>
                            <ArrowUpRight className="absolute w-4 h-4 text-gray-300 transition-all duration-300 top-6 right-6 group-hover:text-gray-900 group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
};

/* ------------------------------------------------------------------ */
/*  Process                                                            */
/* ------------------------------------------------------------------ */
const STEPS = [
    { n: "01", title: "Choose your cloth", body: "Start from the fabric. Every option below adapts to it, so what you see is what is cut." },
    { n: "02", title: "Shape every detail", body: "Lapel, shoulder, pockets, buttons, lining. Each change is rendered on your suit as you make it." },
    { n: "03", title: "Made for you", body: "Your design is saved to your account and handed to the tailors with your measurements." },
];

const Process = () => (
    <section className="py-16 bg-[#141414] text-white sm:py-20 lg:py-28">
        <div className="px-5 mx-auto max-w-[1600px] sm:px-8 lg:px-12">
            <div className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                <div className="lg:col-span-4" data-reveal>
                    <p className="text-[11px] font-semibold tracking-[0.2em] text-white/50 uppercase">How it works</p>
                    <h2 className="mt-3 text-4xl font-light leading-[1.05] tracking-tight sm:text-5xl">Three steps to a suit that is only yours</h2>
                    <div className="mt-8">
                        <Link href="/design" className="btn-ink btn-ink--light">
                            Start designing <ArrowRight className="w-4 h-4" />
                        </Link>
                    </div>
                </div>
                <ol className="grid gap-px overflow-hidden bg-white/10 rounded-2xl lg:col-span-8 md:grid-cols-3">
                    {STEPS.map((step, i) => (
                        <li key={step.n} className="p-7 bg-[#141414] lg:p-9" data-reveal style={{ transitionDelay: `${i * 120}ms` }}>
                            <span className="text-sm font-medium text-white/40">{step.n}</span>
                            <h3 className="mt-6 text-xl font-medium tracking-tight">{step.title}</h3>
                            <p className="mt-3 text-sm leading-relaxed text-white/60">{step.body}</p>
                        </li>
                    ))}
                </ol>
            </div>
        </div>
    </section>
);

/* Cloudinary video: capped width, auto codec/quality; the poster is a frame from the video unless an image is set. */
const CLOUDINARY_VIDEO = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/video\/upload\/)(v\d+\/.+)$/;
const videoUrl = (url) => {
    const m = url && CLOUDINARY_VIDEO.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_1600,c_limit/${m[2]}` : url;
};
const videoPoster = (url) => {
    const m = url && CLOUDINARY_VIDEO.exec(url);
    return m ? `${m[1]}so_0,f_auto,q_auto,w_1600,c_limit/${m[2].replace(/\.[a-z0-9]+$/i, ".jpg")}` : null;
};

const Designer = ({ image, video }) => (
    <section id="how-it-works" className="py-16 bg-[#f7f6f3] sm:py-24 lg:py-32 scroll-mt-16">
        <div className="grid items-center gap-12 px-5 mx-auto max-w-[1600px] sm:px-8 lg:px-12 lg:grid-cols-12 lg:gap-16">
            <div className="lg:col-span-5" data-reveal>
                <Title>High-tech tailoring for every body</Title>
                <p className="max-w-md mt-8 text-[17px] leading-relaxed text-gray-700">
                    When your clothes are made with care, you can feel it. Before our tailors handcraft your suit, our algorithm uses a
                    decade&rsquo;s worth of sizing data to make sure it fits you right. Hard to believe, easy to prove.
                </p>
                <div className="mt-8">
                    <Pill href="/design" inertia>
                        Open the designer <ArrowRight className="w-4 h-4" />
                    </Pill>
                </div>
            </div>

            <div className="lg:col-span-7" data-reveal>
                <div className="p-2 sm:p-3 rounded-[22px] bg-[#1e1e1e] shadow-[0_30px_80px_-30px_rgba(0,0,0,0.45)]">
                    <Photo src={swatchUrl(image, 1600)} alt="The Custom Tailor suit designer" tone="stone" className="relative aspect-[16/10] rounded-[14px] bg-white">
                        {video && (
                            <video
                                src={videoUrl(video)}
                                poster={image ? swatchUrl(image, 1600) : videoPoster(video)}
                                autoPlay
                                muted
                                loop
                                playsInline
                                preload="metadata"
                                aria-label="The Custom Tailor suit designer in action"
                                className="absolute inset-0 object-cover w-full h-full"
                            />
                        )}
                        {!video && !image && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 text-center">
                                <span className="text-[11px] font-semibold tracking-[0.2em] text-gray-500 uppercase">Fabric · Style · Accents</span>
                                <Link
                                    href="/design"
                                    className="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-900 rounded-full transition-colors hover:bg-gray-700"
                                >
                                    See it live <ArrowUpRight className="w-4 h-4" />
                                </Link>
                            </div>
                        )}
                    </Photo>
                </div>
            </div>
        </div>
    </section>
);

const Split = ({ photo, alt, tone, flip = false, title, children, cta, ctaHref, id }) => (
    <section id={id} className="grid lg:grid-cols-2 scroll-mt-16">
        <div className={`relative min-h-[60vw] lg:min-h-[640px] ${flip ? "lg:order-2" : ""}`} data-reveal>
            <Photo src={swatchUrl(photo, 1400)} alt={alt} tone={tone} className="absolute inset-0" />
        </div>
        <div className="flex items-center justify-center px-6 py-16 sm:px-12 lg:px-16 lg:py-24" data-reveal>
            <div className="max-w-md text-center">
                <Title>{title}</Title>
                <p className="mt-6 text-[15px] leading-relaxed text-gray-600 sm:text-base">{children}</p>
                <div className="mt-8">
                    <Pill href={ctaHref} inertia={ctaHref.startsWith("/")}>{cta}</Pill>
                </div>
            </div>
        </div>
    </section>
);

const COLLAGE = [
    "top-[4%] left-[14%] w-[24%] aspect-[3/4]",
    "top-[40%] left-[2%] w-[15%] aspect-[3/4]",
    "top-[24%] left-[42%] w-[14%] aspect-[3/4]",
    "top-[2%] right-[2%] w-[14%] aspect-[3/4]",
    "bottom-[2%] right-[14%] w-[22%] aspect-[4/3]",
];

/* One real-life photo per fabric — the first — scattered like the reference, until the slots are full. */
function collagePictures(fabrics) {
    return fabrics
        .map((fabric) => realLifeOf(fabric)[0] && { ...realLifeOf(fabric)[0], key: fabric.id })
        .filter(Boolean)
        .slice(0, COLLAGE.length);
}

const Collage = ({ fabrics }) => {
    const pictures = collagePictures(fabrics);
    return (
    <section className="relative overflow-hidden bg-[#b7583f] text-white">
        <div className="relative grid px-5 py-20 mx-auto max-w-[1600px] sm:px-8 lg:px-12 lg:grid-cols-2 lg:min-h-[640px] lg:py-28">
            <div className="relative hidden lg:block" aria-hidden="true">
                {pictures.map((picture, i) => (
                    <div
                        key={picture.key}
                        className={`absolute overflow-hidden shadow-[0_24px_60px_-24px_rgba(0,0,0,0.5)] ${COLLAGE[i]}`}
                        data-reveal
                        style={{ transitionDelay: `${i * 90}ms` }}
                    >
                        <img src={swatchUrl(picture.url, 700)} alt={picture.caption || ""} className="object-cover w-full h-full" loading="lazy" />
                    </div>
                ))}
            </div>

            <div className="flex items-center" data-reveal>
                <div className="max-w-lg lg:pl-12">
                    <h2 className="text-4xl font-light leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
                        Perfect fit garments, to your specifications
                    </h2>
                    <p className="mt-6 text-[15px] leading-relaxed text-white/90 sm:text-base">
                        From fabrics and buttons to pocket styles and lining colours, personalise your handcrafted look. Take control and
                        feel confident with our Perfect Fit Guarantee.
                    </p>
                    <div className="flex gap-3 mt-8 lg:hidden" aria-hidden="true">
                        {pictures.slice(0, 3).map((picture) => (
                            <img key={picture.key} src={swatchUrl(picture.url, 400)} alt={picture.caption || ""} className="object-cover w-1/3 aspect-[3/4] shadow-lg" />
                        ))}
                    </div>
                    <div className="mt-8">
                        <Pill href="/design" inertia dark>
                            Start designing <ArrowRight className="w-4 h-4" />
                        </Pill>
                    </div>
                </div>
            </div>
        </div>
    </section>

    );
};

const FOOTER_LINKS = [
    { title: "Custom suits", links: [["Design your suit", "/design"], ["Fabrics", "#fabrics"], ["How it works", "#how-it-works"]] },
    { title: "Company", links: [["About us", "#"], ["Perfect Fit Guarantee", "#"], ["Blog", "#"]] },
    { title: "Support", links: [["Contact us", "#"], ["Order fabric samples", "#"], ["Track order", "#"], ["FAQs", "#"]] },
];

const PAYMENTS = ["VISA", "Mastercard", "PayPal", "Apple Pay", "Amex"];

/* Uploaded logos when there are any; otherwise the text badges (or nothing). */
const LogoRow = ({ title, logos, fallback = [] }) => {
    if (logos.length === 0 && fallback.length === 0) return null;
    return (
        <div>
            <p className="text-sm font-semibold text-gray-900">{title}</p>
            <ul className="flex flex-wrap items-center gap-2 mt-4">
                {logos.length > 0
                    ? logos.map((url, i) => (
                          <li key={url} className="flex items-center justify-center h-9 px-1 overflow-hidden bg-white border border-gray-200 rounded">
                              <img src={swatchUrl(url, 160)} alt="" className="object-contain h-7 w-auto max-w-[3.75rem]" loading="lazy" />
                          </li>
                      ))
                    : fallback.map((p) => (
                          <li key={p} className="px-3 py-1.5 text-[11px] font-semibold tracking-wide text-gray-700 uppercase border border-gray-200 rounded">
                              {p}
                          </li>
                      ))}
            </ul>
        </div>
    );
};

const Footer = ({ socials, paymentLogos, shippingLogos }) => {
    const [email, setEmail] = useState("");
    const [sent, setSent] = useState(false);
    const socialEntries = Object.entries(socials).filter(([network]) => SOCIAL_ICONS[network]);

    return (
        <footer className="pt-16 bg-white border-t border-gray-100 sm:pt-20">
            <div className="grid gap-12 px-5 mx-auto max-w-[1600px] sm:px-8 lg:px-12 lg:grid-cols-12 lg:gap-8">
                <div className="lg:col-span-4">
                    <p className="text-sm font-semibold text-gray-900">Subscribe to our newsletter to get updates</p>
                    <form
                        className="flex items-center max-w-sm mt-5 border-b border-gray-900"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setSent(true);
                        }}
                    >
                        <label htmlFor="newsletter-email" className="sr-only">Email address</label>
                        <input
                            id="newsletter-email"
                            type="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="Email Address"
                            className="flex-1 py-2 text-base text-gray-900 placeholder-gray-400 bg-transparent border-0 focus:ring-0 px-0"
                        />
                        <button type="submit" aria-label="Subscribe" className="p-2 -mr-2 text-gray-900 transition-transform hover:translate-x-0.5">
                            <ArrowRight className="w-5 h-5" />
                        </button>
                    </form>
                    <p className={`mt-3 text-xs text-gray-500 transition-opacity ${sent ? "opacity-100" : "opacity-0"}`} aria-live="polite">
                        Thanks — you&rsquo;re on the list.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-8">
                    {FOOTER_LINKS.map((col) => (
                        <div key={col.title}>
                            <p className="text-sm font-semibold text-gray-900">{col.title}</p>
                            <ul className="mt-4 space-y-2.5">
                                {col.links.map(([label, href]) => (
                                    <li key={label}>
                                        {href.startsWith("/") ? (
                                            <Link href={href} className="text-sm text-gray-600 transition-colors hover:text-gray-900">{label}</Link>
                                        ) : (
                                            <a href={href} className="text-sm text-gray-600 transition-colors hover:text-gray-900">{label}</a>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}

                    <div className="flex flex-wrap col-span-2 gap-x-12 gap-y-8 sm:col-span-3">
                        <LogoRow title="Payment methods" logos={paymentLogos} fallback={PAYMENTS} />
                        <LogoRow title="Shipping partners" logos={shippingLogos} />
                    </div>
                </div>
            </div>

            {socialEntries.length > 0 && (
                <div className="px-5 mx-auto mt-14 max-w-[1600px] sm:px-8 lg:px-12">
                    <div className="flex items-center gap-4 text-gray-900">
                        {socialEntries.map(([network, href]) => (
                            <a
                                key={network}
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={SOCIAL_LABELS[network]}
                                className="transition-opacity hover:opacity-60"
                            >
                                {SOCIAL_ICONS[network]("w-5 h-5")}
                            </a>
                        ))}
                    </div>
                </div>
            )}

            <div className="mt-14 bg-[#f7f6f3]">
                <div className="flex flex-col gap-2 px-5 py-5 mx-auto text-xs text-gray-500 max-w-[1600px] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
                    <span>Copyright {new Date().getFullYear()} Custom Tailor</span>
                    <span>
                        <a href="#" className="hover:text-gray-900">Terms and Conditions</a> · <a href="#" className="hover:text-gray-900">Privacy Policy</a>
                    </span>
                </div>
            </div>
        </footer>
    );
};

/* ------------------------------------------------------------------ */
/*  Page                                                               */
/* ------------------------------------------------------------------ */
export default function Home({ homepage }) {
    const user = usePage().props.auth?.user ?? null;
    const socials = homepage?.socials ?? {};
    const images = homepage?.images ?? {};
    const [fabrics, setFabrics] = useState([]);
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        let alive = true;
        fetch("/api/configurator", { headers: { Accept: "application/json" } })
            .then((r) => (r.ok ? r.json() : null))
            .then((payload) => alive && payload?.success && setFabrics(payload.data || []))
            .catch(() => {})
            .finally(() => alive && setLoaded(true));
        return () => {
            alive = false;
        };
    }, []);

    useReveal([fabrics]);

    const featured = fabrics.find((f) => f.is_default) || fabrics[0];
    const marquee = [
        "Made to measure",
        fabrics.length ? `${fabrics.length} fabrics` : "Fine fabrics",
        "Rendered as you design",
        "Custom linings",
        "Perfect fit guarantee",
        "Saved to your account",
    ];

    return (
        <div className="min-h-screen text-gray-900 bg-white">
            <Head title="Custom suits, made to fit you" />
            <Header user={user} />

            <main>
                <Hero image={images.hero} texture={featured?.image} />
                <Marquee items={marquee} />
                <Collection fabrics={fabrics} loaded={loaded} />
                <Details fabric={featured} />
                <Designer image={images.designer} video={homepage?.video} />
                <Process />
                <Split
                    photo={images.planet}
                    alt="Rolls of natural fabric"
                    tone="sand"
                    title={<>Our planet<br />appreciates it</>}
                    cta="Learn how it's made"
                    ctaHref="#how-it-works"
                >
                    Feel great about your clothes and your environmental impact. There&rsquo;s no waste when you wear one-of-a-kind.
                </Split>
                <Split
                    flip
                    photo={images.tailor}
                    alt="A tailor taking measurements"
                    tone="graphite"
                    title="Looks that last"
                    cta="Explore fabrics"
                    ctaHref="#fabrics"
                >
                    We know you pay attention to detail, and so do we. From durable fabrics to our quality-controlled tailoring process,
                    be assured that your suit is timeless.
                </Split>
                <Collage fabrics={fabrics} />
            </main>

            <Footer socials={socials} paymentLogos={homepage?.payment_logos ?? []} shippingLogos={homepage?.shipping_logos ?? []} />
        </div>
    );
}
