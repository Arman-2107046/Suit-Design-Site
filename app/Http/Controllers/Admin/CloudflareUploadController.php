<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cloudflare\CloudflareImages;
use App\Services\Cloudflare\CloudflareStream;
use Illuminate\Http\JsonResponse;
use Throwable;

/*
 * Browser uploads go straight to Cloudflare, so large files never meet PHP's
 * upload limits. The browser cannot hold the API token, though, so it asks
 * here first for a one-time address to post a single file to.
 */
class CloudflareUploadController extends Controller
{
    /** One image: the bulk uploader asks once per file. */
    public function image(CloudflareImages $images): JsonResponse
    {
        return $this->answer(fn () => $images->directUpload());
    }

    /** The homepage video. */
    public function video(CloudflareStream $stream): JsonResponse
    {
        return $this->answer(fn () => $stream->directUpload());
    }

    /**
     * Where an uploaded video has got to. The admin field asks until the MP4
     * the homepage plays is ready, then saves its address.
     */
    public function videoStatus(string $uid, CloudflareStream $stream): JsonResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/i', $uid), 404);

        return $this->answer(fn () => $stream->mp4($uid));
    }

    private function answer(callable $call): JsonResponse
    {
        try {
            return response()->json($call());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
