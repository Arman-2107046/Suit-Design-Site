import InputError from "@/Components/InputError";
import SiteLayout, { cdn } from "@/Layouts/SiteLayout";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowRight, Check } from "lucide-react";

const COUNTRIES = [
    "Bangladesh", "India", "Pakistan", "Sri Lanka", "Nepal", "United Arab Emirates", "Saudi Arabia", "Qatar", "Singapore", "Malaysia",
    "United Kingdom", "Ireland", "Germany", "France", "Spain", "Italy", "Netherlands", "Belgium", "Switzerland", "Austria", "Sweden",
    "Norway", "Denmark", "Finland", "Portugal", "Poland", "United States", "Canada", "Australia", "New Zealand", "Japan", "South Korea",
];

const inputCls = "mt-1.5 block w-full rounded-xl border-gray-200 bg-white px-4 py-3 text-[15px] placeholder-gray-400 focus:border-gray-900 focus:ring-0";

const Field = ({ label, error, children, className = "" }) => (
    <label className={`block ${className}`}>
        <span className="text-[13px] font-medium tracking-wide text-gray-700">{label}</span>
        {children}
        <InputError message={error} className="mt-1.5" />
    </label>
);

export default function Samples({ intro, image, max, fabrics, defaults }) {
    const sent = Boolean(usePage().props.flash?.sent);
    const form = useForm({
        email: defaults?.email ?? "",
        fabric_ids: [],
        shipping: { name: defaults?.name ?? "", phone: "", address: "", address2: "", city: "", postcode: "", country: "" },
        notes: "",
    });

    const toggle = (id) => {
        const chosen = form.data.fabric_ids.includes(id);
        if (!chosen && form.data.fabric_ids.length >= max) return;
        form.setData("fabric_ids", chosen ? form.data.fabric_ids.filter((x) => x !== id) : [...form.data.fabric_ids, id]);
    };
    const ship = (key, value) => form.setData("shipping", { ...form.data.shipping, [key]: value });
    const err = (key) => form.errors[key];

    const submit = (e) => {
        e.preventDefault();
        form.post(route("samples.request"), { preserveScroll: true });
    };

    return (
        <SiteLayout
            eyebrow="Support"
            title="Order fabric samples"
            subtitle={intro || `Hold the cloth to the light before you decide. Choose up to ${max} and we post the swatches to you, free.`}
            hero={image}
        >
            <Head title="Fabric samples" />

            {sent ? (
                <div className="mx-auto max-w-lg rounded-3xl bg-[#f7f6f3] p-10 text-center animate-slide-up">
                    <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 text-white">
                        <Check className="h-6 w-6" />
                    </span>
                    <p className="mt-6 text-2xl font-light tracking-tight">Your swatches are on their way.</p>
                    <p className="mt-2 text-sm text-gray-500">We have emailed you a confirmation. When one feels right, come back and design in it.</p>
                    <Link href="/design" className="btn-ink mt-8 bg-gray-900 text-white hover:bg-gray-700">
                        Open the designer <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            ) : (
                <form onSubmit={submit} className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                    <section className="lg:col-span-7 animate-slide-up [animation-delay:120ms]">
                        <div className="flex items-baseline justify-between">
                            <h2 className="text-2xl font-light tracking-tight">Choose your cloths</h2>
                            <span className="text-sm text-gray-500 tabular-nums">
                                {form.data.fabric_ids.length} / {max}
                            </span>
                        </div>
                        <InputError message={err("fabric_ids")} className="mt-2" />
                        <ul className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                            {fabrics.map((fabric) => {
                                const chosen = form.data.fabric_ids.includes(fabric.id);
                                const full = !chosen && form.data.fabric_ids.length >= max;
                                return (
                                    <li key={fabric.id}>
                                        <button
                                            type="button"
                                            onClick={() => toggle(fabric.id)}
                                            aria-pressed={chosen}
                                            disabled={full}
                                            className={`group relative block w-full overflow-hidden rounded-2xl text-left transition-all duration-300 ${chosen ? "ring-2 ring-gray-900 ring-offset-2" : "hover:-translate-y-0.5"} ${full ? "opacity-40" : ""}`}
                                        >
                                            <span className="relative block aspect-[4/3] bg-[#efece6]">
                                                <img src={cdn(fabric.image, 600)} alt={fabric.name} loading="lazy" className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" />
                                                {chosen && (
                                                    <span className="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-white">
                                                        <Check className="h-3.5 w-3.5" />
                                                    </span>
                                                )}
                                            </span>
                                            <span className="flex items-baseline justify-between px-1 pt-2.5">
                                                <span className="text-sm font-medium">{fabric.name}</span>
                                                <span className="text-xs text-gray-500">${Math.round(Number(fabric.price) || 0)}</span>
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    <section className="lg:col-span-5 animate-slide-up [animation-delay:200ms]">
                        <div className="sticky top-24 rounded-3xl bg-[#f7f6f3] p-7 sm:p-8">
                            <h2 className="text-2xl font-light tracking-tight">Where should we post them?</h2>
                            <div className="mt-6 space-y-4">
                                <Field label="Full name" error={err("shipping.name")}>
                                    <input value={form.data.shipping.name} onChange={(e) => ship("name", e.target.value)} required autoComplete="name" className={inputCls} />
                                </Field>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Email" error={err("email")}>
                                        <input type="email" value={form.data.email} onChange={(e) => form.setData("email", e.target.value)} required autoComplete="email" className={inputCls} />
                                    </Field>
                                    <Field label="Phone (optional)" error={err("shipping.phone")}>
                                        <input value={form.data.shipping.phone} onChange={(e) => ship("phone", e.target.value)} autoComplete="tel" className={inputCls} />
                                    </Field>
                                </div>
                                <Field label="Street address" error={err("shipping.address")}>
                                    <input value={form.data.shipping.address} onChange={(e) => ship("address", e.target.value)} required className={inputCls} />
                                </Field>
                                <Field label="Apartment, floor (optional)" error={err("shipping.address2")}>
                                    <input value={form.data.shipping.address2} onChange={(e) => ship("address2", e.target.value)} className={inputCls} />
                                </Field>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="City" error={err("shipping.city")}>
                                        <input value={form.data.shipping.city} onChange={(e) => ship("city", e.target.value)} required className={inputCls} />
                                    </Field>
                                    <Field label="Postcode" error={err("shipping.postcode")}>
                                        <input value={form.data.shipping.postcode} onChange={(e) => ship("postcode", e.target.value)} required className={inputCls} />
                                    </Field>
                                </div>
                                <Field label="Country" error={err("shipping.country")}>
                                    <select value={form.data.shipping.country} onChange={(e) => ship("country", e.target.value)} required className={inputCls}>
                                        <option value="">Select a country</option>
                                        {COUNTRIES.map((c) => <option key={c}>{c}</option>)}
                                    </select>
                                </Field>
                                <Field label="Notes (optional)" error={err("notes")}>
                                    <textarea rows={2} value={form.data.notes} onChange={(e) => form.setData("notes", e.target.value)} placeholder="What are you planning to have made?" className={inputCls} />
                                </Field>
                            </div>
                            <button type="submit" disabled={form.processing || form.data.fabric_ids.length === 0} className="btn-ink mt-6 w-full justify-center bg-gray-900 py-4 text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400">
                                {form.processing ? "Sending…" : `Request ${form.data.fabric_ids.length || ""} free sample${form.data.fabric_ids.length === 1 ? "" : "s"}`}
                                {!form.processing && <ArrowRight className="h-4 w-4" />}
                            </button>
                        </div>
                    </section>
                </form>
            )}
        </SiteLayout>
    );
}
