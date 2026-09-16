import { Footer } from "@/Components/Site/Footer";
import { Header } from "@/Components/Site/Header";
import { usePage } from "@inertiajs/react";

const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
export const cdn = (url, width) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_${width}/${m[2]}` : url;
};

/*
 * Frame for content and support pages: the storefront header (solid) and
 * footer around an optional hero and the page body.
 */
export default function SiteLayout({ eyebrow, title, subtitle, hero, children, narrow = false }) {
    const user = usePage().props.auth?.user ?? null;

    return (
        <div className="min-h-dvh bg-white text-gray-900">
            <Header user={user} solid />

            {hero ? (
                <section className="relative mt-16 h-[52vh] min-h-[360px] overflow-hidden bg-[#141414]">
                    <img src={cdn(hero, 2400)} alt="" className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-black/20" aria-hidden="true" />
                    <div className="absolute inset-x-0 bottom-0 mx-auto max-w-[1600px] px-5 pb-10 sm:px-8 lg:px-12 lg:pb-14">
                        {eyebrow && <p className="mb-3 text-[11px] font-semibold uppercase tracking-[0.28em] text-white/70">{eyebrow}</p>}
                        <h1 className="text-4xl font-light tracking-tight text-white sm:text-5xl lg:text-6xl text-balance">{title}</h1>
                        {subtitle && <p className="mt-4 max-w-2xl text-lg font-light text-white/85">{subtitle}</p>}
                    </div>
                </section>
            ) : (
                <section className="mx-auto max-w-[1600px] px-5 pb-6 pt-28 sm:px-8 lg:px-12 lg:pt-36">
                    <div className={narrow ? "max-w-3xl" : "max-w-4xl"}>
                        {eyebrow && <p className="mb-3 text-[11px] font-semibold uppercase tracking-[0.28em] text-gray-400">{eyebrow}</p>}
                        <h1 className="text-4xl font-light tracking-tight sm:text-5xl lg:text-6xl text-balance animate-slide-up">{title}</h1>
                        {subtitle && <p className="mt-4 max-w-2xl text-lg font-light text-gray-500 animate-slide-up [animation-delay:80ms]">{subtitle}</p>}
                    </div>
                </section>
            )}

            <main className="mx-auto max-w-[1600px] px-5 pb-24 pt-8 sm:px-8 lg:px-12 lg:pt-12">{children}</main>

            <Footer />
        </div>
    );
}
