<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;


class TaxController extends Controller
{

    public function store(Request $request)
    {
        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        $contracts = Contract::with(['clients', 'template', 'notaire'])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        if ($contracts->isEmpty()) {
            return response()->json(['message' => 'لا توجد عقود خلال هذه الفترة'], 404);
        }

        $templatePath = public_path('templates/tax-template.docx');

        // Correct output path definition
        $outputPath = storage_path('app/public/tax_reports/tax_report-' . now()->format('YmdHis') . '.docx');

        // Ensure directory exists
        $reportsDir = dirname($outputPath);
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }

        $template = new TemplateProcessor($templatePath);
        $template->cloneRow('${نوع_العقد}', $contracts->count());

        foreach ($contracts as $i => $contract) {
            $row = $i + 1;
            $clientsList = $contract->clients->map(function ($client) {
                return "{$client->nom} {$client->prenom}";
            })->implode('، ');

            $template->setValue("تاريخ_العقد#{$row}", $contract->created_at->format('Y-m-d'));
            $template->setValue("العملاء#{$row}", $clientsList);
            $template->setValue("نوع_العقد#{$row}", $contract->template->contract_subtype ?? 'غير محدد');
            $template->setValue("نسبة_الضريبة#{$row}", $contract->template->taxe_pourcentage ?? '');
            //$template->setValue("السعر#{$row}", number_format($contract->template->attributes['taxable_price'] ?? 0, 2));
             // Handle tax percentage based on type
            if ($contract->template->taxe_type == "varied") {
                $template->setValue("نسبية#{$row}", $contract->template->taxe_pourcentage ?? '');
                $template->setValue("ثابتة#{$row}", ''); // Empty the fixed tax field
            } else {
                $template->setValue("ثابتة#{$row}", $contract->template->taxe_pourcentage ?? '');
                $template->setValue("نسبية#{$row}", ''); // Empty the variable tax field
            }
        }

        $notaireName = $contracts->first()->notaire->nom . ' ' . $contracts->first()->notaire->prenom;
        $template->setValue("موثق", $notaireName);

        try {
            $template->saveAs($outputPath);
            return response()->download($outputPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function generatePreview(Request $request)
    {
        $bon = $request->all();
        $templatePath = public_path('templates/bon_template.docx');
        $tempDir = storage_path('app/temp');

        // Create temp directory if needed
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Prepare template paths
        $tempDocPath = $tempDir . '/bon_temp_' . time() . '.docx';
        $docxPath = $tempDir . '/preview_bon_' . time() . '.docx';
        $generatedPdfPath = $tempDir . '/preview_bon_' . time() . '.pdf';

        try {
            // Copy template
            copy($templatePath, $tempDocPath);

            // Process template
            $template = new TemplateProcessor($tempDocPath);

            // Handle empty values and set fields
            $fields = [
                'الموثق' => $bon['notaryOffice'] ?? '',
                'رقم_الوصل' => $bon['receiptNumber'] ?? '',
                'المبلغ' => $bon['amount'] ?? '',
                'المبلغ_حرفيا' => $bon['amountInWords'] ?? '',
                'العنوان' => $bon['address'] ?? '',
                'نوع_العقد' => $bon['contractType'] ?? '',
                'التاريخ' => $bon['date'] ?? '',
                'أصل' => $bon['original'] ?? '',
                'نسخة' => $bon['copy'] ?? '',
                'توثيق' => $bon['documentation'] ?? '',
                'النشر' => $bon['publication'] ?? '',
                'استشارة' => $bon['consultation'] ?? '',
                'أجرة الاطلاع' => $bon['consultationFee'] ?? '',
                'أجرة العمل' => $bon['workFee'] ?? '',
                'أخرى' => $bon['others'] ?? '',
                'طابع الحجم' => $bon['stamp'] ?? '',
                'تسجيل' => $bon['registration'] ?? '',
                'اشهار' => $bon['advertisement'] ?? '',
                'ر.ق.م' => $bon['rkm'] ?? '',
                'اعلانات' => $bon['announcements'] ?? '',
                'ايداع' => $bon['deposit'] ?? '',
                'BOAL' => $bon['boal'] ?? '',
                'القيد/ الشطب' => $bon['registration_or_cancellation'] ?? '',
                'العميل' => isset($bon['clients']) ?
                    collect($bon['clients'])->map(fn($c) => ($c['nom'] ?? '') . ' ' . ($c['prenom'] ?? ''))->implode(', ') : ''
            ];

            foreach ($fields as $key => $value) {
                $template->setValue($key, $value);
            }

            // Save DOCX
            $template->saveAs($docxPath);

            // Convert to PDF - Using full path to LibreOffice
            $command = "\"C:\\Program Files\\LibreOffice\\program\\soffice.exe\" --headless --convert-to pdf --outdir " . escapeshellarg($tempDir) . " " . escapeshellarg($docxPath);

            // Execute command with timeout
            $output = shell_exec($command . " 2>&1");

            if (!file_exists($generatedPdfPath)) {
                throw new \Exception("PDF conversion failed: " . ($output ?? 'No output'));
            }

            // Store PDF
            $pdfName = 'preview_bon_' . time() . '.pdf';
            $publicPdfPath = 'bons_previews/' . $pdfName;

            if (!Storage::disk('public')->put($publicPdfPath, file_get_contents($generatedPdfPath))) {
                throw new \Exception("Could not store PDF");
            }

            // Clean up temporary files
            @unlink($tempDocPath);
            @unlink($docxPath);
            @unlink($generatedPdfPath);

            return response()->json([
                'preview_url' => asset('storage/' . $publicPdfPath)
            ]);

        } catch (\Exception $e) {
            \Log::error('PDF Generation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Clean up any created files
            @unlink($tempDocPath ?? '');
            @unlink($docxPath ?? '');
            @unlink($generatedPdfPath ?? '');

            return response()->json([
                'error' => 'PDF generation failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }

}
