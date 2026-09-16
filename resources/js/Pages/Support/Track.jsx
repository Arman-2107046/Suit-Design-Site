import InputError from "@/Components/InputError";
import SiteLayout, { cdn } from "@/Layouts/SiteLayout";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowRight, Package } from "lucide-react";

const inputCls = "mt-1.5 block w-full rounded-xl border-gray-200 bg-white px-4 py-3 text-[15px] placeholder-gray-400 focus:border-gray-900 focus:ring-0";

const STEPS = ["Pending", "Confirmed", "In production", "Quality check", "Shipped", "Delivered"];

export default function Track({ image }) {
    const user = usePage().props.auth?.user ?? null;
    const form = useForm({ number: "", email: user?.email ?? "" });

    const submit = (e) => {
        e.preventDefault();
        form.post(route("track.lookup"), { preserveScroll: true });
    };

    return (
        <SiteLayout eyebrow="Support" title="Track your order" subtitle="Enter your order number and the email you ordered with, and we will show you where your suit is." hero={image}>
            <Head title="Track order" />

            <div className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                <div className="lg:col-span-5 animate-slide-up [animation-delay:120ms]">
                    <form onSubmit={submit} className="rounded-3xl bg-[#f7f6f3] p-7 sm:p-8">
                        <label className="block">
                            <span className="text-[13px] font-medium tracking-wide text-gray-700">Order number</span>
                            <input value={form.data.number} onChange={(e) => form.setData("number", e.target.value.toUpperCase())} required placeholder="CT-2026-A7K3QZ" className={`${inputCls} font-mono tracking-wider`} />
                            <InputError message={form.errors.number} className="mt-1.5" />
                        </label>
                        <label className="mt-4 block">
                            <span className="text-[13px] font-medium tracking-wide text-gray-700">Email</span>
                            <input type="email" value={form.data.email} onChange={(e) => form.setData("email", e.target.value)} required className={inputCls} />
                            <InputError message={form.errors.email} className="mt-1.5" />
                        </label>
                        <button type="submit" disabled={form.processing} className="btn-ink mt-6 w-full justify-center bg-gray-900 py-4 text-white hover:bg-gray-700 disabled:opacity-60">
                            {form.processing ? "Looking…" : "Track order"} {!form.processing && <ArrowRight className="h-4 w-4" />}
                        </button>
                        {user && (
                            <p className="mt-4 text-center text-xs text-gray-500">
                                Signed in? Your orders are also in{" "}
                                <Link href={route("dashboard")} className="text-gray-900 underline-offset-4 hover:underline">your atelier</Link>.
                            </p>
                        )}
                    </form>
                    <p className="mt-4 text-xs text-gray-500">The order number is at the top of your confirmation email and receipt.</p>
                </div>

                <div className="lg:col-span-7 animate-slide-up [animation-delay:200ms]">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">How an order moves</p>
                    <ol className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {STEPS.map((step, i) => (
                            <li key={step} className="flex gap-4">
                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-900 text-[11px] font-semibold text-white">{i + 1}</span>
                                <div>
                                    <p className="text-[15px] font-medium">{step}</p>
                                    <p className="mt-1 text-sm text-gray-500">
                                        {[
                                            "We review your measurements and reserve the cloth.",
                                            "Your order is confirmed and scheduled for cutting.",
                                            "Our tailors cut and sew your suit.",
                                            "Inspected, pressed and finished by hand.",
                                            "On its way, tracked to your door.",
                                            "Delivered. Enjoy it.",
                                        ][i]}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>
                    <div className="mt-10 flex items-start gap-4 rounded-3xl border border-gray-100 p-6">
                        <Package className="mt-0.5 h-5 w-5 text-gray-700" strokeWidth={1.5} />
                        <p className="text-sm text-gray-600">Most suits are tailored and delivered in about three weeks. If something looks off, <Link href={route("contact")} className="text-gray-900 underline-offset-4 hover:underline">write to us</Link> with your order number.</p>
                    </div>
                </div>
            </div>
        </SiteLayout>
    );
}
