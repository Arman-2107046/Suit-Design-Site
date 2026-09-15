import { cartCount, useCart } from "@/lib/store";
import { Link, usePage } from "@inertiajs/react";
import { ArrowLeft, Lock, ShoppingBag, User } from "lucide-react";

/*
 * Frame for the bag and checkout: a calm solid header with the wordmark,
 * bag count and account, and an optional step indicator. `secure` swaps the
 * bag for a padlock, the way checkout pages do.
 */
export default function StoreLayout({ title, eyebrow, steps, step, back, secure = false, wide = false, children }) {
    const user = usePage().props.auth?.user ?? null;
    const count = cartCount(useCart());

    return (
        <div className="min-h-dvh bg-[#f7f6f3] text-gray-900">
            <header className="bg-white shadow-[0_1px_0_rgba(0,0,0,0.06)]">
                <div className="mx-auto flex h-16 max-w-[1400px] items-center justify-between px-5 sm:px-8">
                    <div className="flex items-center gap-6">
                        {back && (
                            <Link href={back.href} className="inline-flex items-center gap-1.5 text-sm text-gray-500 transition-colors hover:text-gray-900">
                                <ArrowLeft className="h-4 w-4" /> <span className="hidden sm:inline">{back.label}</span>
                            </Link>
                        )}
                        <Link href="/" className="text-[22px] font-semibold tracking-tight">
                            Custom Tailor
                        </Link>
                    </div>

                    <div className="flex items-center gap-5">
                        {secure ? (
                            <span className="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                <Lock className="h-3.5 w-3.5" /> Secure checkout
                            </span>
                        ) : (
                            <Link href={route("cart")} className="relative inline-flex items-center gap-2 text-sm font-medium transition-opacity hover:opacity-60">
                                <ShoppingBag className="h-5 w-5" strokeWidth={1.5} />
                                <span className="hidden sm:inline">Bag</span>
                                {count > 0 && (
                                    <span className="absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-gray-900 px-1 text-[10px] font-semibold text-white sm:static sm:h-auto sm:min-w-0 sm:bg-transparent sm:px-0 sm:text-sm sm:font-medium sm:text-gray-500">
                                        {count}
                                    </span>
                                )}
                            </Link>
                        )}
                        <Link
                            href={user ? route("dashboard") : route("login")}
                            className="inline-flex items-center gap-1.5 text-sm font-medium transition-opacity hover:opacity-60"
                        >
                            <User className="h-5 w-5" strokeWidth={1.5} />
                            <span className="hidden sm:inline">{user ? user.name.split(" ")[0] : "Login"}</span>
                        </Link>
                    </div>
                </div>

                {steps && (
                    <ol className="mx-auto flex max-w-[1400px] gap-2 px-5 pb-4 sm:px-8" aria-label="Checkout progress">
                        {steps.map((label, i) => {
                            const state = i < step ? "done" : i === step ? "current" : "todo";
                            return (
                                <li key={label} className="flex flex-1 flex-col gap-2">
                                    <span className={`h-0.5 rounded-full transition-colors ${state === "todo" ? "bg-gray-200" : "bg-gray-900"}`} />
                                    <span className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${state === "current" ? "text-gray-900" : "text-gray-400"}`}>{label}</span>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </header>

            <main className={`mx-auto px-5 pb-20 pt-10 sm:px-8 sm:pt-12 ${wide ? "max-w-[1400px]" : "max-w-[1180px]"}`}>
                {(title || eyebrow) && (
                    <div className="mb-10 animate-slide-up">
                        {eyebrow && <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">{eyebrow}</p>}
                        {title && <h1 className="mt-2 text-4xl font-light tracking-tight sm:text-5xl">{title}</h1>}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
