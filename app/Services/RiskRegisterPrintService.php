<?php

namespace App\Services;

use App\Models\Tmrisk;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiskRegisterPrintService
{
    public function streamDownload(Collection $risks): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($risks);

        $filename = 'risk-register-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function buildSpreadsheet(Collection $risks): Spreadsheet
    {
        /** @var \Illuminate\Support\Collection<int,Tmrisk> $risks */
        $risks = $risks->values();

        $risks = $risks->sort(function (Tmrisk $a, Tmrisk $b) {
            $pa = (int) ($a->f_risk_primary ?? 0);
            $pb = (int) ($b->f_risk_primary ?? 0);

            if ($pa !== $pb) {
                return $pb <=> $pa;
            }

            $ra = (string) ($a->i_risk ?? '');
            $rb = (string) ($b->i_risk ?? '');
            return strcmp($ra, $rb);
        })->values();

        $primary = $risks->first(fn (Tmrisk $r) => (bool) ($r->f_risk_primary ?? false)) ?? $risks->first();

        $templatePath = (string) (env('RISK_REGISTER_TEMPLATE_PATH') ?: base_path('app/templates/risk-register-template.xlsx'));
        if (! is_file($templatePath)) {
            throw new \RuntimeException("Template XLSX not found: {$templatePath}");
        }

        $spreadsheet = IOFactory::load($templatePath);

        $sheet = $spreadsheet->getSheetByName('84-FI-KU-001.2')
            ?? $spreadsheet->getActiveSheet();

        $baseRow = 17;
        $extra = max(0, $risks->count() - 1);
        if ($extra > 0) {
            $sheet->insertNewRowBefore($baseRow + 1, $extra);

            for ($i = 1; $i <= $extra; $i++) {
                $this->copyRowStyleAndMerges($sheet, $baseRow, $baseRow + $i);
            }
        }

        // HEADER
        $year = (string) ($primary->c_risk_year ?? '');
        $sheet->setCellValue('B4', 'Periode : ' . $year);
        $this->wrapCell($sheet, 'B4');

        $latest = $this->latestDate($risks);
        $sheet->setCellValue('E6', ': ' . ($latest ? $latest->format('d M Y') : ''));
        $this->wrapCell($sheet, 'E6');

        $sheet->setCellValue('E7', ': ' . (string) ($primary->c_org_owner ?? ''));
        $this->wrapCell($sheet, 'E7');

        $impacted = $this->mergeImpactedOrgs($risks, $primary);
        $sheet->setCellValue('E8', ': ' . $impacted);
        $this->wrapCell($sheet, 'E8');

        $sheet->setCellValue('E9', ': ' . (string) ($primary->c_control_effectiveness ?? ''));
        $this->wrapCell($sheet, 'E9');

        $sheet->setCellValue('E10', ': ' . $this->longestExposurePeriod($risks));
        $this->wrapCell($sheet, 'E10');

        // SIGNATURE
        $sigRow = 29 + $extra;

        $iCreate = (string) ($primary->i_entry ?? '');
        $iUpdate = (string) ($primary->i_update ?? '');
        if ($iUpdate === '' || $iUpdate === '0') {
            $iUpdate = $iCreate;
        }

        $sheet->setCellValue('B' . $sigRow, $iCreate);
        $this->wrapRange($sheet, "B{$sigRow}:I" . ($sigRow + 2));

        $sheet->setCellValue('J' . $sigRow, $iUpdate);
        $this->wrapRange($sheet, "J{$sigRow}:R" . ($sigRow + 2));

        // BODY
        foreach ($risks as $idx => $risk) {
            $r = $baseRow + $idx;

            $taxonomyCode = (string) ($risk->taxonomy?->c_taxonomy ?? '');
            $taxonomyName = (string) ($risk->taxonomy?->n_taxonomy ?? '');

            $sheet->setCellValueExplicit("D{$r}", $taxonomyCode, DataType::TYPE_STRING);
            $sheet->setCellValue("E{$r}", $taxonomyName);

            $sheet->setCellValueExplicit("C{$r}", (string) ($risk->i_risk ?? ''), DataType::TYPE_STRING);

            $statusLabel = $this->statusLabel($risk->c_risk_status);
            $sheet->setCellValue("G{$r}", $statusLabel);

            $sheet->setCellValue("F{$r}", ((int) ($risk->f_risk_primary ?? 0) === 1) ? 'Primary Risk' : 'Non Primary Risk');

            /**
             * H = Risk Event
             * I = Existing Control (e_exist_ctrl)
             */
            $sheet->setCellValue("H{$r}", (string) ($risk->e_risk_event ?? ''));
            $sheet->setCellValue("I{$r}", (string) ($risk->e_exist_ctrl ?? ''));

            $sheet->setCellValue("J{$r}", (string) ($risk->e_risk_cause ?? ''));
            $sheet->setCellValue("K{$r}", (string) ($risk->e_risk_impact ?? ''));

            $sheet->setCellValue("L{$r}", (string) ($risk->c_risk_impact_value ?? ''));
            $sheet->setCellValue("M{$r}", (string) ($risk->c_risk_impact_unit ?? ''));

            $sheet->setCellValue("N{$r}", (string) ($risk->c_risk_kri ?? ''));
            $sheet->setCellValue("O{$r}", (string) ($risk->c_risk_kri_unit ?? ''));

            $sheet->setCellValue("P{$r}", (string) ($risk->c_risk_threshold_safe ?? ''));
            $sheet->setCellValue("Q{$r}", (string) ($risk->c_risk_threshold_caution ?? ''));
            $sheet->setCellValue("R{$r}", (string) ($risk->c_risk_threshold_danger ?? ''));

            $this->wrapRange($sheet, "B{$r}:R{$r}");
            $sheet->getRowDimension($r)->setRowHeight(-1);
        }

        return $spreadsheet;
    }

    protected function latestDate(Collection $risks): ?Carbon
    {
        $best = null;

        foreach ($risks as $r) {
            $raw = $r->d_update ?? $r->d_entry ?? null;
            if (! $raw) continue;

            try {
                $dt = $raw instanceof Carbon ? $raw : Carbon::parse($raw);
            } catch (\Throwable) {
                continue;
            }

            if (! $best || $dt->greaterThan($best)) {
                $best = $dt;
            }
        }

        return $best;
    }

    protected function mergeImpactedOrgs(Collection $risks, Tmrisk $primary): string
    {
        $all = [];

        $pushParts = function (?string $value) use (&$all) {
            $value = trim((string) $value);
            if ($value === '') return;

            $parts = array_map('trim', explode(',', $value));
            foreach ($parts as $p) {
                if ($p === '') continue;
                $all[] = $p;
            }
        };

        $pushParts($primary->c_org_impact ?? '');

        foreach ($risks as $r) {
            if ((int) ($r->i_id_risk ?? 0) === (int) ($primary->i_id_risk ?? 0)) continue;
            $pushParts($r->c_org_impact ?? '');
        }

        $unique = [];
        foreach ($all as $v) {
            $k = mb_strtolower($v);
            if (! isset($unique[$k])) $unique[$k] = $v;
        }

        return implode(', ', array_values($unique));
    }

    protected function longestExposurePeriod(Collection $risks): string
    {
        $best = '';

        foreach ($risks as $r) {
            $v = trim((string) ($r->d_exposure_period ?? ''));
            if (mb_strlen($v) > mb_strlen($best)) {
                $best = $v;
            }
        }

        return $best;
    }

    protected function statusLabel($state): string
    {
        $opts = Tmrisk::statusOptions();

        if (is_numeric($state)) {
            $k = (int) $state;
            return $opts[$k] ?? (string) $state;
        }

        return (string) $state;
    }

    protected function wrapCell(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    protected function wrapRange(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    protected function copyRowStyleAndMerges(Worksheet $sheet, int $srcRow, int $dstRow): void
    {
        $sheet->duplicateStyle($sheet->getStyle("B{$srcRow}:R{$srcRow}"), "B{$dstRow}:R{$dstRow}");
        $sheet->getRowDimension($dstRow)->setRowHeight($sheet->getRowDimension($srcRow)->getRowHeight());

        foreach (array_keys($sheet->getMergeCells()) as $mergedRange) {
            if (! preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $mergedRange, $m)) {
                continue;
            }

            $r1 = (int) $m[2];
            $r2 = (int) $m[4];

            if ($r1 === $srcRow && $r2 === $srcRow) {
                $newRange = $m[1] . $dstRow . ':' . $m[3] . $dstRow;
                if (! isset($sheet->getMergeCells()[$newRange])) {
                    $sheet->mergeCells($newRange);
                }
            }
        }
    }
}
