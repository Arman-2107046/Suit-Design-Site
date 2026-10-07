<?php

namespace App\Services\RankYak;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/*
 * RankYak's REST API (https://docs.rankyak.com). The key stays on this server;
 * the browser never sees it.
 */
class RankYakClient
{
    public const BASE_URL = 'https://rankyak.com/api/v1';

    public function __construct(private readonly string $apiKey) {}

    /**
     * One page of the project's articles, newest first, with the title heading
     * left out of the body (the journal shows the title itself).
     *
     * @return array{data: list<array<string, mixed>>, next: bool}
     */
    public function articles(int $page = 1, int $perPage = 100): array
    {
        $response = $this->request()->get('/articles', [
            'page' => $page,
            'per_page' => $perPage,
            'exclude_h1' => 'true',
        ])->throw();

        return [
            'data' => $response->json('data') ?? [],
            'next' => filled($response->json('links.next')),
        ];
    }

    /**
     * Tell RankYak where an article went live, so it can verify it and count
     * it in the backlink exchange.
     */
    public function reportUrl(string $articleId, string $url): void
    {
        $this->request()->post('/articles/'.rawurlencode($articleId).'/external-url', [
            'external_url' => $url,
        ])->throw();
    }

    /** A cheap call that proves the key works. */
    public function check(): void
    {
        $this->articles(1, 1);
    }

    /** A message an admin can act on, from whatever went wrong. */
    public static function explain(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return match ($e->response->status()) {
                401, 403 => 'RankYak rejected the API key. Copy it again from RankYak → Settings → Integrations → API.',
                404 => 'RankYak could not find that article. It may have been deleted there.',
                422 => 'RankYak did not accept the request: '.($e->response->json('message') ?? 'invalid data').'.',
                429 => 'RankYak is rate-limiting requests. Try again in a minute.',
                default => 'RankYak answered with an error ('.$e->response->status().').',
            };
        }

        return 'Could not reach RankYak: '.$e->getMessage();
    }

    private function request(): PendingRequest
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('No RankYak API key is saved.');
        }

        return Http::baseUrl(self::BASE_URL)
            ->withHeaders(['X-Api-Key' => $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(2, 500, fn ($e) => ! $e instanceof RequestException || $e->response->serverError(), throw: true);
    }
}
