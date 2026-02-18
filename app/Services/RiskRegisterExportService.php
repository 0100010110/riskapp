<?php

namespace App\Services;

use App\Models\Tmrisk;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RiskRegisterExportService
{
    public function download(Collection $risks): BinaryFileResponse
    {
        $path = $this->exportToPath($risks);

        return response()->download(
            $path,
            'risk-register-' . now()->format('Ymd_His') . '.xlsx'
        )->deleteFileAfterSend(true);
    }

    private function exportToPath(Collection $risks): string
    {
        /** @var Collection<int, Tmrisk> $risks */
        $risks = $risks->values();

        $templatePath = app_path('templates/risk-register-template.xlsx');

        $spreadsheet = IOFactory::load($templatePath);

        $sheet = $spreadsheet->getSheetByName('84-FI-KU-001.2') ?? $spreadsheet->getActiveSheet();

        $primary = $risks->first(fn ($r) => (bool) $r->f_risk_primary) ?? $risks->first();
        $sorted  = $risks->sortBy(fn ($r) => [(bool) $r->f_risk_primary ? 0 : 1, (string) $r->i_risk])->values();

        $year = (string) ($primary->c_risk_year ?? '');
        $ownerOrg = (string) ($primary->c_org_owner ?? '');

        $impactedOrgs = $this->mergeCommaSeparatedStringsPrimaryFirst(
            $this->pickFirstNonEmpty($primary, ['c_org_impact', 'c_org_impacted', 'c_org_impact_org', 'c_org_impacted_org']),
            $sorted
                ->reject(fn ($r) => $primary && $r->getKey() === $primary->getKey())
                ->map(fn ($r) => $this->pickFirstNonEmpty($r, ['c_org_impact', 'c_org_impacted', 'c_org_impact_org', 'c_org_impacted_org']))
                ->all(),
        );

        $controlEffectiveness = (string) ($this->pickFirstNonEmpty($primary, [
            'c_control_effectiveness',
            'c_control_efectiveness',
            'c_control_effectivity',
            'c_control_effectivity',
        ]) ?? '');

        $exposurePeriod = (string) $sorted
            ->map(fn ($r) => $this->pickFirstNonEmpty($r, ['d_exposure_period', 'c_exposure_period', 'c_exposureperiod']))
            ->filter(fn ($v) => filled($v))
            ->sortByDesc(fn ($v) => mb_strlen((string) $v))
            ->first();

        Carbon::setLocale('id');

        $latestDate = $sorted
            ->map(fn ($r) => $r->d_update ?: $r->d_entry)
            ->filter()
            ->map(fn ($v) => Carbon::parse($v))
            ->sortDesc()
            ->first();

        $dateText = $latestDate ? $latestDate->translatedFormat('j F Y') : '';


        $sheet->setCellValue('B4', trim('Periode : ' . $year));
        $sheet->setCellValue('E7', ': ' . $ownerOrg);
        $sheet->setCellValue('E8', ': ' . $impactedOrgs);
        $sheet->setCellValue('E9', ': ' . $controlEffectiveness);
        $sheet->setCellValue('E6', ': ' . $dateText);
        $sheet->setCellValue('E10', ': ' . $exposurePeriod);

        // ========== BODY ==========
        $startRow = 17;
        $count    = $sorted->count();
        $extraRows = max(0, $count - 1);

        if ($count > 1) {
            for ($i = 1; $i < $count; $i++) {
                $targetRow = $startRow + $i; // insert sebelum row ini
                $sheet->insertNewRowBefore($targetRow, 1);

                $this->copyRowStyle($sheet, $startRow, $targetRow, 2, 18); // B..R

            }
        }

        foreach ($sorted as $i => $risk) {
            $row = $startRow + $i;

            $sheet->setCellValue("B{$row}", '');

            // C: Risk No
            $sheet->setCellValue("C{$row}", (string) ($risk->i_risk ?? ''));

            // D: Taxonomy code
            $sheet->setCellValue("D{$row}", (string) optional($risk->taxonomy)->c_taxonomy);

            // E: Taxonomy name
            $sheet->setCellValue("E{$row}", (string) optional($risk->taxonomy)->n_taxonomy);

            // F: Primary label
            $sheet->setCellValue("F{$row}", (bool) $risk->f_risk_primary ? 'Primary Risk' : 'Non Primary Risk');

            // G: Status label
            $statusOptions = Tmrisk::statusOptions();
            $statusLabel = $statusOptions[(int) ($risk->c_risk_status ?? 0)] ?? (string) ($risk->c_risk_status ?? '');
            $sheet->setCellValue("G{$row}", $statusLabel);

            /**
             * H: Risk Event (sebelumnya masuk merge H:I)
             * I: Existing Control (e_exist_ctrl)
             */
            $sheet->setCellValue("H{$row}", (string) ($risk->e_risk_event ?? ''));
            $sheet->setCellValue("I{$row}", (string) ($risk->e_exist_ctrl ?? ''));

            // J: Risk Cause
            $sheet->setCellValue("J{$row}", (string) ($risk->e_risk_cause ?? ''));

            // K: Risk Impact (deskripsi)
            $sheet->setCellValue("K{$row}", (string) ($risk->e_risk_impact ?? ''));

            // L: Risk impact value
            $sheet->setCellValue("L{$row}", $risk->v_risk_impact ?? '');

            // M: Impact unit
            $sheet->setCellValue("M{$row}", (string) ($risk->c_risk_impactunit ?? ''));

            // N: KRI
            $sheet->setCellValue("N{$row}", (string) ($risk->e_kri ?? ''));

            // O: KRI unit
            $sheet->setCellValue("O{$row}", (string) ($risk->c_kri_unit ?? ''));

            // P/Q/R thresholds
            $sheet->setCellValue("P{$row}", $risk->v_threshold_safe ?? '');
            $sheet->setCellValue("Q{$row}", $risk->v_threshold_caution ?? '');
            $sheet->setCellValue("R{$row}", $risk->v_threshold_danger ?? '');

            $sheet->getStyle("B{$row}:R{$row}")
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $this->autosizeRowHeightForWrappedCells($sheet, $row, ['H', 'I', 'J', 'K'], baseHeight: 18, maxHeight: 140);
        }

        // ========== SIGNATURE (geser jika extra rows) ==========
        $baseStart = 20;
        $baseEnd   = 22;

        $sigStart = $baseStart + $extraRows;
        $sigEnd   = $baseEnd + $extraRows;

        $employeeCache = $this->resolveEmployeeCacheService();

        $createdById = $primary->i_entry ?? null;
        $checkedById = $primary->i_update ?? $primary->i_entry ?? null;

        $createdName = $this->employeeNameById($employeeCache, $createdById);
        $checkedName = $this->employeeNameById($employeeCache, $checkedById);

        $leftRange  = "B{$sigStart}:I{$sigEnd}";
        $rightRange = "J{$sigStart}:R{$sigEnd}";

        $this->safeMerge($sheet, $leftRange);
        $this->safeMerge($sheet, $rightRange);

        $sheet->setCellValue("B{$sigStart}", "Dibuat oleh :\n" . $createdName);
        $sheet->setCellValue("J{$sigStart}", "Diperiksa oleh :\n" . $checkedName);

        $sheet->getStyle($leftRange)->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle($rightRange)->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $outPath = $dir . DIRECTORY_SEPARATOR . 'risk-register-' . now()->format('Ymd_His') . '-' . Str::random(6) . '.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outPath);

        return $outPath;
    }

    private function pickFirstNonEmpty(object $obj, array $fields): ?string
    {
        foreach ($fields as $f) {
            if (isset($obj->{$f}) && filled($obj->{$f})) {
                return (string) $obj->{$f};
            }
        }
        return null;
    }

    private function copyRowStyle(Worksheet $sheet, int $sourceRow, int $targetRow, int $fromCol, int $toCol): void
    {
        $sheet->getRowDimension($targetRow)->setRowHeight(
            $sheet->getRowDimension($sourceRow)->getRowHeight()
        );

        for ($col = $fromCol; $col <= $toCol; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $source = $colLetter . $sourceRow;
            $target = $colLetter . $targetRow;
            $sheet->duplicateStyle($sheet->getStyle($source), $target);
        }
    }

    private function mergeCommaSeparatedStringsPrimaryFirst(?string $primary, array $others): string
    {
        $normalize = function (?string $value): array {
            if (! filled($value)) {
                return [];
            }

            return collect(explode(',', (string) $value))
                ->map(fn ($v) => trim($v))
                ->filter()
                ->values()
                ->all();
        };

        $result = [];
        foreach ($normalize($primary) as $v) {
            $key = mb_strtoupper($v);
            if (! isset($result[$key])) {
                $result[$key] = $v;
            }
        }

        foreach ($others as $item) {
            foreach ($normalize($item) as $v) {
                $key = mb_strtoupper($v);
                if (! isset($result[$key])) {
                    $result[$key] = $v;
                }
            }
        }

        return implode(', ', array_values($result));
    }

    private function autosizeRowHeightForWrappedCells(
        Worksheet $sheet,
        int $row,
        array $columns,
        int $baseHeight = 26,
        int $maxHeight = 320
    ): void {
        $maxLines = 1;

        foreach ($columns as $colSpec) {
            $colSpec = strtoupper(trim($colSpec));
            if ($colSpec === '') continue;

            $textCellCol = $colSpec;
            $totalWidth = 0.0;

            if (str_contains($colSpec, ':')) {
                [$c1, $c2] = array_map('trim', explode(':', $colSpec, 2));
                $textCellCol = $c1;

                $startIdx = Coordinate::columnIndexFromString($c1);
                $endIdx   = Coordinate::columnIndexFromString($c2);

                for ($i = $startIdx; $i <= $endIdx; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $w = (float) $sheet->getColumnDimension($col)->getWidth();
                    $totalWidth += ($w > 0 ? $w : 10);
                }
            } else {
                $w = (float) $sheet->getColumnDimension($colSpec)->getWidth();
                $totalWidth = ($w > 0 ? $w : 20);
            }

            $text = (string) $sheet->getCell($textCellCol . $row)->getValue();

            $lines = max(1, substr_count($text, "\n") + 1);

            $charsPerLine = max(8, (int) floor($totalWidth * 0.9));
            $plain = str_replace("\n", '', $text);
            $wrapLines = (int) ceil(max(1, mb_strlen($plain)) / $charsPerLine);

            $maxLines = max($maxLines, $lines, $wrapLines);
        }

        $multiplier   = 1.6;
        $paddingLines = 1;

        $effectiveLines = (int) ceil(($maxLines + $paddingLines) * $multiplier);

        $height = min($maxHeight, $baseHeight * $effectiveLines);
        $height += 4;

        $sheet->getRowDimension($row)->setRowHeight($height);
    }

    private function safeMerge(Worksheet $sheet, string $range): void
    {
        $range = strtoupper($range);

        $existing = $sheet->getMergeCells();
        if (isset($existing[$range])) {
            return;
        }

        [$r1, $r2] = explode(':', $range);
        [$c1, $row1] = Coordinate::coordinateFromString($r1);
        [$c2, $row2] = Coordinate::coordinateFromString($r2);

        $x1 = Coordinate::columnIndexFromString($c1);
        $x2 = Coordinate::columnIndexFromString($c2);
        $y1 = (int) $row1;
        $y2 = (int) $row2;

        foreach ($existing as $m) {
            $m = strtoupper($m);
            [$m1, $m2] = explode(':', $m);
            [$mc1, $my1] = Coordinate::coordinateFromString($m1);
            [$mc2, $my2] = Coordinate::coordinateFromString($m2);

            $mx1 = Coordinate::columnIndexFromString($mc1);
            $mx2 = Coordinate::columnIndexFromString($mc2);
            $my1 = (int) $my1;
            $my2 = (int) $my2;

            $overlap = !($x2 < $mx1 || $mx2 < $x1 || $y2 < $my1 || $my2 < $y1);
            if ($overlap) {
                try { $sheet->unmergeCells($m); } catch (Throwable) {}
            }
        }

        try { $sheet->mergeCells($range); } catch (Throwable) {}
    }

    private function resolveEmployeeCacheService(): ?object
    {
        $candidates = [
            'App\\Services\\EmployeeCacheService',
            'App\\Services\\EmployeeService',
            'App\\Services\\EmployeeApiCacheService',
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return app($class);
            }
        }

        return null;
    }

    private function employeeNameById(?object $svc, $id): string
    {
        if (! $svc || ! filled($id)) {
            return '-';
        }

        try {
            $id = (int) $id;

            $emp = null;
            foreach (['findById', 'getById', 'getEmployeeById', 'resolveById'] as $method) {
                if (method_exists($svc, $method)) {
                    $emp = $svc->{$method}($id);
                    break;
                }
            }

            if (! $emp) return '-';

            if (is_array($emp)) {
                return (string) ($emp['nama'] ?? $emp['name'] ?? $emp['n_emp'] ?? '-');
            }

            return (string) ($emp->nama ?? $emp->name ?? $emp->n_emp ?? '-');
        } catch (Throwable) {
            return '-';
        }
    }
}
