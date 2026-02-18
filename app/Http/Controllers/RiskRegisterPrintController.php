<?php

namespace App\Http\Controllers;

use App\Models\Tmrisk;
use App\Services\RiskRegisterPrintService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiskRegisterPrintController extends Controller
{
    public function download(string $token, Request $request, RiskRegisterPrintService $service): Response
    {
        $user = Filament::auth()->user() ?? $request->user() ?? auth()->user();
        $userId = (int) ($user?->getAuthIdentifier() ?? 0);

        if ($userId <= 0) {
            Log::warning('RiskRegisterPrint unauthorized', [
                'token' => $token,
                'ip'    => $request->ip(),
            ]);
            abort(403, 'Unauthorized');
        }

        $cacheKey = "risk_print:{$userId}:{$token}";

        $exists = Cache::has($cacheKey);
        $rawIds = Cache::get($cacheKey);

        
        if ($request->boolean('debug')) {
            return response()->json([
                'user_id'    => $userId,
                'token'      => $token,
                'cache_key'  => $cacheKey,
                'cache_has'  => $exists,
                'raw_ids'    => $rawIds,
                'hint'       => $exists ? 'Cache ada, harusnya bisa download.' : 'Cache tidak ada. Pastikan ListRisks menyimpan Cache::put(risk_print:...) sebelum redirect.',
                'request'    => [
                    'ip'        => $request->ip(),
                    'referer'   => $request->headers->get('referer'),
                    'userAgent' => $request->userAgent(),
                ],
            ]);
        }

        if (! is_array($rawIds) || empty($rawIds)) {
            Log::warning('RiskRegisterPrint cache missing/empty', [
                'user_id'   => $userId,
                'token'     => $token,
                'cache_key' => $cacheKey,
                'cache_has' => $exists,
            ]);
            abort(404, 'Token expired / data not found');
        }

        $ids = array_values(array_unique(array_map('intval', $rawIds)));
        $ids = array_values(array_filter($ids, fn ($v) => $v > 0));

        if (empty($ids)) {
            Log::warning('RiskRegisterPrint ids empty after sanitize', [
                'user_id'   => $userId,
                'token'     => $token,
                'cache_key' => $cacheKey,
                'raw_ids'   => $rawIds,
            ]);
            abort(404, 'No records selected');
        }

        Cache::forget($cacheKey);

        $risks = Tmrisk::query()
            ->with(['taxonomy'])
            ->whereIn('i_id_risk', $ids)
            ->get();

        if ($risks->isEmpty()) {
            Log::warning('RiskRegisterPrint records not found', [
                'user_id' => $userId,
                'ids'     => $ids,
            ]);
            abort(404, 'Records not found');
        }

        Log::info('RiskRegisterPrint download start', [
            'user_id'  => $userId,
            'ids_count'=> count($ids),
        ]);

        /** @var StreamedResponse $resp */
        $resp = $service->streamDownload($risks);

        return $resp;
    }
}
