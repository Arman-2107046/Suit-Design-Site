@php
    $postsUrl = \App\Filament\Resources\BlogPosts\BlogPostResource::getUrl('index');
@endphp

<style>
    .ryi { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); overflow: hidden; color: #0f172a; }
    .ryi-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 22px; border-bottom: 1px solid #f1f5f9; }
    .ryi-head b { font-size: 15px; font-weight: 650; }
    .ryi-head a { font-size: 13px; font-weight: 600; color: var(--primary-600); text-decoration: none; }
    .ryi-row { display: grid; grid-template-columns: 56px minmax(0, 1fr) auto; align-items: center; gap: 14px; padding: 12px 22px; border-bottom: 1px solid #f8fafc; text-decoration: none; color: inherit; transition: background 0.15s ease; }
    .ryi-row:last-child { border-bottom: 0; }
    .ryi-row:hover { background: #f8fafc; }
    .ryi-thumb { width: 56px; height: 40px; border-radius: 8px; background: #f1f5f9 center / cover no-repeat; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06); }
    .ryi-title { font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ryi-meta { margin-top: 2px; font-size: 12px; color: #64748b; }
    .ryi-badges { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .ryi-badge { padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .ryi-live { background: #ecfdf5; color: #047857; }
    .ryi-scheduled { background: var(--primary-50); color: var(--primary-700); }
    .ryi-draft { background: #f1f5f9; color: #475569; }
    .ryi-reported { background: #f8fafc; color: #64748b; box-shadow: inset 0 0 0 1px #e2e8f0; }
    .ryi-empty { padding: 34px 22px; text-align: center; font-size: 13px; color: #94a3b8; }
    .ryi-empty strong { display: block; margin-bottom: 4px; font-size: 13.5px; color: #475569; }
</style>

<div class="ryi">
    <div class="ryi-head">
        <b>Recent articles from RankYak</b>
        @if ($total > 0)
            <a href="{{ $postsUrl }}">All posts →</a>
        @endif
    </div>

    @forelse ($posts as $post)
        @php
            $state = match (true) {
                $post->status !== 'published' => ['Draft', 'ryi-draft'],
                $post->published_at?->isFuture() => ['Scheduled '.$post->published_at->format('j M'), 'ryi-scheduled'],
                default => ['Live', 'ryi-live'],
            };
        @endphp
        <a class="ryi-row" href="{{ \App\Filament\Resources\BlogPosts\BlogPostResource::getUrl('edit', ['record' => $post]) }}">
            <span class="ryi-thumb" @if ($post->cover_image_url) style="background-image: url('{{ \App\Support\ImageUrl::sized($post->cover_image_url, 160) }}');" @endif></span>
            <div style="min-width: 0;">
                <div class="ryi-title">{{ $post->title }}</div>
                <div class="ryi-meta">{{ $post->category?->name ?? 'No category' }} · arrived {{ $post->created_at->diffForHumans() }}</div>
            </div>
            <div class="ryi-badges">
                <span class="ryi-badge {{ $state[1] }}">{{ $state[0] }}</span>
                @if ($post->external_url_reported_at)
                    <span class="ryi-badge ryi-reported" title="RankYak was told this post's URL {{ $post->external_url_reported_at->diffForHumans() }}">URL reported</span>
                @endif
            </div>
        </a>
    @empty
        <div class="ryi-empty">
            <strong>No articles yet</strong>
            Once the webhook is set up in RankYak, each article it publishes will appear here.
        </div>
    @endforelse
</div>
