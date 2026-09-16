import SiteLayout from "@/Layouts/SiteLayout";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight } from "lucide-react";

const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "long", year: "numeric" }) : null);

export default function Page({ page }) {
    return (
        <SiteLayout title={page.title} subtitle={page.subtitle} hero={page.hero_image_url} narrow>
            <Head title={page.title} />

            <div className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                <article className="prose-site max-w-3xl text-[17px] leading-relaxed text-gray-700 lg:col-span-8 animate-slide-up [animation-delay:120ms]" dangerouslySetInnerHTML={{ __html: page.body || "" }} />

                <aside className="lg:col-span-4 animate-slide-up [animation-delay:200ms]">
                    <div className="sticky top-24 space-y-4">
                        <div className="rounded-3xl bg-[#f7f6f3] p-7">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Made to measure</p>
                            <p className="mt-3 text-2xl font-light tracking-tight">A suit that is only yours.</p>
                            <Link href="/design" className="btn-ink mt-5 bg-gray-900 text-white hover:bg-gray-700">
                                Design your suit <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                        {page.updated_at && <p className="px-2 text-xs text-gray-400">Last updated {fmtDate(page.updated_at)}</p>}
                    </div>
                </aside>
            </div>
        </SiteLayout>
    );
}
