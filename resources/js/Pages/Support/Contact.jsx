import InputError from "@/Components/InputError";
import SiteLayout, { cdn } from "@/Layouts/SiteLayout";
import { Head, useForm, usePage } from "@inertiajs/react";
import { ArrowRight, Check, Clock, Mail, MapPin, Phone } from "lucide-react";

const inputCls = "mt-1.5 block w-full rounded-xl border-gray-200 bg-white px-4 py-3 text-[15px] placeholder-gray-400 focus:border-gray-900 focus:ring-0";

const Field = ({ label, error, children }) => (
    <label className="block">
        <span className="text-[13px] font-medium tracking-wide text-gray-700">{label}</span>
        {children}
        <InputError message={error} className="mt-1.5" />
    </label>
);

export default function Contact({ contact }) {
    const user = usePage().props.auth?.user ?? null;
    const sent = Boolean(usePage().props.flash?.sent);
    const form = useForm({ name: user?.name ?? "", email: user?.email ?? "", subject: "", message: "" });

    const submit = (e) => {
        e.preventDefault();
        form.post(route("contact.send"), { preserveScroll: true, onSuccess: () => form.reset("subject", "message") });
    };

    const rows = [
        contact.email && { Icon: Mail, label: "Email", value: contact.email, href: `mailto:${contact.email}` },
        contact.phone && { Icon: Phone, label: "Phone", value: contact.phone, href: `tel:${contact.phone.replace(/\s+/g, "")}` },
        contact.address && { Icon: MapPin, label: "Atelier", value: contact.address },
        contact.hours && { Icon: Clock, label: "Hours", value: contact.hours },
    ].filter(Boolean);

    return (
        <SiteLayout eyebrow="Support" title="Contact us" subtitle={contact.intro || "A question about cloth, fit or an order? Write to us and we will answer within one working day."}>
            <Head title="Contact us" />

            <div className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                <div className="lg:col-span-5 animate-slide-up [animation-delay:120ms]">
                    {contact.image && (
                        <div className="relative mb-8 aspect-[4/5] overflow-hidden rounded-3xl bg-[#efece6]">
                            <img src={cdn(contact.image, 1200)} alt="" className="absolute inset-0 h-full w-full object-cover" />
                        </div>
                    )}
                    <dl className="divide-y divide-gray-100">
                        {rows.map(({ Icon, label, value, href }) => (
                            <div key={label} className="flex gap-4 py-5 first:pt-0">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#f7f6f3]">
                                    <Icon className="h-4 w-4 text-gray-900" strokeWidth={1.5} />
                                </span>
                                <div>
                                    <dt className="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-400">{label}</dt>
                                    <dd className="mt-1 whitespace-pre-line text-[15px] text-gray-800">
                                        {href ? <a href={href} className="underline-offset-4 hover:underline">{value}</a> : value}
                                    </dd>
                                </div>
                            </div>
                        ))}
                        {rows.length === 0 && <p className="text-sm text-gray-500">Contact details can be added under Admin → Support settings.</p>}
                    </dl>
                </div>

                <div className="lg:col-span-7 animate-slide-up [animation-delay:200ms]">
                    <div className="rounded-3xl bg-[#f7f6f3] p-7 sm:p-10">
                        {sent ? (
                            <div className="py-10 text-center">
                                <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 text-white">
                                    <Check className="h-6 w-6" />
                                </span>
                                <p className="mt-6 text-2xl font-light tracking-tight">Thank you — we have your message.</p>
                                <p className="mt-2 text-sm text-gray-500">We reply within one working day, to the address you gave us.</p>
                            </div>
                        ) : (
                            <form onSubmit={submit} className="space-y-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Name" error={form.errors.name}>
                                        <input value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required autoComplete="name" className={inputCls} />
                                    </Field>
                                    <Field label="Email" error={form.errors.email}>
                                        <input type="email" value={form.data.email} onChange={(e) => form.setData("email", e.target.value)} required autoComplete="email" className={inputCls} />
                                    </Field>
                                </div>
                                <Field label="Subject" error={form.errors.subject}>
                                    <input value={form.data.subject} onChange={(e) => form.setData("subject", e.target.value)} placeholder="Fit, fabric, an order…" className={inputCls} />
                                </Field>
                                <Field label="Message" error={form.errors.message}>
                                    <textarea rows={6} value={form.data.message} onChange={(e) => form.setData("message", e.target.value)} required className={inputCls} />
                                </Field>
                                <button type="submit" disabled={form.processing} className="btn-ink bg-gray-900 text-white hover:bg-gray-700 disabled:opacity-60">
                                    {form.processing ? "Sending…" : "Send message"} {!form.processing && <ArrowRight className="h-4 w-4" />}
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </SiteLayout>
    );
}
