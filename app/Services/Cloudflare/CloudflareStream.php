<?php

namespace App\Services\Cloudflare;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/*
 * Cloudflare Stream: the homepage video. Images does not host video.
 *
 * The homepage plays it in a plain <video> element, muted and looping, so it
 * is served as Stream's MP4 rendition rather than HLS — that needs no player
 * library and behaves exactly as the old Cloudinary file did. The MP4 is
 * produced once the upload has finished encoding, which takes a little while;
 * the admin field waits for it before saving the address.
 *
 * Stored address: https://customer-<code>.cloudflarestream.com/<uid>/downloads/default.mp4
 */
class CloudflareStream
{
    public function __construct(
        private readonly ?string $accountId,
        private readonly ?string $token,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.cloudflare.account_id'),
            config('services.cloudflare.api_token'),
        );
    }

    /**
     * A one-time address the browser posts the video to directly.
     *
     * @return array{uid: string, uploadURL: string}
     */
    public function directUpload(int $maxDurationSeconds = 600): array
    {
        $response = $this->api()->post($this->endpoint('stream/direct_upload'), [
            'maxDurationSeconds' => $maxDurationSeconds,
        ]);

        return $this->result($response, 'mint an upload URL');
    }

    /** Have Stream fetch a video itself, for moving one across from elsewhere. */
    public function copyFromUrl(string $url, string $name): array
    {
        $response = $this->api()->post($this->endpoint('stream/copy'), [
            'url' => $url,
            'meta' => ['name' => $name],
        ]);

        return $this->result($response, "copy {$name}");
    }

    public function video(string $uid): array
    {
        return $this->result($this->api()->get($this->endpoint("stream/{$uid}")), "read {$uid}");
    }

    /**
     * Ask for the MP4 rendition. Only possible once the video is ready to
     * stream; until then this says so rather than failing.
     *
     * @return array{state: string, percent: int|float, url: ?string}
     */
    public function mp4(string $uid): array
    {
        $video = $this->video($uid);

        if (! ($video['readyToStream'] ?? false)) {
            return [
                'state' => 'encoding',
                'percent' => (float) ($video['status']['pctComplete'] ?? 0),
                'url' => null,
            ];
        }

        /* Creating downloads is idempotent: asking again reports progress. */
        $downloads = $this->result(
            $this->api()->post($this->endpoint("stream/{$uid}/downloads")),
            "prepare the MP4 of {$uid}"
        );

        $default = $downloads['default'] ?? [];

        return [
            'state' => ($default['status'] ?? null) === 'ready' ? 'ready' : 'rendering',
            'percent' => (float) ($default['percentComplete'] ?? 0),
            'url' => $default['url'] ?? null,
        ];
    }

    public function delete(string $uid): void
    {
        $response = $this->api()->delete($this->endpoint("stream/{$uid}"));

        if ($response->status() !== 404) {
            $this->result($response, "delete {$uid}");
        }
    }

    /** The video id inside a stored Stream address, if it is one. */
    public static function uidFromUrl(?string $url): ?string
    {
        return $url && preg_match('~cloudflarestream\.com/([a-f0-9]{32})/~i', $url, $m) ? $m[1] : null;
    }

    private function api(): PendingRequest
    {
        if (! filled($this->accountId) || ! filled($this->token)) {
            throw new RuntimeException('Cloudflare is not configured: set CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_API_TOKEN.');
        }

        return Http::withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->retry(3, 1000, fn ($e) => ($e->response?->status() ?? 500) >= 500 || $e->response?->status() === 429, throw: false);
    }

    private function endpoint(string $path): string
    {
        return "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/{$path}";
    }

    private function result(Response $response, string $doing): array
    {
        if ($response->successful() && $response->json('success')) {
            return $response->json('result') ?? [];
        }

        $message = $response->json('errors.0.message') ?? "HTTP {$response->status()}";

        throw new RuntimeException("Cloudflare Stream could not {$doing}: {$message}");
    }
}
