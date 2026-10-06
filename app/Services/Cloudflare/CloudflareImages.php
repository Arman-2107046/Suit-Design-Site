<?php

namespace App\Services\Cloudflare;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/*
 * Cloudflare Images: where every picture on the site lives.
 *
 * Images are stored under a custom id that is the path they would have had on
 * disk — "homepage/01M2….png" — so a path saved in the database resolves to
 * the same image whichever side of the migration from Cloudinary it was made.
 *
 * Delivery is https://imagedelivery.net/<hash>/<id>/<variant>. The account has
 * flexible variants switched on, so the variant can be a size such as
 * "w=800,fit=scale-down" as well as the named "public".
 */
class CloudflareImages
{
    /** Cloudflare answers this when a custom id is already taken. */
    private const ALREADY_EXISTS = 5409;

    public function __construct(
        private readonly ?string $accountId,
        private readonly ?string $token,
        private readonly ?string $hash,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.cloudflare.account_id'),
            config('services.cloudflare.api_token'),
            config('services.cloudflare.images_hash'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->accountId) && filled($this->token) && filled($this->hash);
    }

    /**
     * The public address of an image, at full size or as a flexible variant.
     */
    public function url(string $id, string $variant = 'public'): string
    {
        return "https://imagedelivery.net/{$this->hash}/".$this->encodeId($id)."/{$variant}";
    }

    /**
     * @param  string|resource  $contents
     */
    public function upload($contents, string $id): array
    {
        $response = $this->api()
            ->attach('file', $contents, basename($id))
            ->post($this->endpoint('images/v1'), ['id' => $id]);

        return $this->result($response, "upload {$id}");
    }

    /**
     * Have Cloudflare fetch the image itself — nothing passes through this server.
     *
     * Returns false when the id is already taken, which for a re-run of the
     * migration means it was copied last time.
     */
    public function uploadFromUrl(string $url, string $id): array|false
    {
        $response = $this->api()
            ->asMultipart()
            ->post($this->endpoint('images/v1'), [
                ['name' => 'url', 'contents' => $url],
                ['name' => 'id', 'contents' => $id],
            ]);

        if ($this->errorCode($response) === self::ALREADY_EXISTS) {
            return false;
        }

        return $this->result($response, "copy {$id}");
    }

    /**
     * Copy several images at once, each fetched by Cloudflare from its URL.
     *
     * @param  array<string, string>  $items  id => source URL
     * @return array<string, true|string> id => true when it is now on Cloudflare
     *                                     (copied, or already there), else why not
     */
    public function copyMany(array $items): array
    {
        $this->api();

        $responses = Http::pool(fn ($pool) => collect($items)->map(
            fn (string $url, string $id) => $pool->as($id)
                ->withToken($this->token)
                ->acceptJson()
                ->timeout(120)
                ->asMultipart()
                ->post($this->endpoint('images/v1'), [
                    ['name' => 'url', 'contents' => $url],
                    ['name' => 'id', 'contents' => $id],
                ])
        )->all());

        $outcome = [];

        foreach ($items as $id => $url) {
            $response = $responses[$id] ?? null;

            if (! $response instanceof Response) {
                $outcome[$id] = $response instanceof \Throwable ? $response->getMessage() : 'No response';

                continue;
            }

            $outcome[$id] = match (true) {
                $response->successful() && (bool) $response->json('success') => true,
                $this->errorCode($response) === self::ALREADY_EXISTS => true,
                default => $response->json('errors.0.message') ?? "HTTP {$response->status()}",
            };
        }

        return $outcome;
    }

    /**
     * A one-time address the browser can post a file to directly, so uploads
     * skip PHP's size limits and the API token never leaves the server.
     *
     * @return array{id: string, uploadURL: string}
     */
    public function directUpload(?string $id = null): array
    {
        $fields = [['name' => 'requireSignedURLs', 'contents' => 'false']];

        if ($id !== null) {
            $fields[] = ['name' => 'id', 'contents' => $id];
        }

        $response = $this->api()
            ->asMultipart()
            ->post($this->endpoint('images/v2/direct_upload'), $fields);

        return $this->result($response, 'mint upload URL');
    }

    public function exists(string $id): bool
    {
        return $this->api()->get($this->endpoint('images/v1/'.$this->encodeId($id)))->successful();
    }

    public function delete(string $id): void
    {
        $response = $this->api()->delete($this->endpoint('images/v1/'.$this->encodeId($id)));

        /* Gone already is as good as deleted. */
        if ($response->status() !== 404) {
            $this->result($response, "delete {$id}");
        }
    }

    public function download(string $id): string
    {
        $response = $this->api()->get($this->endpoint('images/v1/'.$this->encodeId($id).'/blob'));

        if (! $response->successful()) {
            throw new RuntimeException("Cloudflare Images could not return {$id} (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    private function api(): PendingRequest
    {
        if (! filled($this->accountId) || ! filled($this->token)) {
            throw new RuntimeException('Cloudflare is not configured: set CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_API_TOKEN.');
        }

        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout(120)
            ->retry(3, 1000, fn ($e) => $this->worthRetrying($e), throw: false);
    }

    /** Rate limits and Cloudflare's own hiccups are worth a second try; a bad request is not. */
    private function worthRetrying($exception): bool
    {
        if (self::certificateRejected($exception)) {
            return false;
        }

        /* A dropped connection has no response at all, and is worth retrying too. */
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return $status === null || $status === 429 || $status >= 500;
    }

    /**
     * The connection was refused because this machine does not trust the
     * certificate it was shown — usually antivirus software scanning HTTPS
     * (Avast does) with a root PHP has not been told about. It fails the same
     * way every time, so retrying only burns PHP's time limit: on a form save
     * that lost the upload outright.
     */
    public static function certificateRejected($exception): bool
    {
        $message = $exception instanceof \Throwable ? $exception->getMessage() : '';

        return str_contains($message, 'SSL certificate') || str_contains($message, 'local issuer certificate');
    }

    private function endpoint(string $path): string
    {
        return "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/{$path}";
    }

    /* Slashes stay — they are part of the id — everything else is escaped. */
    private function encodeId(string $id): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $id)));
    }

    private function errorCode(Response $response): ?int
    {
        return $response->json('errors.0.code');
    }

    private function result(Response $response, string $doing): array
    {
        if ($response->successful() && $response->json('success')) {
            return $response->json('result') ?? [];
        }

        $message = $response->json('errors.0.message') ?? "HTTP {$response->status()}";

        throw new RuntimeException("Cloudflare Images could not {$doing}: {$message}");
    }
}
