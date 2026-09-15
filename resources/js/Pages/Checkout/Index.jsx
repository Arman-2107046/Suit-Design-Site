import InputError from "@/Components/InputError";
import StoreLayout from "@/Layouts/StoreLayout";
import { cartSubtotal, clearCart, money, useBodyProfile, useCart } from "@/lib/store";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowRight, Banknote, Check, ChevronUp, Landmark, Lock, Pencil, Ruler, ShoppingBag } from "lucide-react";
import { useEffect, useState } from "react";

const STEPS = ["Bag", "Body profile", "Delivery & payment", "Done"];

const COUNTRIES = [
    "Bangladesh", "India", "Pakistan", "Sri Lanka", "Nepal", "United Arab Emirates", "Saudi Arabia", "Qatar", "Singapore", "Malaysia",
    "United Kingdom", "Ireland", "Germany", "France", "Spain", "Italy", "Netherlands", "Belgium", "Switzerland", "Austria", "Sweden",
    "Norway", "Denmark", "Finland", "Portugal", "Poland", "United States", "Canada", "Australia", "New Zealand", "Japan", "South Korea",
];

const PAYMENT = {
    cash_on_delivery: { icon: Banknote, title: "Pay on delivery", body: "Pay in cash or by card when your suit arrives." },
    bank_transfer: { icon: Landmark, title: "Bank transfer", body: "We email you our bank details; production starts once the transfer clears." },
};

const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
const cdn = (url, width) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_${width}/${m[2]}` : url;
};

const inputCls = "mt-1.5 block w-full rounded-xl border-gray-200 bg-white px-4 py-3 text-[15px] placeholder-gray-400 focus:border-gray-900 focus:ring-0";

const Field = ({ label, error, children, className = "" }) => (
    <label className={`block ${className}`}>
        <span className="text-[13px] font-medium tracking-wide text-gray-700">{label}</span>
        {children}
        <InputError message={error} className="mt-1.5" />
    </label>
);

const StepHeader = ({ n, title, state, onEdit }) => (
    <div className="flex items-center gap-4">
        <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${state === "todo" ? "bg-gray-200 text-gray-500" : "bg-gray-900 text-white"}`}>
            {state === "done" ? <Check className="h-4 w-4" /> : n}
        </span>
        <h2 className={`flex-1 text-xl font-medium tracking-tight ${state === "todo" ? "text-gray-400" : ""}`}>{title}</h2>
        {state === "done" && (
            <button type="button" onClick={onEdit} className="inline-flex items-center gap-1 text-sm text-gray-500 transition-colors hover:text-gray-900">
                <Pencil className="h-3.5 w-3.5" /> Edit
            </button>
        )}
    </div>
);

export default function Checkout({ profiles, paymentMethods }) {
    const user = usePage().props.auth?.user ?? null;
    const items = useCart();
    const profile = useBodyProfile();
    const subtotal = cartSubtotal(items);

    const [emailDone, setEmailDone] = useState(Boolean(user));
    const [step, setStep] = useState(0); // 0 delivery, 1 details, 2 payment
    const [summaryOpen, setSummaryOpen] = useState(true);

    const form = useForm({
        email: user?.email ?? "",
        shipping: { name: user?.name ?? "", phone: "", address: "", address2: "", city: "", postcode: "", country: "" },
        payment_method: "cash_on_delivery",
        notes: "",
        items: [],
        body_profile: null,
    });

    /* Keep the payload in sync with the bag and profile from localStorage. */
    useEffect(() => {
        form.setData((d) => ({
            ...d,
            items: items.map((i) => ({ fabric_id: i.fabricId, quantity: i.quantity || 1, design: i.design, summary: i.summary })),
            body_profile: profile,
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [items, profile]);

    const ship = (key, value) => form.setData("shipping", { ...form.data.shipping, [key]: value });
    const err = (key) => form.errors[key];

    const deliveryValid = form.data.shipping.address && form.data.shipping.city && form.data.shipping.postcode && form.data.shipping.country;
    const detailsValid = form.data.shipping.name && form.data.shipping.phone && form.data.email;

    const place = (e) => {
        e.preventDefault();
        form.post(route("checkout.store"), {
            onSuccess: () => clearCart(),
            onError: (errors) => {
                if (Object.keys(errors).some((k) => k.startsWith("shipping.address") || k.startsWith("shipping.city") || k.startsWith("shipping.postcode") || k.startsWith("shipping.country"))) setStep(0);
                else if (Object.keys(errors).some((k) => k.startsWith("shipping.name") || k.startsWith("shipping.phone") || k === "email")) setStep(1);
            },
        });
    };

    const empty = items.length === 0;
    const missingProfile = !profile;

    return (
        <StoreLayout steps={STEPS} step={2} secure back={{ href: route("cart"), label: "Back to bag" }} wide>
            <Head title="Checkout" />

            {empty || missingProfile ? (
                <div className="mx-auto max-w-md rounded-3xl bg-white p-10 text-center shadow-[0_1px_0_rgba(0,0,0,0.04)] animate-slide-up">
                    <p className="text-2xl font-light tracking-tight">{empty ? "Your bag is empty" : "One more step"}</p>
                    <p className="mt-3 text-sm text-gray-500">
                        {empty ? "Add a suit to your bag before checking out." : "We need your body profile so the suit is cut to you."}
                    </p>
                    <Link href={empty ? "/design" : route("checkout.body-profile")} className="btn-ink mt-8 bg-gray-900 text-white hover:bg-gray-700">
                        {empty ? "Design a suit" : "Create body profile"} <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            ) : !emailDone ? (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (form.data.email) setEmailDone(true);
                    }}
                    className="mx-auto max-w-md pt-6 text-center animate-slide-up"
                >
                    <h1 className="text-3xl font-light tracking-tight sm:text-4xl">Where should we send your order confirmation?</h1>
                    <p className="mt-3 text-sm text-gray-500">Your digital body profile will be saved to this email too.</p>
                    <Field label="E-mail" className="mt-8 text-left" error={err("email")}>
                        <input type="email" required autoFocus value={form.data.email} onChange={(e) => form.setData("email", e.target.value)} placeholder="you@example.com" className={inputCls} />
                    </Field>
                    <button type="submit" className="btn-ink mt-6 w-full justify-center bg-gray-900 py-4 text-white hover:bg-gray-700">
                        Continue to delivery <ArrowRight className="h-4 w-4" />
                    </button>
                    <p className="mt-6 text-sm text-gray-500">
                        Have an account?{" "}
                        <Link href={route("login")} className="font-medium text-gray-900 underline-offset-4 hover:underline">
                            Sign in
                        </Link>
                    </p>
                </form>
            ) : (
                <form onSubmit={place} className="grid gap-10 lg:grid-cols-12 lg:gap-14">
                    <div className="space-y-2 lg:col-span-7">
                        <h1 className="mb-8 text-3xl font-light tracking-tight sm:text-4xl animate-slide-up">Checkout</h1>

                        {/* 1 · Delivery */}
                        <section className="border-t border-gray-200 py-7 animate-slide-up">
                            <StepHeader n={1} title="How should we deliver your order?" state={step === 0 ? "current" : step > 0 ? "done" : "todo"} onEdit={() => setStep(0)} />
                            {step === 0 ? (
                                <div className="mt-6 space-y-4 pl-12">
                                    <p className="-mt-2 text-sm text-gray-500">Delivered to your door, pressed and ready for its first outing.</p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Postcode" error={err("shipping.postcode")}>
                                            <input value={form.data.shipping.postcode} onChange={(e) => ship("postcode", e.target.value)} placeholder="Example: 1212" className={inputCls} />
                                        </Field>
                                        <Field label="Country" error={err("shipping.country")}>
                                            <select value={form.data.shipping.country} onChange={(e) => ship("country", e.target.value)} className={inputCls}>
                                                <option value="">Select a country</option>
                                                {COUNTRIES.map((c) => (
                                                    <option key={c}>{c}</option>
                                                ))}
                                            </select>
                                        </Field>
                                    </div>
                                    <Field label="Street address" error={err("shipping.address")}>
                                        <input value={form.data.shipping.address} onChange={(e) => ship("address", e.target.value)} placeholder="House, road, area" className={inputCls} />
                                    </Field>
                                    <Field label="Apartment, floor (optional)" error={err("shipping.address2")}>
                                        <input value={form.data.shipping.address2} onChange={(e) => ship("address2", e.target.value)} className={inputCls} />
                                    </Field>
                                    <Field label="City" error={err("shipping.city")}>
                                        <input value={form.data.shipping.city} onChange={(e) => ship("city", e.target.value)} className={inputCls} />
                                    </Field>
                                    <button type="button" disabled={!deliveryValid} onClick={() => setStep(1)} className="btn-ink mt-2 bg-gray-900 text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400">
                                        Continue <ArrowRight className="h-4 w-4" />
                                    </button>
                                </div>
                            ) : step > 0 ? (
                                <p className="mt-3 pl-12 text-sm text-gray-500">
                                    {[form.data.shipping.address, form.data.shipping.address2, form.data.shipping.city, form.data.shipping.postcode, form.data.shipping.country].filter(Boolean).join(", ")}
                                </p>
                            ) : null}
                        </section>

                        {/* 2 · Details */}
                        <section className="border-t border-gray-200 py-7 animate-slide-up [animation-delay:60ms]">
                            <StepHeader n={2} title="Your details" state={step === 1 ? "current" : step > 1 ? "done" : "todo"} onEdit={() => setStep(1)} />
                            {step === 1 ? (
                                <div className="mt-6 space-y-4 pl-12">
                                    <Field label="Full name" error={err("shipping.name")}>
                                        <input value={form.data.shipping.name} onChange={(e) => ship("name", e.target.value)} autoComplete="name" className={inputCls} />
                                    </Field>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Phone" error={err("shipping.phone")}>
                                            <input value={form.data.shipping.phone} onChange={(e) => ship("phone", e.target.value)} autoComplete="tel" placeholder="+880 …" className={inputCls} />
                                        </Field>
                                        <Field label="E-mail" error={err("email")}>
                                            <input type="email" value={form.data.email} onChange={(e) => form.setData("email", e.target.value)} disabled={Boolean(user)} className={`${inputCls} disabled:bg-gray-50 disabled:text-gray-500`} />
                                        </Field>
                                    </div>
                                    <Field label="Notes for the tailor (optional)" error={err("notes")}>
                                        <textarea rows={3} value={form.data.notes} onChange={(e) => form.setData("notes", e.target.value)} placeholder="Anything we should know — posture, preferences, a deadline." className={inputCls} />
                                    </Field>
                                    <button type="button" disabled={!detailsValid} onClick={() => setStep(2)} className="btn-ink mt-2 bg-gray-900 text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400">
                                        Continue <ArrowRight className="h-4 w-4" />
                                    </button>
                                </div>
                            ) : step > 1 ? (
                                <p className="mt-3 pl-12 text-sm text-gray-500">
                                    {form.data.shipping.name} · {form.data.shipping.phone} · {form.data.email}
                                </p>
                            ) : null}
                        </section>

                        {/* 3 · Payment */}
                        <section className="border-t border-b border-gray-200 py-7 animate-slide-up [animation-delay:120ms]">
                            <StepHeader n={3} title="How do you want to pay?" state={step === 2 ? "current" : "todo"} />
                            {step === 2 && (
                                <div className="mt-6 space-y-3 pl-12">
                                    {paymentMethods.map((method) => {
                                        const meta = PAYMENT[method];
                                        const Icon = meta.icon;
                                        const active = form.data.payment_method === method;
                                        return (
                                            <label key={method} className={`flex cursor-pointer items-start gap-4 rounded-2xl border p-4 transition-colors ${active ? "border-gray-900 bg-white" : "border-gray-200 bg-white/60 hover:border-gray-400"}`}>
                                                <input type="radio" name="payment_method" value={method} checked={active} onChange={() => form.setData("payment_method", method)} className="mt-1 h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-900/30" />
                                                <Icon className="mt-0.5 h-5 w-5 text-gray-700" strokeWidth={1.5} />
                                                <span>
                                                    <span className="block text-[15px] font-medium">{meta.title}</span>
                                                    <span className="block text-sm text-gray-500">{meta.body}</span>
                                                </span>
                                            </label>
                                        );
                                    })}
                                    <InputError message={err("payment_method")} />
                                    <p className="pt-2 text-xs text-gray-500">Card payments will be available soon. Nothing is charged until your order is confirmed.</p>
                                    <button type="submit" disabled={form.processing} className="btn-ink mt-4 w-full justify-center bg-gray-900 py-4 text-white hover:bg-gray-700 disabled:opacity-60 sm:w-auto">
                                        <Lock className="h-4 w-4" /> {form.processing ? "Placing your order…" : `Place order · ${money(subtotal)}`}
                                    </button>
                                </div>
                            )}
                        </section>
                    </div>

                    {/* Summary */}
                    <aside className="lg:col-span-5 animate-slide-up [animation-delay:180ms]">
                        <div className="sticky top-10 rounded-3xl bg-white p-6 shadow-[0_1px_0_rgba(0,0,0,0.04),0_20px_50px_-30px_rgba(0,0,0,0.15)] sm:p-7">
                            <button type="button" onClick={() => setSummaryOpen((v) => !v)} className="flex w-full items-center justify-between">
                                <span className="inline-flex items-center gap-2 text-[15px] font-medium">
                                    <ShoppingBag className="h-4 w-4" /> Your order
                                    <Link href={route("cart")} className="ml-1 text-xs font-normal text-gray-500 underline-offset-4 hover:underline">
                                        Edit
                                    </Link>
                                </span>
                                <ChevronUp className={`h-4 w-4 text-gray-400 transition-transform ${summaryOpen ? "" : "rotate-180"}`} />
                            </button>

                            {summaryOpen && (
                                <ul className="mt-5 space-y-4">
                                    {items.map((item) => (
                                        <li key={item.id} className="flex gap-4">
                                            <span className="relative h-20 w-16 shrink-0 overflow-hidden rounded-xl bg-[#efece6]">
                                                {item.fabricImage && <img src={cdn(item.fabricImage, 200)} alt="" className="absolute inset-0 h-full w-full object-cover" />}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="flex justify-between gap-3 text-sm">
                                                    <span className="font-medium">Tailored suit{(item.quantity || 1) > 1 ? ` × ${item.quantity}` : ""}</span>
                                                    <span>{money(item.price * (item.quantity || 1))}</span>
                                                </span>
                                                <span className="mt-0.5 block truncate text-xs text-gray-500">
                                                    {item.fabricName} · {(item.summary || []).slice(0, 3).map((r) => r.value).join(", ")}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <dl className="mt-6 space-y-2 border-t border-gray-100 pt-5 text-sm">
                                <div className="flex justify-between"><dt className="text-gray-500">Bag total</dt><dd>{money(subtotal)}</dd></div>
                                <div className="flex justify-between"><dt className="text-gray-500">Shipping</dt><dd>Free &amp; tracked</dd></div>
                            </dl>
                            <div className="mt-4 flex items-baseline justify-between border-t border-gray-900 pt-4">
                                <span className="font-medium">Total</span>
                                <span className="text-2xl font-light tracking-tight">{money(subtotal)}</span>
                            </div>

                            <div className="mt-6 flex items-start justify-between gap-4 border-t border-gray-100 pt-5">
                                <div className="flex items-start gap-3">
                                    <Ruler className="mt-0.5 h-4 w-4 text-gray-700" />
                                    <div>
                                        <p className="text-sm font-medium">Body profile</p>
                                        <p className="text-xs text-gray-500">
                                            {profile.name} · {profile.height_cm} cm · {profile.weight_kg} kg · {profile.age} years
                                        </p>
                                    </div>
                                </div>
                                <Link href={route("checkout.body-profile")} className="text-xs font-medium text-gray-900 underline-offset-4 hover:underline">
                                    Edit
                                </Link>
                            </div>
                            <InputError message={err("items") || err("body_profile")} className="mt-3" />
                        </div>
                    </aside>
                </form>
            )}
        </StoreLayout>
    );
}
