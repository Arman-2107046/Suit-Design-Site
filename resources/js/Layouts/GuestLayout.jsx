import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
const heroUrl = (url) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_1600/${m[2]}` : url;
};

/*
 * Split-screen frame for every guest page: editorial panel on the left
 * (the homepage hero photo, or a graphite tone until one is uploaded),
 * the form on the right. `title` / `subtitle` head the form column;
 * `aside` is a small line rendered under it (e.g. the sign-up switch).
 */
export default function GuestLayout({ title, subtitle, aside, children }) {
    const heroImage = usePage().props.brand?.heroImage ?? null;

    return (
        <div className="grid min-h-dvh bg-white lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] xl:grid-cols-2">
            <aside className="relative hidden overflow-hidden bg-[#141414] text-white lg:block">
                {heroImage && (
                    <img src={heroUrl(heroImage)} alt="" className="absolute inset-0 h-full w-full object-cover object-[60%_center]" />
                )}
                <div
                    className="absolute inset-0 bg-[linear-gradient(160deg,rgba(0,0,0,0.15)_0%,rgba(0,0,0,0.05)_40%,rgba(0,0,0,0.75)_100%)]"
                    aria-hidden="true"
                />

                <div className="relative flex h-full flex-col justify-between p-10 xl:p-14">
                    <Link href="/" className="text-2xl font-semibold tracking-tight">
                        Custom Tailor
                    </Link>

                    <div className="max-w-md animate-slide-up">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/70">Made to measure</p>
                        <p className="mt-4 text-4xl font-light leading-[1.05] tracking-tight xl:text-5xl">
                            Dress the real you
                        </p>
                        <p className="mt-4 text-[15px] leading-relaxed text-white/80">
                            Save your designs, track your orders and come back to a suit that already knows your measurements.
                        </p>
                    </div>
                </div>
            </aside>

            <main className="relative flex flex-col px-6 py-8 sm:px-12 lg:px-16 xl:px-24">
                <div className="flex items-center justify-between">
                    <Link href="/" className="text-xl font-semibold tracking-tight text-gray-900 lg:invisible">
                        Custom Tailor
                    </Link>
                    <Link
                        href="/"
                        className="inline-flex items-center gap-1.5 text-sm text-gray-500 transition-colors hover:text-gray-900"
                    >
                        <ArrowLeft className="h-4 w-4" /> Back to site
                    </Link>
                </div>

                <div className="flex flex-1 items-center py-12">
                    <div className="w-full max-w-md animate-slide-up">
                        {title && (
                            <header className="mb-10">
                                <h1 className="text-4xl font-light tracking-tight text-gray-900 sm:text-5xl">{title}</h1>
                                {subtitle && <p className="mt-3 text-[15px] text-gray-500">{subtitle}</p>}
                            </header>
                        )}

                        {children}

                        {aside && <p className="mt-10 text-sm text-gray-500">{aside}</p>}
                    </div>
                </div>

                <p className="text-xs text-gray-400">Copyright {new Date().getFullYear()} Custom Tailor</p>
            </main>
        </div>
    );
}
