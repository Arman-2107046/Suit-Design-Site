<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request): Response
    {
        $category = $request->query('category');
        $tag = $request->query('tag');

        $query = BlogPost::published()
            ->with(['category', 'author'])
            ->withCount(['likes', 'approvedComments'])
            ->when($category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $category)))
            ->when($tag, fn ($q) => $q->whereJsonContains('tags', $tag))
            ->orderByDesc('published_at');

        $featured = ! $category && ! $tag && ! $request->query('page')
            ? (clone $query)->where('is_featured', true)->first() ?? (clone $query)->first()
            : null;

        $posts = (clone $query)
            ->when($featured, fn ($q) => $q->whereKeyNot($featured->id))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Journal/Index', [
            'featured' => $featured?->toCardArray(),
            'posts' => $posts->through(fn (BlogPost $post) => $post->toCardArray()),
            'categories' => BlogCategory::orderBy('sort_order')->withCount(['posts' => fn ($q) => $q->published()])->get()
                ->filter(fn ($c) => $c->posts_count > 0)->values()->map->only(['name', 'slug', 'posts_count']),
            'filters' => ['category' => $category, 'tag' => $tag],
        ]);
    }

    public function show(Request $request, BlogPost $post): Response
    {
        abort_unless($post->status === 'published' && $post->published_at?->lte(now()), 404);

        $post->load(['category', 'author', 'fabrics', 'approvedComments.user'])->loadCount(['likes', 'approvedComments']);
        BlogPost::whereKey($post->id)->increment('views');

        $related = BlogPost::published()->with(['category', 'author'])->withCount(['likes', 'approvedComments'])
            ->whereKeyNot($post->id)
            ->when($post->blog_category_id, fn ($q) => $q->orderByRaw('blog_category_id = ? desc', [$post->blog_category_id]))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return Inertia::render('Journal/Show', [
            'post' => [
                ...$post->toCardArray(),
                'body' => $post->body,
                'cover_caption' => $post->cover_caption,
                'tags' => $post->tags ?? [],
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'fabrics' => $post->fabrics->map->only(['id', 'name', 'price', 'image']),
                'liked' => $post->likes()->where('token', $this->likeToken($request))->exists(),
                'comments' => $post->approvedComments->map(fn (BlogComment $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'body' => $c->body,
                    'created_at' => $c->created_at->toIso8601String(),
                    'is_staff' => (bool) $c->user_id && $c->user_id === $post->user_id,
                ]),
            ],
            'related' => $related->map->toCardArray(),
            'commentDefaults' => $request->user() ? ['name' => $request->user()->name, 'email' => $request->user()->email] : null,
        ]);
    }

    public function comment(Request $request, BlogPost $post): RedirectResponse
    {
        abort_unless($post->status === 'published', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'website' => ['nullable', 'max:0'], // honeypot: must stay empty
        ]);

        $post->comments()->create([
            'user_id' => $request->user()?->id,
            'name' => $request->user()?->name ?? $data['name'],
            'email' => strtolower($request->user()?->email ?? $data['email']),
            'body' => $data['body'],
            'status' => 'pending',
        ]);

        return back()->with('comment_sent', true);
    }

    public function like(Request $request, BlogPost $post): JsonResponse
    {
        abort_unless($post->status === 'published', 404);

        $token = $this->likeToken($request);
        $existing = $post->likes()->where('token', $token)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $post->likes()->create(['user_id' => $request->user()?->id, 'token' => $token]);
        }

        $response = response()->json(['liked' => ! $existing, 'count' => $post->likes()->count()]);

        if (! $request->user() && ! $request->cookie('ct_reader')) {
            $response->cookie('ct_reader', $this->readerId($request), 60 * 24 * 365, null, null, null, true);
        }

        return $response;
    }

    public function feed(): HttpResponse
    {
        $posts = BlogPost::published()->with(['category', 'author'])->orderByDesc('published_at')->limit(20)->get();

        $xml = view('journal.feed', ['posts' => $posts])->render();

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    /* One identity per reader: the account if signed in, otherwise a long-lived cookie set on the first like. */
    private function likeToken(Request $request): string
    {
        return $request->user() ? 'user:' . $request->user()->id : 'reader:' . $this->readerId($request);
    }

    private function readerId(Request $request): string
    {
        return $request->attributes->get('ct_reader') ?? tap(
            $request->cookie('ct_reader') ?: Str::random(40),
            fn ($id) => $request->attributes->set('ct_reader', $id)
        );
    }
}
