import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, Menu, Settings, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const NAV = [
    { label: 'Overview', href: () => route('dashboard'), active: () => route().current('dashboard') },
    { label: 'Design a suit', href: () => '/design', active: () => false },
    { label: 'Fabrics', href: () => '/#fabrics', active: () => false },
];

const initials = (name = '') =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('') || '·';

/*
 * Account shell: a slim top bar in the storefront's ink-and-stone palette.
 * `title` / `eyebrow` / `actions` head the page; `children` is the body.
 */
export default function AuthenticatedLayout({ eyebrow, title, subtitle, actions, children }) {
    const user = usePage().props.auth.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);
    const accountRef = useRef(null);

    useEffect(() => {
        if (!accountOpen) return undefined;
        const onDown = (e) => accountRef.current && !accountRef.current.contains(e.target) && setAccountOpen(false);
        const onKey = (e) => e.key === 'Escape' && setAccountOpen(false);
        document.addEventListener('mousedown', onDown);
        window.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            window.removeEventListener('keydown', onKey);
        };
    }, [accountOpen]);

    return (
        <div className="min-h-dvh bg-[#f7f6f3] text-gray-900">
            <header className="sticky top-0 z-40 bg-white/90 backdrop-blur-md shadow-[0_1px_0_rgba(0,0,0,0.06)]">
                <div className="mx-auto flex h-16 max-w-[1400px] items-center justify-between px-5 sm:px-8">
                    <div className="flex items-center gap-10">
                        <Link href="/" className="text-[22px] font-semibold tracking-tight">
                            Custom Tailor
                        </Link>
                        <nav className="hidden items-center gap-7 md:flex" aria-label="Account">
                            {NAV.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href()}
                                    className={`nav-link text-[14px] font-medium tracking-tight ${item.active() ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900'}`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link href="/design" className="btn-ink hidden !py-2.5 !px-5 bg-gray-900 text-white hover:bg-gray-700 sm:inline-flex">
                            Design a suit
                        </Link>

                        <div className="relative" ref={accountRef}>
                            <button
                                type="button"
                                onClick={() => setAccountOpen((v) => !v)}
                                aria-expanded={accountOpen}
                                aria-haspopup="menu"
                                className="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition-colors hover:bg-gray-100"
                            >
                                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-gray-900 text-[12px] font-semibold tracking-wide text-white">
                                    {initials(user.name)}
                                </span>
                                <span className="hidden max-w-[10rem] truncate text-sm font-medium sm:block">{user.name}</span>
                                <ChevronDown className={`hidden h-4 w-4 text-gray-400 transition-transform sm:block ${accountOpen ? 'rotate-180' : ''}`} />
                            </button>

                            {accountOpen && (
                                <div
                                    role="menu"
                                    className="absolute right-0 mt-2 w-64 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-[0_20px_50px_-20px_rgba(0,0,0,0.25)] animate-slide-down"
                                >
                                    <div className="border-b border-gray-100 px-4 py-3">
                                        <p className="truncate text-sm font-medium">{user.name}</p>
                                        <p className="truncate text-xs text-gray-500">{user.email}</p>
                                    </div>
                                    <div className="p-1.5">
                                        <Link
                                            href={route('profile.edit')}
                                            role="menuitem"
                                            className="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                                        >
                                            <Settings className="h-4 w-4" /> Account settings
                                        </Link>
                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            role="menuitem"
                                            className="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                                        >
                                            <LogOut className="h-4 w-4" /> Sign out
                                        </Link>
                                    </div>
                                </div>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => setMenuOpen((v) => !v)}
                            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={menuOpen}
                            className="rounded-full p-2 text-gray-700 transition-colors hover:bg-gray-100 md:hidden"
                        >
                            {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                </div>

                {menuOpen && (
                    <nav className="border-t border-gray-100 bg-white px-5 py-3 md:hidden animate-slide-down" aria-label="Account">
                        {NAV.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href()}
                                onClick={() => setMenuOpen(false)}
                                className={`block rounded-xl px-3 py-2.5 text-[15px] font-medium ${item.active() ? 'bg-gray-100 text-gray-900' : 'text-gray-600'}`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                )}
            </header>

            <main className="mx-auto max-w-[1400px] px-5 pb-20 pt-10 sm:px-8 sm:pt-14">
                {(title || actions) && (
                    <div className="mb-10 flex flex-wrap items-end justify-between gap-6 animate-slide-up">
                        <div>
                            {eyebrow && <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">{eyebrow}</p>}
                            {title && <h1 className="mt-2 text-4xl font-light tracking-tight sm:text-5xl">{title}</h1>}
                            {subtitle && <p className="mt-3 max-w-xl text-[15px] text-gray-500">{subtitle}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap gap-3">{actions}</div>}
                    </div>
                )}

                {children}
            </main>
        </div>
    );
}
