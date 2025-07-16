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
use Illuminate\Support\Str;

class TaxController extends Controller
{

    public function store(Request $request)
    {
        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);
        $tax_type = $request->tax_type;

        $contracts = Contract::with(['clientUsers', 'template', 'notaire'])
                ->whereBetween('created_at', [$start, $end])
                ->whereHas('template', function ($query) use ($tax_type) {
                    $query->where('taxe_type', $tax_type);
                })
                ->get();

        if ($contracts->isEmpty()) {
            return response()->json([
                'message' => __('tax.no_contracts')  // Define this in your translation files
            ], 422);
        }


        $templatePath = public_path('templates/tax_template.docx');

        // Correct output path definition
        $outputPath = public_path('storage/tax_reports/tax_report-' . now()->format('YmdHis') . '.docx');

        // Ensure directory exists
        $reportsDir = dirname($outputPath);
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }

        $template = new TemplateProcessor($templatePath);
        $template->cloneRow('${نوع_العقد}', $contracts->count());

        foreach ($contracts as $i => $contract) {
            $row = $i + 1;

            $clientsList = $contract->clientUsers->map(function ($client) {
                return "{$client->nom} {$client->prenom}";
            })->implode('/ ');

            $template->setValue("رقم#{$row}", $row);
            $template->setValue("تاريخ_العقد#{$row}", $contract->created_at->format('Y-m-d'));
            $template->setValue("العملاء#{$row}", $clientsList);
            $template->setValue("نوع_العقد#{$row}", $contract->template->contract_subtype ?? 'غير محدد');
            $template->setValue("نسبة_الضريبة#{$row}", $contract->template->taxe_pourcentage ?? '');
            //$template->setValue("السعر#{$row}", number_format($contract->template->attributes['taxable_price'] ?? 0, 2));
             // Handle tax percentage based on type
            if ($contract->template->taxe_type == "Variable") {
                $template->setValue("نسبية#{$row}", $contract->template->taxe_pourcentage ?? '');
                $template->setValue("ثابتة#{$row}", ''); // Empty the fixed tax field
            } else {
                $template->setValue("ثابتة#{$row}", $contract->template->taxe_pourcentage ?? '');
                $template->setValue("نسبية#{$row}", ''); // Empty the variable tax field
            }
        }

        $notaireName = $contracts->first()->notaire->nom . ' ' . $contracts->first()->notaire->prenom;
        $template->setValue("موثق", $notaireName);

        // Calculate total tax pourcentage
        $totalTax = $contracts->sum(function ($contract) {
            return is_numeric($contract->template->taxe_pourcentage) ? floatval($contract->template->taxe_pourcentage) : 0;
        });

        // Replace based on tax_type (assuming all contracts have the same tax_type from the request)
        if ($tax_type === 'Variable') {
            $template->setValue('المجموعن', number_format($totalTax, 1));
            $template->setValue('المجموعث', ''); // Clear other placeholder
        } else {
            $template->setValue('المجموعث', number_format($totalTax, 1));
            $template->setValue('المجموعن', ''); // Clear other placeholder
        }
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
        Log::info($bon);
        $templatePath = public_path('templates/bon_template.docx');
        $tempDir = storage_path('app/temp');

        // Crée le dossier temporaire si nécessaire
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        try {
            $uniqueName = $bon['notaryOffice'] .'_'. Str::uuid();
            $docxPath = $tempDir . '/' . $uniqueName . '.docx';
            $generatedPdfPath = $tempDir . '/' . $uniqueName . '.pdf';

            // Copie du template
            copy($templatePath, $docxPath);
            $template = new TemplateProcessor($docxPath);

            // Préparation des champs
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

            // Enregistre le .docx
            $template->saveAs($docxPath);

            // Convertit en PDF avec LibreOffice
            $libreOfficeBin = env('LIBREOFFICE_BIN');
            $command = "\"{$libreOfficeBin}\" --headless --convert-to pdf --outdir " . escapeshellarg($tempDir) . ' ' . escapeshellarg($docxPath);

            $output = shell_exec($command . " 2>&1");

            if (!file_exists($generatedPdfPath)) {
                throw new \Exception("PDF conversion failed: " . ($output ?? 'No output'));
            }

            // Stocke le fichier PDF dans le disque public
            $publicPdfName = $uniqueName . '.pdf';
            $publicPdfPath = 'bons/' . $publicPdfName;

            if (!Storage::disk('public')->put($publicPdfPath, file_get_contents($generatedPdfPath))) {
                throw new \Exception("Could not store PDF");
            }

            // Nettoyage
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

            // Nettoyage de secours
            @unlink($docxPath ?? '');
            @unlink($generatedPdfPath ?? '');

            return response()->json([
                'error' => 'PDF generation failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }

}
