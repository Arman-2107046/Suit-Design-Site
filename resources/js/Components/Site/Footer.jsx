import { Link, usePage } from "@inertiajs/react";
import { ArrowRight, Facebook, Instagram } from "lucide-react";
import { useState } from "react";

const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
const swatchUrl = (url, width) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_${width}/${m[2]}` : url;
};

const Brand = ({ d, className }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden="true">
        <path d={d} />
    </svg>
);

const SOCIAL_ICONS = {
    instagram: (cls) => <Instagram className={cls} strokeWidth={1.5} />,
    facebook: (cls) => <Facebook className={cls} strokeWidth={1.5} />,
    x: (cls) => (
        <Brand
            className={cls}
            d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"
        />
    ),
    pinterest: (cls) => (
        <Brand
            className={cls}
            d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"
        />
    ),
    tiktok: (cls) => (
        <Brand
            className={cls}
            d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"
        />
    ),
};

const SOCIAL_LABELS = { instagram: "Instagram", facebook: "Facebook", x: "X", pinterest: "Pinterest", tiktok: "TikTok" };

const FOOTER_LINKS = [
    { title: "Custom suits", links: [["Design your suit", "/design"], ["Fabrics", "/#fabrics"], ["How it works", "/#how-it-works"]] },
    { title: "Company", links: [["About us", "/p/about-us"], ["Perfect Fit Guarantee", "/p/perfect-fit-guarantee"], ["The Journal", "/journal"]] },
    { title: "Support", links: [["Contact us", "/contact"], ["Order fabric samples", "/samples"], ["Track order", "/track"], ["FAQs", "/faqs"]] },
];

const PAYMENTS = ["VISA", "Mastercard", "PayPal", "Apple Pay", "Amex"];

/* Uploaded logos when there are any; otherwise the text badges (or nothing). */
const LogoRow = ({ title, logos, fallback = [] }) => {
    if (logos.length === 0 && fallback.length === 0) return null;
    return (
        <div>
            <p className="text-sm font-semibold text-gray-900">{title}</p>
            <ul className="flex flex-wrap items-center gap-2 mt-4">
                {logos.length > 0
                    ? logos.map((url, i) => (
                          <li key={url} className="flex items-center justify-center h-9 px-1 overflow-hidden bg-white border border-gray-200 rounded">
                              <img src={swatchUrl(url, 160)} alt="" className="object-contain h-7 w-auto max-w-[3.75rem]" loading="lazy" />
                          </li>
                      ))
                    : fallback.map((p) => (
                          <li key={p} className="px-3 py-1.5 text-[11px] font-semibold tracking-wide text-gray-700 uppercase border border-gray-200 rounded">
                              {p}
                          </li>
                      ))}
            </ul>
        </div>
    );
};

export const Footer = (props) => {
    const site = usePage().props.site || {};
    const socials = props.socials ?? site.socials ?? {};
    const paymentLogos = props.paymentLogos ?? site.payment_logos ?? [];
    const shippingLogos = props.shippingLogos ?? site.shipping_logos ?? [];
    const [email, setEmail] = useState("");
    const [sent, setSent] = useState(false);
    const socialEntries = Object.entries(socials).filter(([network]) => SOCIAL_ICONS[network]);

    return (
        <footer className="pt-16 bg-white border-t border-gray-100 sm:pt-20">
            <div className="grid gap-12 px-5 mx-auto max-w-[1600px] sm:px-8 lg:px-12 lg:grid-cols-12 lg:gap-8">
                <div className="lg:col-span-4">
                    <p className="text-sm font-semibold text-gray-900">Subscribe to our newsletter to get updates</p>
                    <form
                        className="flex items-center max-w-sm mt-5 border-b border-gray-900"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setSent(true);
                        }}
                    >
                        <label htmlFor="newsletter-email" className="sr-only">Email address</label>
                        <input
                            id="newsletter-email"
                            type="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="Email Address"
                            className="flex-1 py-2 text-base text-gray-900 placeholder-gray-400 bg-transparent border-0 focus:ring-0 px-0"
                        />
                        <button type="submit" aria-label="Subscribe" className="p-2 -mr-2 text-gray-900 transition-transform hover:translate-x-0.5">
                            <ArrowRight className="w-5 h-5" />
                        </button>
                    </form>
                    <p className={`mt-3 text-xs text-gray-500 transition-opacity ${sent ? "opacity-100" : "opacity-0"}`} aria-live="polite">
                        Thanks — you&rsquo;re on the list.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-8">
                    {FOOTER_LINKS.map((col) => (
                        <div key={col.title}>
                            <p className="text-sm font-semibold text-gray-900">{col.title}</p>
                            <ul className="mt-4 space-y-2.5">
                                {col.links.map(([label, href]) => (
                                    <li key={label}>
                                        {href.startsWith("/") && !href.includes("#") ? (
                                            <Link href={href} className="text-sm text-gray-600 transition-colors hover:text-gray-900">{label}</Link>
                                        ) : (
                                            <a href={href} className="text-sm text-gray-600 transition-colors hover:text-gray-900">{label}</a>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}

                    <div className="flex flex-wrap col-span-2 gap-x-12 gap-y-8 sm:col-span-3">
                        <LogoRow title="Payment methods" logos={paymentLogos} fallback={PAYMENTS} />
                        <LogoRow title="Shipping partners" logos={shippingLogos} />
                    </div>
                </div>
            </div>

            {socialEntries.length > 0 && (
                <div className="px-5 mx-auto mt-14 max-w-[1600px] sm:px-8 lg:px-12">
                    <div className="flex items-center gap-4 text-gray-900">
                        {socialEntries.map(([network, href]) => (
                            <a
                                key={network}
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={SOCIAL_LABELS[network]}
                                className="transition-opacity hover:opacity-60"
                            >
                                {SOCIAL_ICONS[network]("w-5 h-5")}
                            </a>
                        ))}
                    </div>
                </div>
            )}

            <div className="mt-14 bg-[#f7f6f3]">
                <div className="flex flex-col gap-2 px-5 py-5 mx-auto text-xs text-gray-500 max-w-[1600px] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
                    <span>Copyright {new Date().getFullYear()} Custom Tailor</span>
                    <span>
                        <Link href="/p/terms" className="hover:text-gray-900">Terms and Conditions</Link> · <Link href="/p/privacy-policy" className="hover:text-gray-900">Privacy Policy</Link>
                    </span>
                </div>
            </div>
        </footer>
    );
};

