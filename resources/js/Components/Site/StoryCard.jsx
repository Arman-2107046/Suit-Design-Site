import { cdn } from "@/Layouts/SiteLayout";
import { Link } from "@inertiajs/react";
import { Heart, MessageCircle } from "lucide-react";

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
