<?php

namespace App\Services;

use App\Models\Tmrisk;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RiskNumberGeneratorService
{
 
    public function generateFor(Tmrisk $risk): string
    {
        $div = strtoupper(trim((string) ($risk->c_org_owner ?? '')));
        $div = (mb_strlen($div) >= 2) ? mb_substr($div, 0, 2) : 'XX';

        $year = trim((string) ($risk->c_risk_year ?? ''));
        if (! preg_match('/^\d{4}$/', $year)) {
            $year = now()->format('Y');
        }

        $prefix = $div . $year;

        return DB::transaction(function () use ($prefix) {
            DB::selectOne(
                "SELECT pg_advisory_xact_lock(('x' || substr(md5(?), 1, 16))::bit(64)::bigint)",
                [$prefix]
            );

            $pattern = '^' . preg_quote($prefix, '/') . '[0-9]{2}$';

            $codes = Tmrisk::query()
                ->where('i_risk', 'like', $prefix . '%')
                ->whereRaw("i_risk ~ ?", [$pattern])
                ->whereNotIn('i_risk', ['null', '', 'NULL'])
                ->pluck('i_risk');

            $max = -1;
            foreach ($codes as $code) {
                $code = (string) $code;
                $suffix = substr($code, -2);
                if (ctype_digit($suffix)) {
                    $max = max($max, (int) $suffix);
                }
            }

            $next = $max + 1;
            if ($next > 99) {
                throw new RuntimeException("Nomor urut untuk {$prefix} sudah mencapai batas (00-99).");
            }

            return $prefix . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
        });
    }
}
