import { cartCount, useCart } from "@/lib/store";
import { Link } from "@inertiajs/react";
import { ArrowRight, Globe, Menu, ShoppingBag, User, X } from "lucide-react";
import { useEffect, useState } from "react";

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

export const Header = ({ user, solid: alwaysSolid = false }) => {
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

    const solid = alwaysSolid || scrolled || open;
    const ink = solid ? "text-gray-900" : "text-white";
    const link = `nav-link text-[15px] font-medium tracking-tight ${ink}`;

    return (
        <header className="fixed inset-x-0 top-0 z-50">
            <div
                className={`overflow-hidden text-center text-[13px] tracking-wide text-white bg-[#1c1c1c] transition-all duration-300 ${
                    scrolled || alwaysSolid ? "h-0" : "h-9"
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

