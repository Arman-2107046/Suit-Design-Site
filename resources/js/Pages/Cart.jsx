import StoreLayout from "@/Layouts/StoreLayout";
import { cartSubtotal, money, removeFromCart, setCartQuantity, useBodyProfile, useCart } from "@/lib/store";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, Check, Minus, Plus, Ruler, Trash2 } from "lucide-react";

const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
const cdn = (url, width) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_${width}/${m[2]}` : url;
};

const Card = ({ className = "", children, ...rest }) => (
    <div {...rest} className={`rounded-3xl bg-white shadow-[0_1px_0_rgba(0,0,0,0.04),0_20px_50px_-30px_rgba(0,0,0,0.15)] ${className}`}>
        {children}
    </div>
);

export default function Cart() {
    const items = useCart();
    const profile = useBodyProfile();
    const subtotal = cartSubtotal(items);
    const next = profile ? route("checkout") : route("checkout.body-profile");

    return (
        <StoreLayout eyebrow="Your bag" title={items.length ? "My shopping bag" : "Your bag is empty"} back={{ href: "/design", label: "Back to the designer" }}>
            <Head title="Bag" />

            {items.length === 0 ? (
                <Card className="p-10 text-center animate-slide-up">
                    <p className="mx-auto max-w-md text-[15px] text-gray-500">
                        Design a suit, add it to your bag, and we will take it from there — measurements, delivery, and payment.
                    </p>
                    <Link href="/design" className="btn-ink mt-8 bg-gray-900 text-white hover:bg-gray-700">
                        Design a suit <ArrowRight className="h-4 w-4" />
                    </Link>
                </Card>
            ) : (
                <div className="grid gap-6 lg:grid-cols-12 lg:gap-8">
                    <div className="space-y-4 lg:col-span-8">
                        {items.map((item, i) => (
                            <Card key={item.id} className="p-5 sm:p-6 animate-slide-up" style={{ animationDelay: `${i * 60}ms` }}>
                                <div className="flex gap-5 sm:gap-7">
                                    <div className="relative h-28 w-24 shrink-0 overflow-hidden rounded-2xl bg-[#efece6] sm:h-36 sm:w-32">
                                        {item.fabricImage && <img src={cdn(item.fabricImage, 320)} alt={item.fabricName} className="absolute inset-0 h-full w-full object-cover" />}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-400">Custom suit</p>
                                                <h2 className="mt-1 text-xl font-medium tracking-tight">{item.fabricName}</h2>
                                                <p className="mt-0.5 text-sm text-gray-500">Tailored and delivered in about 3 weeks</p>
                                            </div>
                                            <p className="text-lg font-medium">{money(item.price * (item.quantity || 1))}</p>
                                        </div>

                                        <ul className="mt-4 flex flex-wrap gap-1.5">
                                            {(item.summary || []).map((row) => (
                                                <li key={row.label} className="rounded-full bg-[#f7f6f3] px-2.5 py-1 text-[11px]">
                                                    <span className="text-gray-400">{row.label} · </span>
                                                    <span className="font-medium text-gray-700">{row.value}</span>
                                                </li>
                                            ))}
                                        </ul>

                                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                                            <div className="inline-flex items-center rounded-full border border-gray-200">
                                                <button type="button" onClick={() => setCartQuantity(item.id, (item.quantity || 1) - 1)} aria-label="Decrease quantity" className="p-2 text-gray-500 transition-colors hover:text-gray-900">
                                                    <Minus className="h-3.5 w-3.5" />
                                                </button>
                                                <span className="w-6 text-center text-sm font-medium">{item.quantity || 1}</span>
                                                <button type="button" onClick={() => setCartQuantity(item.id, (item.quantity || 1) + 1)} aria-label="Increase quantity" className="p-2 text-gray-500 transition-colors hover:text-gray-900">
                                                    <Plus className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <div className="flex items-center gap-4 text-sm">
                                                <Link href={`/design?fabric=${item.fabricId}`} className="text-gray-500 transition-colors hover:text-gray-900">
                                                    Edit design
                                                </Link>
                                                <button type="button" onClick={() => removeFromCart(item.id)} className="inline-flex items-center gap-1.5 text-gray-500 transition-colors hover:text-red-700">
                                                    <Trash2 className="h-3.5 w-3.5" /> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>

                    <div className="space-y-4 lg:col-span-4 animate-slide-up [animation-delay:120ms]">
                        <Card className="p-6">
                            <p className="text-center text-sm font-medium text-emerald-700">Free shipping on every suit</p>
                            <p className="mt-1 text-center text-xs text-gray-500">Tailored and delivered in about 3 weeks, tracked</p>
                            <dl className="mt-6 space-y-2 text-sm">
                                <div className="flex justify-between"><dt className="text-gray-500">Bag total</dt><dd>{money(subtotal)}</dd></div>
                                <div className="flex justify-between"><dt className="text-gray-500">Shipping</dt><dd className="font-medium text-emerald-700">Free</dd></div>
                            </dl>
                            <div className="mt-4 flex items-baseline justify-between border-t border-gray-100 pt-4">
                                <span className="text-lg font-medium">Total</span>
                                <span className="text-2xl font-light tracking-tight">{money(subtotal)}</span>
                            </div>
                            <Link href={next} className="btn-ink mt-6 w-full justify-center bg-gray-900 py-4 text-white hover:bg-gray-700">
                                {profile ? "Checkout" : "Create body profile"} <ArrowRight className="h-4 w-4" />
                            </Link>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-start gap-3">
                                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${profile ? "bg-emerald-50 text-emerald-700" : "bg-[#f7f6f3] text-gray-700"}`}>
                                    {profile ? <Check className="h-4 w-4" /> : <Ruler className="h-4 w-4" />}
                                </span>
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">{profile ? `Body profile · ${profile.name}` : "Body profile"}</p>
                                    <p className="mt-0.5 text-xs text-gray-500">
                                        {profile
                                            ? `${profile.height_cm} cm · ${profile.weight_kg} kg · ${profile.age} years`
                                            : "Three numbers and we estimate your measurements. You review them before ordering."}
                                    </p>
                                    <Link href={route("checkout.body-profile")} className="mt-2 inline-block text-xs font-medium text-gray-900 underline-offset-4 hover:underline">
                                        {profile ? "Change profile" : "Create now"}
                                    </Link>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            )}
        </StoreLayout>
    );
}
