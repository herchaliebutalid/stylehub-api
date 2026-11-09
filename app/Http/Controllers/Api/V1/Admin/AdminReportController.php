<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminReportOverviewRequest;
use App\Services\Admin\AdminReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminReportService $reports
    ) {}

    public function overview(
        AdminReportOverviewRequest $request
    ): Response {
        $format = $request->query('format', 'json');

        if ($format === 'latest') {
            return $this->downloadLatest($request);
        }

        $data = $this->reports->overview(
            $request->safe()->except('format')
        );

        $generatedAt = now();

        // Normal report statistics remain unchanged.
        if ($format !== 'pdf') {
            return response()->json([
                'success' => true,
                'data' => $data,
                'generated_at' => $generatedAt->toISOString(),
            ]);
        }

        $dateFrom = $data['period']['date_from'] ?? null;
        $dateTo = $data['period']['date_to'] ?? null;

        $range = (
            $dateFrom === null &&
            $dateTo === null
        )
            ? 'All_Records'
            : ($dateFrom ?: 'Any_Start')
                . '_to_'
                . ($dateTo ?: 'Any_End');

        $filename =
            'StyleHub_Report_'
            . $range
            . '_'
            . $generatedAt->format('Ymd_His')
            . '.pdf';

        // Generate the formal black-and-white report.
        $pdf = Pdf::loadView(
            'admin.reports.pdf',
            [
                'report' => $data,
                'generatedAt' => $generatedAt
                    ->copy()
                    ->timezone('Asia/Manila'),
            ]
        )->setPaper('a4', 'portrait');

        $pdf->render();

        // Add black page numbers to every page.
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();

        $fontFamily = is_file(
            public_path('fonts/arial.ttf')
        )
            ? 'StyleHub Arial'
            : 'Helvetica';

        $font = $dompdf
            ->getFontMetrics()
            ->getFont($fontFamily, 'normal');

        $canvas->page_text(
            $canvas->get_width() - 101,
            $canvas->get_height() - 37,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            8,
            [0, 0, 0]
        );

        $pdfBytes = $pdf->output();

        // Each administrator has their own latest report.
        $base = $this->storageBase($request);
        $disk = Storage::disk('local');

        if (!$disk->put(
            $base . '/latest.pdf',
            $pdfBytes
        )) {
            abort(
                500,
                'The PDF could not be saved.'
            );
        }

        $metadata = json_encode(
            [
                'filename' => $filename,
                'generated_at' => $generatedAt->toISOString(),
            ],
            JSON_THROW_ON_ERROR
        );

        if (!$disk->put(
            $base . '/latest.json',
            $metadata
        )) {
            abort(
                500,
                'The report metadata could not be saved.'
            );
        }

        return response(
            $pdfBytes,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function downloadLatest(
        AdminReportOverviewRequest $request
    ): Response {
        $base = $this->storageBase($request);
        $disk = Storage::disk('local');

        $pdfPath = $base . '/latest.pdf';
        $metadataPath = $base . '/latest.json';

        if (!$disk->exists($pdfPath)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'No saved PDF is available yet.',
            ], 404);
        }

        // The PDF can still be downloaded if its metadata
        // file is missing.
        $metadata = $disk->exists($metadataPath)
            ? json_decode(
                $disk->get($metadataPath),
                true
            )
            : [];

        $filename = is_array($metadata)
            ? ($metadata['filename'] ?? '')
            : '';

        if (
            !is_string($filename) ||
            !preg_match(
                '/\AStyleHub_Report_[A-Za-z0-9_-]+\.pdf\z/',
                $filename
            )
        ) {
            $filename = 'StyleHub_Latest_Report.pdf';
        }

        return $disk->download(
            $pdfPath,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function storageBase(
        AdminReportOverviewRequest $request
    ): string {
        return 'admin-reports/'
            . (int) $request
                ->user()
                ->getAuthIdentifier();
    }
}