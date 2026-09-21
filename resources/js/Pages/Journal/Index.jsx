import SiteLayout, { cdn } from "@/Layouts/SiteLayout";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, Heart, MessageCircle } from "lucide-react";

const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "long", year: "numeric" }) : "");

export const StoryMeta = ({ post, className = "" }) => (
    <p className={`flex flex-wrap items-center gap-x-2 text-xs text-gray-500 ${className}`}>
        {post.category && <span className="font-semibold uppercase tracking-[0.18em] text-gray-900">{post.category.name}</span>}
        {post.category && <span aria-hidden="true">·</span>}
        <span>{fmtDate(post.published_at)}</span>
        <span aria-hidden="true">·</span>
        <span>{post.reading_minutes} min read</span>
    </p>
);

export const StoryCard = ({ post, large = false }) => (
    <Link href={route("journal.show", post.slug)} className="group block">
        <div className={`relative overflow-hidden rounded-2xl bg-[#efece6] ${large ? "aspect-[16/9]" : "aspect-[4/3]"}`}>
            {post.cover && <img src={cdn(post.cover, large ? 1600 : 900)} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-105" />}
        </div>
        <StoryMeta post={post} className="mt-4" />
        <h3 className={`mt-2 font-light tracking-tight text-gray-900 text-balance transition-colors group-hover:text-gray-600 ${large ? "text-3xl sm:text-4xl" : "text-2xl"}`}>{post.title}</h3>
        <p className="mt-2 line-clamp-2 text-[15px] leading-relaxed text-gray-600">{post.excerpt}</p>
        <p className="mt-3 flex items-center gap-4 text-xs text-gray-400">
            <span className="inline-flex items-center gap-1"><Heart className="h-3.5 w-3.5" /> {post.likes}</span>
            <span className="inline-flex items-center gap-1"><MessageCircle className="h-3.5 w-3.5" /> {post.comments}</span>
        </p>
    </Link>
);

export default function Index({ featured, posts, categories, filters }) {
    const active = filters.category;
    const items = posts.data;

    return (
        <SiteLayout eyebrow="The Journal" title="Notes on suits, cloth and dressing well" subtitle="Stories from the atelier — on fit, fabric, the occasions that call for a suit, and how to keep one for decades.">
            <Head>
                <title>The Journal</title>
                <meta name="description" content="Stories from the Custom Tailor atelier on fit, fabric, style and the craft of dressing well." />
                <link rel="alternate" type="application/rss+xml" title="Custom Tailor Journal" href={route("journal.feed")} />
            </Head>

            {(categories.length > 0 || filters.tag) && (
                <nav className="-mx-5 mb-10 overflow-x-auto px-5 no-scrollbar sm:mx-0 sm:px-0 animate-slide-up [animation-delay:120ms]" aria-label="Categories">
                    <ul className="flex w-max gap-2">
                        <li>
                            <Link href={route("journal")} className={`inline-block rounded-full border px-4 py-2 text-sm transition-colors ${!active && !filters.tag ? "border-gray-900 bg-gray-900 text-white" : "border-gray-200 text-gray-700 hover:border-gray-900"}`}>
                                All stories
                            </Link>
                        </li>
                        {categories.map((c) => (
                            <li key={c.slug}>
                                <Link href={route("journal", { category: c.slug })} className={`inline-block rounded-full border px-4 py-2 text-sm transition-colors ${active === c.slug ? "border-gray-900 bg-gray-900 text-white" : "border-gray-200 text-gray-700 hover:border-gray-900"}`}>
                                    {c.name} <span className="text-gray-400">{c.posts_count}</span>
                                </Link>
                            </li>
                        ))}
                        {filters.tag && (
                            <li>
                                <span className="inline-block rounded-full border border-gray-900 bg-gray-900 px-4 py-2 text-sm text-white">#{filters.tag}</span>
                            </li>
                        )}
                    </ul>
                </nav>
            )}

            {featured && (
                <section className="mb-16 animate-slide-up [animation-delay:160ms]">
                    <Link href={route("journal.show", featured.slug)} className="group grid gap-8 lg:grid-cols-12 lg:items-center lg:gap-12">
                        <div className="relative overflow-hidden rounded-3xl bg-[#efece6] aspect-[16/10] lg:col-span-7">
                            {featured.cover && <img src={cdn(featured.cover, 1800)} alt="" className="absolute inset-0 h-full w-full object-cover transition-transform duration-[1400ms] ease-out group-hover:scale-105" />}
                        </div>
                        <div className="lg:col-span-5">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Featured</p>
                            <StoryMeta post={featured} className="mt-3" />
                            <h2 className="mt-3 text-4xl font-light tracking-tight text-gray-900 text-balance sm:text-5xl">{featured.title}</h2>
                            <p className="mt-4 text-[17px] leading-relaxed text-gray-600">{featured.excerpt}</p>
                            <span className="btn-ink btn-ink--outline mt-6">
                                Read the story <ArrowRight className="h-4 w-4" />
                            </span>
                        </div>
                    </Link>
                </section>
            )}

            {items.length === 0 && !featured ? (
                <div className="rounded-3xl bg-[#f7f6f3] p-12 text-center">
                    <p className="text-2xl font-light tracking-tight">The first story is being written.</p>
                    <p className="mt-2 text-sm text-gray-500">Check back soon — or design a suit in the meantime.</p>
                    <Link href="/design" className="btn-ink mt-6 bg-gray-900 text-white hover:bg-gray-700">Open the designer <ArrowRight className="h-4 w-4" /></Link>
                </div>
            ) : (
                <div className="grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 animate-slide-up [animation-delay:220ms]">
                    {items.map((post) => <StoryCard key={post.slug} post={post} />)}
                </div>
            )}

            {posts.links && posts.last_page > 1 && (
                <nav className="mt-16 flex items-center justify-center gap-2" aria-label="Pages">
                    {posts.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url || "#"}
                            preserveScroll
                            className={`min-w-[2.5rem] rounded-full border px-3 py-2 text-center text-sm transition-colors ${link.active ? "border-gray-900 bg-gray-900 text-white" : link.url ? "border-gray-200 text-gray-700 hover:border-gray-900" : "border-transparent text-gray-300"}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </SiteLayout>
    );
}
