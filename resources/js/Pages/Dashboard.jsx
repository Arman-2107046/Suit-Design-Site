import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { cartCount, useCart } from '@/lib/store';
import { ArrowRight, ArrowUpRight, Package, Ruler, Scissors, Shirt, ShoppingBag, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';

/* Same key the designer saves to. */
const STORAGE_KEY = 'custom-tailor.design.v1';

const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
const cdn = (url, width) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_${width}/${m[2]}` : url;
};

const money = (value) => `$${Math.round(Number(value) || 0)}`;

const greeting = () => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
};

const memberSince = (iso) =>
    iso ? new Date(iso).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) : null;

function loadSavedDesign() {
    try {
        const parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY));
        return parsed && typeof parsed === 'object' && parsed.intent ? parsed : null;
    } catch {
        return null;
    }
}

/* The choices a saved design carries, as readable chips. */
function designChips(intent) {
    if (!intent) return [];
    const chips = [];
    if (intent.body?.name) chips.push(['Body', intent.body.name]);
    if (intent.lapel?.categoryName) chips.push(['Lapel', [intent.lapel.categoryName, intent.lapel.subcategoryName].filter(Boolean).join(' · ')]);
    if (intent.sleeve?.name) chips.push(['Shoulder', intent.sleeve.name]);
    if (intent.sidePocket?.name) chips.push(['Side pockets', intent.sidePocket.name]);
    if (intent.chestPocket?.name) chips.push(['Chest pocket', intent.chestPocket.name]);
    chips.push(['Buttons', intent.button?.name || 'Default']);
    chips.push(['Lining', intent.lining?.mode === 'custom' && intent.lining.name ? intent.lining.name : 'Default']);
    return chips;
}

const Card = ({ className = '', children }) => (
    <div className={`rounded-3xl bg-white shadow-[0_1px_0_rgba(0,0,0,0.04),0_20px_50px_-30px_rgba(0,0,0,0.15)] ${className}`}>{children}</div>
);

const SectionLabel = ({ children }) => <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">{children}</p>;

export default function Dashboard({ orders = [], profiles = [] }) {
    const user = usePage().props.auth.user;
    const firstName = (user.name || '').split(' ')[0];

    const [fabrics, setFabrics] = useState([]);
    const [saved, setSaved] = useState(null);
    const bagCount = cartCount(useCart());
    const openOrders = orders.filter((o) => !['delivered', 'cancelled'].includes(o.status.value));

    useEffect(() => {
        setSaved(loadSavedDesign());
        let alive = true;
        fetch('/api/configurator', { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((payload) => alive && payload?.success && setFabrics(payload.data || []))
            .catch(() => {});
        return () => {
            alive = false;
        };
    }, []);

    const savedFabric = saved && fabrics.find((f) => f.id === saved.fabricId);
    const featured = savedFabric || fabrics.find((f) => f.is_default) || fabrics[0];
    const chips = savedFabric ? designChips(saved.intent) : [];
    const fromPrice = fabrics.length ? Math.min(...fabrics.map((f) => Number(f.price) || 0)) : null;

    return (
        <AuthenticatedLayout
            eyebrow="Your atelier"
            title={`${greeting()}, ${firstName || 'there'}`}
            subtitle="Everything about your suit lives here — the design in progress, your fabrics, and your account."
            actions={
                <Link href="/design" className="btn-ink bg-gray-900 text-white hover:bg-gray-700">
                    {savedFabric ? 'Continue designing' : 'Design a suit'} <ArrowRight className="h-4 w-4" />
                </Link>
            }
        >
            <Head title="Overview" />

            <div className="grid gap-6 lg:grid-cols-12">
                {/* In-progress suit */}
                <Card className="relative overflow-hidden bg-[#141414] text-white lg:col-span-8 animate-slide-up">
                    {featured?.image && (
                        <img
                            src={cdn(featured.image, 900)}
                            alt=""
                            aria-hidden="true"
                            className="absolute inset-y-0 right-0 hidden w-1/2 object-cover opacity-90 [mask-image:linear-gradient(to_right,transparent,black_35%)] md:block"
                        />
                    )}
                    <div className="relative flex min-h-[22rem] flex-col justify-between p-7 sm:p-9">
                        <div>
                            <SectionLabel>{savedFabric ? 'Suit in progress' : 'Start your first suit'}</SectionLabel>
                            <h2 className="mt-3 max-w-md text-3xl font-light tracking-tight sm:text-4xl">
                                {savedFabric ? `Your ${savedFabric.name} suit` : 'A suit that is only yours'}
                            </h2>
                            <p className="mt-3 max-w-md text-[15px] text-white/70">
                                {savedFabric
                                    ? `Saved in this browser, picked up exactly where you left it. ${money(savedFabric.price)} in ${savedFabric.name}.`
                                    : `Choose a cloth, shape every detail, and watch it rendered as you go.${fromPrice != null ? ` From ${money(fromPrice)}.` : ''}`}
                            </p>
                        </div>

                        <div className="mt-8">
                            {chips.length > 0 && (
                                <ul className="mb-6 flex flex-wrap gap-2">
                                    {chips.map(([label, value]) => (
                                        <li key={label} className="rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs backdrop-blur">
                                            <span className="text-white/50">{label} · </span>
                                            <span className="font-medium">{value}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <div className="flex flex-wrap gap-3">
                                <Link href="/design" className="btn-ink btn-ink--light">
                                    {savedFabric ? 'Continue designing' : 'Open the designer'} <ArrowRight className="h-4 w-4" />
                                </Link>
                                {savedFabric && (
                                    <a href="/#fabrics" className="btn-ink border-white/40 text-white hover:bg-white/10">
                                        Try another cloth
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Quick actions */}
                <div className="grid gap-6 lg:col-span-4 animate-slide-up [animation-delay:80ms]">
                    {[
                        { icon: Scissors, title: 'Design a suit', body: 'Fabric, style and accents, rendered live.', href: '/design', inertia: true },
                        { icon: Shirt, title: 'Browse fabrics', body: fabrics.length ? `${fabrics.length} cloths, from ${money(fromPrice)}.` : 'Every cloth in the collection.', href: '/#fabrics' },
                        { icon: UserRound, title: 'Account settings', body: 'Name, email and password.', href: route('profile.edit'), inertia: true },
                    ].map(({ icon: Icon, title, body, href, inertia }) => {
                        const inner = (
                            <>
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#f7f6f3] text-gray-900 transition-colors group-hover:bg-gray-900 group-hover:text-white">
                                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-[15px] font-medium">{title}</span>
                                    <span className="block text-sm text-gray-500">{body}</span>
                                </span>
                                <ArrowUpRight className="h-4 w-4 shrink-0 text-gray-300 transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-gray-900" />
                            </>
                        );
                        const cls = 'group flex items-center gap-4 rounded-3xl bg-white p-5 shadow-[0_1px_0_rgba(0,0,0,0.04)] transition-shadow hover:shadow-[0_20px_50px_-30px_rgba(0,0,0,0.2)]';
                        return inertia ? (
                            <Link key={title} href={href} className={cls}>{inner}</Link>
                        ) : (
                            <a key={title} href={href} className={cls}>{inner}</a>
                        );
                    })}
                </div>

                {/* Fabrics */}
                <Card className="p-7 lg:col-span-8 animate-slide-up [animation-delay:160ms]">
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <SectionLabel>The collection</SectionLabel>
                            <h2 className="mt-2 text-2xl font-light tracking-tight">Fabrics</h2>
                        </div>
                        <a href="/#fabrics" className="text-sm text-gray-500 transition-colors hover:text-gray-900">
                            See all
                        </a>
                    </div>
                    <ul className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
                        {(fabrics.length ? fabrics : [null, null, null, null, null]).slice(0, 5).map((fabric, i) => (
                            <li key={fabric?.id ?? i}>
                                {fabric ? (
                                    <Link href={`/design?fabric=${fabric.id}`} className="group block">
                                        <span className="relative block aspect-[4/5] overflow-hidden rounded-2xl bg-[#efece6]">
                                            <img
                                                src={cdn(fabric.image, 480)}
                                                alt={fabric.name}
                                                loading="lazy"
                                                className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                            />
                                            {savedFabric?.id === fabric.id && (
                                                <span className="absolute left-2 top-2 rounded-full bg-white/95 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-gray-900">
                                                    In progress
                                                </span>
                                            )}
                                        </span>
                                        <span className="mt-2.5 flex items-baseline justify-between">
                                            <span className="truncate text-sm font-medium">{fabric.name}</span>
                                            <span className="text-xs text-gray-500">{money(fabric.price)}</span>
                                        </span>
                                    </Link>
                                ) : (
                                    <div className="aspect-[4/5] animate-pulse rounded-2xl bg-[#f1eee9]" aria-hidden="true" />
                                )}
                            </li>
                        ))}
                    </ul>
                </Card>

                {/* Orders + account */}
                <div className="grid gap-6 lg:col-span-4 animate-slide-up [animation-delay:240ms]">
                    {bagCount > 0 && (
                        <Link href={route('cart')} className="group flex items-center gap-4 rounded-3xl bg-gray-900 p-5 text-white shadow-[0_20px_50px_-30px_rgba(0,0,0,0.4)]">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10">
                                <ShoppingBag className="h-[18px] w-[18px]" strokeWidth={1.75} />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-[15px] font-medium">{bagCount} suit{bagCount > 1 ? 's' : ''} in your bag</span>
                                <span className="block text-sm text-white/60">Ready when you are — measurements, delivery, payment.</span>
                            </span>
                            <ArrowUpRight className="h-4 w-4 shrink-0 text-white/50 transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-white" />
                        </Link>
                    )}

                    <Card className="p-7">
                        <div className="flex items-baseline justify-between">
                            <SectionLabel>Orders</SectionLabel>
                            {orders.length > 0 && <span className="text-xs text-gray-500">{openOrders.length} in progress</span>}
                        </div>
                        {orders.length === 0 ? (
                            <div className="mt-5 flex items-start gap-4">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#f7f6f3]">
                                    <Package className="h-5 w-5 text-gray-700" strokeWidth={1.5} />
                                </span>
                                <div>
                                    <p className="text-[15px] font-medium">No orders yet</p>
                                    <p className="mt-1 text-sm text-gray-500">When you place your first order, its progress and tracking will appear here.</p>
                                </div>
                            </div>
                        ) : (
                            <ul className="mt-5 divide-y divide-gray-100">
                                {orders.slice(0, 4).map((order) => (
                                    <li key={order.number} className="py-4 first:pt-0 last:pb-0">
                                        <Link href={route('orders.show', order.number)} className="group block">
                                            <div className="flex items-baseline justify-between gap-3">
                                                <span className="text-sm font-medium">{order.items.map((i) => i.fabric_name).join(', ')}</span>
                                                <span className="text-sm">{money(order.total)}</span>
                                            </div>
                                            <div className="mt-1 flex items-center justify-between gap-3 text-xs text-gray-500">
                                                <span>{order.number} · {new Date(order.placed_at).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}</span>
                                                <span className={`rounded-full px-2 py-0.5 font-medium ${order.status.value === 'delivered' ? 'bg-emerald-50 text-emerald-700' : order.status.value === 'cancelled' ? 'bg-red-50 text-red-700' : 'bg-[#f7f6f3] text-gray-700'}`}>
                                                    {order.status.label}
                                                </span>
                                            </div>
                                            {order.status.value !== 'cancelled' && (
                                                <div className="mt-3 flex gap-1">
                                                    {order.journey.map((s, i) => (
                                                        <span key={s.value} className={`h-1 flex-1 rounded-full ${i <= order.status.step ? 'bg-gray-900' : 'bg-gray-200'}`} />
                                                    ))}
                                                </div>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card className="p-7">
                        <SectionLabel>Account</SectionLabel>
                        <dl className="mt-5 space-y-3 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Name</dt>
                                <dd className="truncate font-medium">{user.name}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Email</dt>
                                <dd className="truncate font-medium">{user.email}</dd>
                            </div>
                            {memberSince(user.created_at) && (
                                <div className="flex justify-between gap-4">
                                    <dt className="text-gray-500">Member since</dt>
                                    <dd className="font-medium">{memberSince(user.created_at)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Body profile</dt>
                                <dd className={`flex items-center gap-1.5 font-medium ${profiles.length ? '' : 'text-gray-400'}`}>
                                    <Ruler className="h-3.5 w-3.5" />
                                    {profiles.length ? `${profiles[0].name} · ${profiles[0].height_cm} cm` : 'Not on file'}
                                </dd>
                            </div>
                        </dl>
                        <div className="mt-6 flex flex-wrap gap-2">
                            <Link href={route('profile.edit')} className="btn-ink btn-ink--outline !py-2.5 !px-5 text-[13px]">
                                Edit account
                            </Link>
                            <Link href={route('checkout.body-profile')} className="btn-ink btn-ink--outline !py-2.5 !px-5 text-[13px]">
                                {profiles.length ? 'New body profile' : 'Create body profile'}
                            </Link>
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
