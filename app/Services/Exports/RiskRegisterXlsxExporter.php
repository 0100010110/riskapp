<?php

namespace App\Services\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class RiskRegisterXlsxExporter
{
    public function streamDownload(Collection $risks): StreamedResponse
    {
        $templatePath = base_path('app/templates/risk-register-template.xlsx');

        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Template tidak ditemukan: {$templatePath}");
        }

        Log::info('[RiskXlsx] loading template', ['path' => $templatePath]);

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        
        $sheet->setCellValue('B4', (string) ($risks->first()->c_risk_year ?? ''));
        $sheet->setCellValue('C17', (string) ($risks->first()->i_risk ?? ''));
        $sheet->setCellValue('D17', (string) data_get($risks->first(), 'taxonomy.c_taxonomy', ''));
        $sheet->setCellValue('E17', (string) data_get($risks->first(), 'taxonomy.n_taxonomy', ''));
        $sheet->setCellValue('G17', (string) ($risks->first()->c_risk_status ?? ''));

        
        $sheet->getStyle('E17')->getAlignment()->setWrapText(true);

        $filename = 'risk-register-' . date('Ymd-His') . '.xlsx';

        Log::info('[RiskXlsx] streaming download', ['filename' => $filename]);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
