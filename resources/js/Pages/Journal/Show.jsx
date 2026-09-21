import InputError from "@/Components/InputError";
import { Footer } from "@/Components/Site/Footer";
import { Header } from "@/Components/Site/Header";
import { cdn } from "@/Layouts/SiteLayout";
import { StoryCard } from "@/Pages/Journal/Index";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowRight, Check, Heart, MessageCircle, Share2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";

const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "long", year: "numeric" }) : "");
const csrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || "");

const inputCls = "mt-1.5 block w-full rounded-xl border-gray-200 bg-white px-4 py-3 text-[15px] placeholder-gray-400 focus:border-gray-900 focus:ring-0";

/* Thin bar at the very top that fills as the reader moves through the story. */
function useReadingProgress(ref) {
    const [progress, setProgress] = useState(0);
    useEffect(() => {
        const onScroll = () => {
            const el = ref.current;
            if (!el) return;
            const top = el.offsetTop;
            const height = el.offsetHeight - window.innerHeight;
            setProgress(height > 0 ? Math.min(1, Math.max(0, (window.scrollY - top) / height)) : 1);
        };
        onScroll();
        window.addEventListener("scroll", onScroll, { passive: true });
        return () => window.removeEventListener("scroll", onScroll);
    }, [ref]);
    return progress;
}

const LikeButton = ({ post, className = "" }) => {
    const [liked, setLiked] = useState(post.liked);
    const [count, setCount] = useState(post.likes);
    const [busy, setBusy] = useState(false);

    const toggle = async () => {
        if (busy) return;
        setBusy(true);
        setLiked((v) => !v);
        setCount((c) => c + (liked ? -1 : 1));
        try {
            const res = await fetch(route("journal.like", post.slug), { method: "POST", headers: { Accept: "application/json", "X-XSRF-TOKEN": csrf() }, credentials: "same-origin" });
            if (res.ok) {
                const data = await res.json();
                setLiked(data.liked);
                setCount(data.count);
            }
        } catch {
            /* keep optimistic state */
        } finally {
            setBusy(false);
        }
    };

    return (
        <button type="button" onClick={toggle} aria-pressed={liked} className={`group inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-all ${liked ? "border-gray-900 bg-gray-900 text-white" : "border-gray-200 text-gray-700 hover:border-gray-900"} ${className}`}>
            <Heart className={`h-4 w-4 transition-transform group-active:scale-125 ${liked ? "fill-current" : ""}`} />
            <span className="tabular-nums">{count}</span>
        </button>
    );
};

const ShareButton = ({ title }) => {
    const [copied, setCopied] = useState(false);
    const share = async () => {
        const url = window.location.href;
        try {
            if (navigator.share) await navigator.share({ title, url });
            else {
                await navigator.clipboard.writeText(url);
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            }
        } catch {
            /* dismissed */
        }
    };
    return (
        <button type="button" onClick={share} className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2 text-sm text-gray-700 transition-colors hover:border-gray-900">
            {copied ? <Check className="h-4 w-4" /> : <Share2 className="h-4 w-4" />} {copied ? "Link copied" : "Share"}
        </button>
    );
};

export default function Show({ post, related, commentDefaults }) {
    const user = usePage().props.auth?.user ?? null;
    const sent = Boolean(usePage().props.flash?.comment_sent);
    const articleRef = useRef(null);
    const progress = useReadingProgress(articleRef);

    const form = useForm({ name: commentDefaults?.name ?? "", email: commentDefaults?.email ?? "", body: "", website: "" });
    const submit = (e) => {
        e.preventDefault();
        form.post(route("journal.comment", post.slug), { preserveScroll: true, onSuccess: () => form.reset("body") });
    };

    const description = post.seo_description || post.excerpt;

    return (
        <div className="min-h-dvh bg-white text-gray-900">
            <Head>
                <title>{post.seo_title || post.title}</title>
                <meta name="description" content={description} />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={post.seo_title || post.title} />
                <meta property="og:description" content={description} />
                {post.cover && <meta property="og:image" content={cdn(post.cover, 1200)} />}
                <meta name="twitter:card" content={post.cover ? "summary_large_image" : "summary"} />
                {post.published_at && <meta property="article:published_time" content={post.published_at} />}
            </Head>

            <Header user={user} solid />
            <div className="fixed inset-x-0 top-0 z-[60] h-0.5 bg-gray-900 origin-left transition-transform duration-100" style={{ transform: `scaleX(${progress})` }} aria-hidden="true" />

            <article ref={articleRef} className="pt-28 lg:pt-36">
                <header className="mx-auto max-w-3xl px-5 text-center sm:px-8">
                    {post.category && (
                        <Link href={route("journal", { category: post.category.slug })} className="text-[11px] font-semibold uppercase tracking-[0.28em] text-gray-500 hover:text-gray-900">
                            {post.category.name}
                        </Link>
                    )}
                    <h1 className="mt-4 text-4xl font-light leading-[1.05] tracking-tight text-balance sm:text-5xl lg:text-6xl animate-slide-up">{post.title}</h1>
                    {post.excerpt && <p className="mx-auto mt-6 max-w-2xl font-serif text-xl leading-relaxed text-gray-600 animate-slide-up [animation-delay:80ms]">{post.excerpt}</p>}
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm text-gray-500 animate-slide-up [animation-delay:140ms]">
                        {post.author && (
                            <span className="inline-flex items-center gap-2">
                                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-[11px] font-semibold text-white">{post.author.split(" ").map((w) => w[0]).slice(0, 2).join("")}</span>
                                <span className="font-medium text-gray-900">{post.author}</span>
                            </span>
                        )}
                        <span aria-hidden="true">·</span>
                        <span>{fmtDate(post.published_at)}</span>
                        <span aria-hidden="true">·</span>
                        <span>{post.reading_minutes} min read</span>
                    </div>
                </header>

                {post.cover && (
                    <figure className="mx-auto mt-12 max-w-6xl px-5 sm:px-8 animate-fade-in [animation-delay:200ms]">
                        <div className="relative overflow-hidden rounded-3xl bg-[#efece6] aspect-[16/9]">
                            <img src={cdn(post.cover, 2000)} alt={post.cover_caption || ""} className="absolute inset-0 h-full w-full object-cover" />
                        </div>
                        {post.cover_caption && <figcaption className="mt-3 text-center text-xs text-gray-400">{post.cover_caption}</figcaption>}
                    </figure>
                )}

                <div className="mx-auto mt-14 grid max-w-6xl gap-10 px-5 sm:px-8 lg:grid-cols-12">
                    <aside className="hidden lg:col-span-2 lg:block">
                        <div className="sticky top-28 flex flex-col items-start gap-3">
                            <LikeButton post={post} />
                            <a href="#comments" className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2 text-sm text-gray-700 transition-colors hover:border-gray-900">
                                <MessageCircle className="h-4 w-4" /> <span className="tabular-nums">{post.comments.length}</span>
                            </a>
                            <ShareButton title={post.title} />
                        </div>
                    </aside>

                    <div className="lg:col-span-8">
                        <div className="prose-journal" dangerouslySetInnerHTML={{ __html: post.body || "" }} />

                        {post.tags.length > 0 && (
                            <ul className="mt-12 flex flex-wrap gap-2">
                                {post.tags.map((tag) => (
                                    <li key={tag}>
                                        <Link href={route("journal", { tag })} className="inline-block rounded-full bg-[#f7f6f3] px-3 py-1.5 text-xs text-gray-700 transition-colors hover:bg-gray-900 hover:text-white">#{tag}</Link>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="mt-10 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-8 lg:hidden">
                            <LikeButton post={post} />
                            <ShareButton title={post.title} />
                        </div>

                        {post.fabrics.length > 0 && (
                            <section className="mt-16 rounded-3xl bg-[#f7f6f3] p-7 sm:p-9">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Shop the cloths</p>
                                <p className="mt-2 text-2xl font-light tracking-tight">Design in the fabrics from this story</p>
                                <ul className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                                    {post.fabrics.map((fabric) => (
                                        <li key={fabric.id}>
                                            <Link href={`/design?fabric=${fabric.id}`} className="group block">
                                                <span className="relative block aspect-[4/3] overflow-hidden rounded-2xl bg-[#efece6]">
                                                    <img src={cdn(fabric.image, 600)} alt={fabric.name} loading="lazy" className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" />
                                                </span>
                                                <span className="mt-2.5 flex items-baseline justify-between text-sm">
                                                    <span className="font-medium">{fabric.name}</span>
                                                    <span className="text-gray-500">${Math.round(Number(fabric.price) || 0)}</span>
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        <section id="comments" className="mt-16 scroll-mt-28">
                            <div className="flex items-baseline justify-between">
                                <h2 className="text-2xl font-light tracking-tight">{post.comments.length ? `${post.comments.length} comment${post.comments.length === 1 ? "" : "s"}` : "Join the conversation"}</h2>
                                <span className="text-xs text-gray-400">Comments are reviewed before they appear.</span>
                            </div>

                            {post.comments.length > 0 && (
                                <ul className="mt-8 divide-y divide-gray-100">
                                    {post.comments.map((c) => (
                                        <li key={c.id} className="flex gap-4 py-6 first:pt-0">
                                            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ${c.is_staff ? "bg-gray-900 text-white" : "bg-[#f7f6f3] text-gray-700"}`}>
                                                {c.name.split(" ").map((w) => w[0]).slice(0, 2).join("").toUpperCase()}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="text-sm">
                                                    <span className="font-medium">{c.name}</span>
                                                    {c.is_staff && <span className="ml-2 rounded-full bg-gray-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">Atelier</span>}
                                                    <span className="ml-2 text-gray-400">{fmtDate(c.created_at)}</span>
                                                </p>
                                                <p className="mt-2 whitespace-pre-line text-[15px] leading-relaxed text-gray-700">{c.body}</p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className="mt-10 rounded-3xl bg-[#f7f6f3] p-7 sm:p-8">
                                {sent ? (
                                    <div className="flex items-start gap-4">
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white"><Check className="h-4 w-4" /></span>
                                        <div>
                                            <p className="font-medium">Thank you — your comment is with us.</p>
                                            <p className="mt-1 text-sm text-gray-500">It will appear here once a member of the atelier has read it.</p>
                                        </div>
                                    </div>
                                ) : (
                                    <form onSubmit={submit} className="space-y-4">
                                        <p className="text-lg font-light tracking-tight">Leave a comment</p>
                                        {!user && (
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <label className="block">
                                                    <span className="text-[13px] font-medium tracking-wide text-gray-700">Name</span>
                                                    <input value={form.data.name} onChange={(e) => form.setData("name", e.target.value)} required maxLength={120} className={inputCls} />
                                                    <InputError message={form.errors.name} className="mt-1.5" />
                                                </label>
                                                <label className="block">
                                                    <span className="text-[13px] font-medium tracking-wide text-gray-700">Email <span className="font-normal text-gray-400">(never shown)</span></span>
                                                    <input type="email" value={form.data.email} onChange={(e) => form.setData("email", e.target.value)} required className={inputCls} />
                                                    <InputError message={form.errors.email} className="mt-1.5" />
                                                </label>
                                            </div>
                                        )}
                                        <label className="block">
                                            <span className="text-[13px] font-medium tracking-wide text-gray-700">{user ? `Commenting as ${user.name}` : "Comment"}</span>
                                            <textarea rows={4} value={form.data.body} onChange={(e) => form.setData("body", e.target.value)} required minLength={3} maxLength={2000} placeholder="Share a thought, a question, or your own experience." className={inputCls} />
                                            <InputError message={form.errors.body} className="mt-1.5" />
                                        </label>
                                        <input type="text" name="website" value={form.data.website} onChange={(e) => form.setData("website", e.target.value)} tabIndex={-1} autoComplete="off" className="hidden" aria-hidden="true" />
                                        <button type="submit" disabled={form.processing} className="btn-ink bg-gray-900 text-white hover:bg-gray-700 disabled:opacity-60">
                                            {form.processing ? "Sending…" : "Post comment"} {!form.processing && <ArrowRight className="h-4 w-4" />}
                                        </button>
                                    </form>
                                )}
                            </div>
                        </section>
                    </div>
                </div>
            </article>

            {related.length > 0 && (
                <section className="mx-auto mt-24 max-w-6xl border-t border-gray-100 px-5 pt-16 sm:px-8">
                    <div className="flex items-baseline justify-between">
                        <h2 className="text-2xl font-light tracking-tight">Keep reading</h2>
                        <Link href={route("journal")} className="text-sm text-gray-500 transition-colors hover:text-gray-900">All stories</Link>
                    </div>
                    <div className="mt-8 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                        {related.map((p) => <StoryCard key={p.slug} post={p} />)}
                    </div>
                </section>
            )}

            <div className="mt-24">
                <Footer />
            </div>
        </div>
    );
}
