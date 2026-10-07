<?php

namespace App\Http\Controllers;

use App\Models\RankYakSetting;
use App\Services\RankYak\RankYakClient;
use App\Services\RankYak\RankYakImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class RankYakWebhookController extends Controller
{
    /**
     * RankYak's `article.published` webhook.
     *
     * RankYak does not sign its webhooks, so the long random token in the
     * address is what proves a request came from the URL the admin gave it.
     * The admin can replace the token at any time, which retires the old URL.
     */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $settings = RankYakSetting::current();

        if (! $settings->tokenMatches($token)) {
            abort(404);
        }

        if (! $settings->enabled) {
            return response()->json(['ok' => false, 'message' => 'The RankYak integration is switched off in the admin.'], 503);
        }

        $settings->forceFill(['last_webhook_at' => now()])->save();

        $importer = RankYakImporter::make();

        try {
            ['post' => $post, 'created' => $created] = $importer->import($request->json()->all());
        } catch (ValidationException $e) {
            $settings->recordError('A webhook was rejected: '.collect($e->errors())->flatten()->implode(' '));

            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $url = route('journal.show', $post->slug);

        /* Tell RankYak where it went live; if that fails, the hourly sync tries again */
        try {
            $importer->reportUrl($post);
        } catch (Throwable $e) {
            $settings->recordError(RankYakClient::explain($e));
        }

        return response()->json([
            'ok' => true,
            'created' => $created,
            'id' => $post->id,
            'status' => $post->status,
            'url' => $url,
        ], $created ? 201 : 200);
    }
}
