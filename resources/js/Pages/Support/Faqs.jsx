import SiteLayout from "@/Layouts/SiteLayout";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, Minus, Plus, Search } from "lucide-react";
import { useMemo, useState } from "react";

const strip = (html) => String(html || "").replace(/<[^>]+>/g, " ");

export default function Faqs({ faqs }) {
    const [query, setQuery] = useState("");
    const [open, setOpen] = useState(null);

    const groups = useMemo(() => {
        const q = query.trim().toLowerCase();
        const matched = q ? faqs.filter((f) => `${f.question} ${strip(f.answer)}`.toLowerCase().includes(q)) : faqs;
        const map = new Map();
        matched.forEach((f) => (map.get(f.category) || map.set(f.category, []).get(f.category)).push(f));
        return [...map.entries()];
    }, [faqs, query]);

    return (
        <SiteLayout eyebrow="Support" title="Frequently asked questions" subtitle="Ordering, fit, delivery, and everything in between." narrow>
            <Head title="FAQs" />

            <div className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                <div className="lg:col-span-8 animate-slide-up [animation-delay:120ms]">
                    <label className="relative block">
                        <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search questions"
                            className="block w-full rounded-full border-gray-200 bg-white py-3 pl-11 pr-4 text-[15px] placeholder-gray-400 focus:border-gray-900 focus:ring-0"
                        />
                    </label>

                    {groups.length === 0 && (
                        <p className="mt-10 text-sm text-gray-500">
                            {faqs.length === 0 ? "Questions and answers can be added under Admin → Content → FAQs." : "Nothing matches that search."}
                        </p>
                    )}

                    {groups.map(([category, items]) => (
                        <section key={category} className="mt-10">
                            <h2 className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">{category}</h2>
                            <ul className="mt-3 divide-y divide-gray-100 border-y border-gray-100">
                                {items.map((faq) => {
                                    const isOpen = open === faq.id;
                                    return (
                                        <li key={faq.id}>
                                            <button
                                                type="button"
                                                onClick={() => setOpen(isOpen ? null : faq.id)}
                                                aria-expanded={isOpen}
                                                className="flex w-full items-center justify-between gap-6 py-5 text-left transition-colors hover:text-gray-600"
                                            >
                                                <span className="text-[17px] font-medium tracking-tight">{faq.question}</span>
                                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gray-200 text-gray-700">
                                                    {isOpen ? <Minus className="h-3.5 w-3.5" /> : <Plus className="h-3.5 w-3.5" />}
                                                </span>
                                            </button>
                                            {isOpen && (
                                                <div className="prose-site pb-6 text-[15px] leading-relaxed text-gray-600 animate-fade-in" dangerouslySetInnerHTML={{ __html: faq.answer }} />
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    ))}
                </div>

                <aside className="lg:col-span-4 animate-slide-up [animation-delay:200ms]">
                    <div className="sticky top-24 rounded-3xl bg-[#141414] p-7 text-white">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-white/50">Still wondering?</p>
                        <p className="mt-3 text-2xl font-light tracking-tight">Ask us directly.</p>
                        <p className="mt-2 text-sm text-white/70">A real person answers within one working day.</p>
                        <Link href={route("contact")} className="btn-ink btn-ink--light mt-6">
                            Contact us <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </aside>
            </div>
        </SiteLayout>
    );
}
