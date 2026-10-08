<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkUpload\NamingGuide;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BulkUploadNamingGuideController extends Controller
{
    /** The filename rules as a PDF, to keep, print or send to whoever exports the renders. */
    public function __invoke(Request $request): Response
    {
        $pdf = Pdf::loadView('pdf.bulk-upload-naming-guide', [
            'groups' => NamingGuide::groups(),
            'general' => NamingGuide::general(),
            'generatedAt' => now(),
            'admin' => $request->user()?->name,
        ])->setPaper('a4');

        return $pdf->download('bulk-upload-naming-conventions.pdf');
    }
}
