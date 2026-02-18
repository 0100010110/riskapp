<?php

namespace App\Http\Controllers\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class RiskRegisterDownloadController
{
    public function __invoke(Request $request, string $token)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $cacheKey = 'risk_register_export:' . (int) $user->id . ':' . $token;

       
        $path = Cache::pull($cacheKey);

        abort_unless(is_string($path) && $path !== '' && File::exists($path), 404);

        return response()
            ->download($path, basename($path))
            ->deleteFileAfterSend(true);
    }
}
