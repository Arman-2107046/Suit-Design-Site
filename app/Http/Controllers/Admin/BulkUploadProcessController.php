<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkUpload\BatchReport;
use App\Services\BulkUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkUploadProcessController extends Controller
{
    /**
     * File an uploaded batch.
     *
     * This is a route rather than a Livewire action because a batch has to
     * outlive the page that started it: the admin can navigate away mid-upload,
     * and by the time the last file lands the Livewire component that would
     * have handled it no longer exists.
     */
    public function __invoke(Request $request, BulkUploadService $service, BatchReport $report): JsonResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:500'],
            'files.*.name' => ['required', 'string', 'max:255'],
            'files.*.url' => ['required', 'url', 'max:2048'],
        ]);


        return response()->json(
            $report->summarise($service->process($data['files']))
        );
    }
}
