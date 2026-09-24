<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BulkUploadReportController extends Controller
{
    /**
     * Turn a finished batch's rejections into a report the admin can keep —
     * a PDF to read, or a CSV to work through.
     *
     * The list arrives from the browser because half of it never reaches us:
     * files that fail at Cloudinary are only ever known client-side. It is
     * treated as untrusted text — validated, capped, and escaped on the way out.
     */
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'format' => ['nullable', 'in:pdf,csv'],
            'rejected' => ['required', 'array', 'min:1', 'max:500'],
            'rejected.*.name' => ['required', 'string', 'max:255'],
            'rejected.*.stage' => ['required', 'string', 'max:60'],
            'rejected.*.reason' => ['nullable', 'string', 'max:600'],
            'batch.total' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'batch.filed' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);


        $generatedAt = now();

        $name = 'bulk-upload-rejected-'.$generatedAt->format('Y-m-d-Hi');


        if (($data['format'] ?? 'pdf') === 'csv') {
            return $this->csv($data['rejected'], $name.'.csv');
        }


        $pdf = Pdf::loadView('pdf.bulk-upload-rejected', [
            'rejected' => $data['rejected'],
            'total' => $data['batch']['total'] ?? count($data['rejected']),
            'filed' => $data['batch']['filed'] ?? 0,
            'generatedAt' => $generatedAt,
            'admin' => $request->user()?->name,
        ])->setPaper('a4');


        return $pdf->download($name.'.pdf');
    }



    /**
     * @param  list<array{name: string, stage: string, reason: string|null}>  $rejected
     */
    private function csv(array $rejected, string $filename): Response
    {
        $handle = fopen('php://temp', 'r+');

        /* Excel reads a CSV as the local codepage unless a BOM says otherwise */
        fwrite($handle, "\xEF\xBB\xBF");

        /* escape: '' is the value PHP 8.4 deprecates the default in favour of, and
           it keeps backslashes in reasons from being read as escape characters. */
        fputcsv($handle, ['#', 'File', 'Rejected at', 'Reason'], escape: '');


        foreach ($rejected as $i => $row) {
            fputcsv($handle, [
                $i + 1,
                $this->inert($row['name']),
                $this->inert($row['stage']),
                $this->inert($row['reason'] ?? 'No reason given.'),
            ], escape: '');
        }


        rewind($handle);

        $csv = stream_get_contents($handle);

        fclose($handle);


        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }



    /**
     * A filename such as "=cmd.png" is a formula to a spreadsheet. Leading
     * space keeps the cell readable while stopping it from being evaluated.
     */
    private function inert(string $value): string
    {
        return str_starts_with($value, '=')
            || str_starts_with($value, '+')
            || str_starts_with($value, '-')
            || str_starts_with($value, '@')
            || str_starts_with($value, "\t")
            || str_starts_with($value, "\r")
                ? ' '.$value
                : $value;
    }
}
