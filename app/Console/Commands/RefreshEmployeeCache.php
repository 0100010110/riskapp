<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use Illuminate\Support\Facades\DB;


class RefreshEmployeeCache extends Command
{
    protected $signature = 'employee:refresh-cache
    {--debug : Print verbose debug output}
    {--force : Break the lock and run anyway}
    {--unlock : Remove the lock and exit}
    {--lock-ttl=600 : Lock TTL in seconds (default 600)}';

    protected $description = 'Fetch API B users, match with API A employees, merge, store in cache (safe replace)';

    public function handle(): int
{
    $lockName = 'employee:refresh-cache-lock';
    $ttl = (int) ($this->option('lock-ttl') ?? 600);
    if ($ttl <= 0) {
        $ttl = 600;
    }

    // Utility: clear lock without doing refresh.
    if ((bool) $this->option('unlock')) {
        $cleared = $this->forceReleaseLock($lockName);
        $this->info($cleared ? 'Lock cleared.' : 'No lock to clear (or lock store does not support force release).');
        return self::SUCCESS;
    }

    $lock = Cache::lock($lockName, $ttl);

    if (! $lock->get()) {
        // 1) Try to self-heal: clear expired or suspicious lock (database store).
        $repaired = $this->maybeRepairStuckLock($lockName, $ttl);

        // 2) Force option: break lock regardless.
        if (! $repaired && (bool) $this->option('force')) {
            $this->warn('Force enabled: breaking existing lock…');
            $this->forceReleaseLock($lockName);
        }

        // 3) Retry once.
        $lock = Cache::lock($lockName, $ttl);
        if (! $lock->get()) {
            $this->warn('Another refresh is running.');

            if ($this->option('debug')) {
                $info = $this->getLockInfo($lockName);

                if ($info) {
                    $this->line('Lock key: ' . ($info['key'] ?? '-'));
                    $this->line('Lock owner: ' . ($info['owner'] ?? '-'));
                    $this->line('Lock expiration (epoch): ' . ($info['expiration'] ?? '-'));
                    if (! empty($info['expiration'])) {
                        $this->line('Lock expires at: ' . date('c', (int) $info['expiration']));
                    }
                    $this->line('Now: ' . now()->toIso8601String() . ' (epoch ' . time() . ')');
                } else {
                    $this->line('Lock details unavailable for cache store: ' . config('cache.default'));
                }

                $this->line('Tips:');
                $this->line('- Clear stale lock: php artisan employee:refresh-cache --unlock');
                $this->line('- Break lock & run:  php artisan employee:refresh-cache --force --debug');
            }

            // Keep SUCCESS so the scheduler doesn't mark it as failed due to overlaps.
            return self::SUCCESS;
        }
    }

    try {
        return $this->refreshSafely();
    } finally {
        // If release fails (e.g., owner mismatch due to store issues), hard-clear to avoid a stuck lock.
        try {
            $lock->release();
        } catch (Throwable $e) {
            if ($this->option('debug')) {
                $this->line('Failed to release lock, attempting hard cleanup: ' . $e->getMessage());
            }
            $this->forceReleaseLock($lockName);
        }
    }
}

/**
 * If cache driver is database, attempt to clear:
 * - expired lock (expiration <= now), or
 * - suspicious lock (expiration too far in the future), which can happen on time skew.
 */
private function maybeRepairStuckLock(string $lockName, int $ttl): bool
{
    $info = $this->getLockInfo($lockName);
    if (! $info) {
        return false;
    }

    $now = time();
    $exp = (int) ($info['expiration'] ?? 0);

    // Expired lock should be safe to clear.
    if ($exp > 0 && $exp <= $now) {
        if ($this->option('debug')) {
            $this->line('Stale lock detected (expired). Clearing…');
        }
        return $this->forceReleaseLock($lockName);
    }

    // Suspicious lock (far future) often indicates time skew when the lock was created.
    // We only auto-clear if it's > now + (2 * ttl + 60s).
    $suspiciousThreshold = $now + (max(60, $ttl) * 2) + 60;
    if ($exp > 0 && $exp > $suspiciousThreshold) {
        if ($this->option('debug')) {
            $this->line('Suspicious lock detected (expiration too far in the future). Clearing…');
        }
        return $this->forceReleaseLock($lockName);
    }

    return false;
}

/**
 * Force-release lock across cache drivers.
 */
private function forceReleaseLock(string $lockName): bool
{
    try {
        Cache::lock($lockName)->forceRelease();
        return true;
    } catch (Throwable) {
        // Fallback for database store if forceRelease isn't supported for some reason.
    }

    $info = $this->getLockInfo($lockName);
    if (! $info) {
        return false;
    }

    try {
        $lockTable = $this->databaseLockTable();
        $conn = $this->databaseLockConnection();

        DB::connection($conn)
            ->table($lockTable)
            ->whereIn('key', array_filter([
                $info['key'] ?? null,
                $this->rawLockKey($lockName),
                $this->prefixedLockKey($lockName),
            ]))
            ->delete();

        return true;
    } catch (Throwable) {
        return false;
    }
}

    /**
     * Get lock row for database cache store.
     * Returns null for non-database stores.
     */
    private function getLockInfo(string $lockName): ?array
    {
        if (config('cache.default') !== 'database') {
            return null;
        }

        $lockTable = $this->databaseLockTable();
        $conn = $this->databaseLockConnection();

        try {
            // Try both possible keys (prefixed and raw) to be safe.
            $raw = $this->rawLockKey($lockName);
            $pref = $this->prefixedLockKey($lockName);

            $row = DB::connection($conn)
                ->table($lockTable)
                ->whereIn('key', [$pref, $raw])
                ->first();

            if (! $row) {
                return null;
            }

            return [
                'key' => $row->key ?? null,
                'owner' => $row->owner ?? null,
                'expiration' => $row->expiration ?? null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function rawLockKey(string $lockName): string
    {
        return $lockName;
    }

    private function prefixedLockKey(string $lockName): string
    {
        return (string) config('cache.prefix', '') . $lockName;
    }

    private function databaseLockTable(): string
    {
        // Laravel will default to "cache_locks" if not configured.
        $table = config('cache.stores.database.lock_table');
        return $table ?: 'cache_locks';
    }

    private function databaseLockConnection(): ?string
    {
        // Prefer lock_connection if set, else use cache connection, else default.
        return config('cache.stores.database.lock_connection')
            ?: config('cache.stores.database.connection');
    }


    private function refreshSafely(): int
    {
        try {
            $apiA = config('services.employee.api_a_url');
            $apiB = config('services.employee.api_b_url');

            if (!$apiA || !$apiB) {
                $this->error('EMP_API_A_URL / EMP_API_B_URL belum diset (cek .env dan config/services.php).');
                return self::FAILURE;
            }

            // ===== 1) Fetch API B dulu =====
            $respB = $this->httpClient()
                ->timeout(30)
                ->retry(3, 500)
                ->get($apiB);

            if (! $respB->successful()) {
                $this->error("API B failed: {$respB->status()}");
                if ($this->option('debug')) {
                    $this->line('API B body (first 300 chars): ' . substr(trim($respB->body()), 0, 300));
                }
                return self::FAILURE;
            }

            $usersB = $this->decodeList($respB);

            // ===== 2) Fetch API A =====
            $respA = Http::acceptJson()
                ->timeout(60)
                ->retry(3, 500)
                ->get($apiA);

            if (! $respA->successful()) {
                $this->error("API A failed: {$respA->status()}");
                if ($this->option('debug')) {
                    $this->line('API A body (first 300 chars): ' . substr(trim($respA->body()), 0, 300));
                }
                return self::FAILURE;
            }

            $employeesA = $this->decodeList($respA);

            $this->info('Fetched: apiA=' . count($employeesA) . ' rows, apiB=' . count($usersB) . ' rows');

            if ($this->option('debug')) {
                $this->line('API A content-type: ' . ($respA->header('content-type') ?? '-'));
                $this->line('API B content-type: ' . ($respB->header('content-type') ?? '-'));
            }

            // Validasi minimal parsing
            if (count($employeesA) === 0) {
                $this->error('API A parsed rows = 0. Cache lama TIDAK diganti.');
                if ($this->option('debug')) {
                    $this->line('API A body (first 200 chars): ' . substr(trim($respA->body()), 0, 200));
                }
                return self::FAILURE;
            }

            // ===== 3) Build lookup API B =====
            $bByEmail = [];
            $bByNik   = [];

            foreach ($usersB as $b) {
                $id    = data_get($b, 'id');
                $email = strtolower(trim((string) data_get($b, 'email', '')));
                $nik   = trim((string) data_get($b, 'nik', ''));

                if ($id === null) continue;

                if ($email !== '') $bByEmail[$email] = $b;
                if ($nik !== '')   $bByNik[$nik]     = $b;
            }

            // ===== 4) Merge: matched -> onlyA -> onlyB =====
            $usedBIds = [];

            $matched = [];
            $onlyA   = [];

            foreach ($employeesA as $a) {
                $email = strtolower(trim((string) data_get($a, 'email', '')));
                $nik   = trim((string) data_get($a, 'nik', ''));

                $b = null;

                if ($email !== '' && isset($bByEmail[$email])) {
                    $b = $bByEmail[$email];
                } elseif ($nik !== '' && isset($bByNik[$nik])) {
                    $b = $bByNik[$nik];
                }

                if ($b) {
                    $id = data_get($b, 'id');
                    if ($id !== null) $usedBIds[$id] = true;

                    $matched[] = $this->formatFromA($a, $id); // nik lalu id
                } else {
                    $onlyA[] = $this->formatFromA($a, null); // nik lalu id (null)
                }
            }

            $onlyB = [];
            foreach ($usersB as $b) {
                $id = data_get($b, 'id');
                if ($id !== null && isset($usedBIds[$id])) continue;

                $onlyB[] = $this->formatFromB($b); // nik lalu id
            }

            $merged = array_merge($matched, $onlyA, $onlyB);

            // Validasi minimal sebelum replace cache
            if (count($merged) === 0) {
                $this->error('Merged rows = 0. Cache lama TIDAK diganti.');
                return self::FAILURE;
            }

            $payload = [
                'refreshed_at' => now()->toIso8601String(),
                'count'        => count($merged),
                'count_matched'=> count($matched),
                'count_only_a' => count($onlyA),
                'count_only_b' => count($onlyB),
                'data'         => $merged,
            ];

            // ===== 5) Safe replace: staging lalu promote =====
            $ttl = now()->addDay();

            Cache::put('employee:merged:staging', $payload, $ttl);
            Cache::put('employee:merged', Cache::get('employee:merged:staging'), $ttl);
            Cache::forget('employee:merged:staging');

            $this->info(
                "Cached employee:merged (safe replace), count=" . count($merged)
                . " matched=" . count($matched)
                . " onlyA=" . count($onlyA)
                . " onlyB=" . count($onlyB)
            );

            return self::SUCCESS;

        } catch (Throwable $e) {
            // Penting: jangan timpa cache lama
            $this->error('Exception while refreshing. Cache lama TIDAK diganti.');
            if ($this->option('debug')) {
                $this->line($e->getMessage());
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    /**
     * Format output berbasis API A, urutan: nik, id, ...
     */
    private function formatFromA(array $a, $id): array
    {
        return [
            'nik' => data_get($a, 'nik'),
            'id'  => $id,
            'nama' => data_get($a, 'nama'),
            'organisasi' => data_get($a, 'organisasi'),
            'nama_organisasi' => data_get($a, 'nama_organisasi'),
            'email' => data_get($a, 'email'),
            'telepon' => data_get($a, 'telepon'),
            'jabatan' => data_get($a, 'jabatan'),
            'nama_jabatan' => data_get($a, 'nama_jabatan'),
            'nama_gelar' => data_get($a, 'nama_gelar'),
            'tanggal_lahir' => data_get($a, 'tanggal_lahir'),
        ];
    }

    /**
     * Only-B: bentuk mengikuti schema yang sama.
     */
    private function formatFromB(array $b): array
    {
        $nik = trim((string) data_get($b, 'nik', ''));
        $nik = $nik === '' ? null : $nik;

        return [
            'nik' => $nik,
            'id'  => data_get($b, 'id'),
            'nama' => data_get($b, 'name'),
            'organisasi' => null,
            'nama_organisasi' => null,
            'email' => data_get($b, 'email'),
            'telepon' => null,
            'jabatan' => null,
            'nama_jabatan' => null,
            'nama_gelar' => null,
            'tanggal_lahir' => null,
        ];
    }

    /**
     * Parse response menjadi list of objects:
     * - [ {...}, {...} ]
     * - { data: [ ... ] } / { items: [ ... ] }
     * - "{...},{...},{...}" (comma-separated json objects, tanpa [ ])
     */
    private function decodeList(Response $resp): array
    {
        $decoded = $resp->json();
        $list = $this->normalizeToList($decoded);
        if ($list !== null) return $list;

        $body = trim($resp->body());
        if ($body === '') return [];

        if (str_starts_with($body, '{') && str_contains($body, '},{') && !str_starts_with($body, '[')) {
            $wrapped = '[' . $body . ']';
            $decoded2 = json_decode($wrapped, true);
            $list2 = $this->normalizeToList($decoded2);
            if ($list2 !== null) return $list2;
        }

        $decoded3 = json_decode($body, true);
        $list3 = $this->normalizeToList($decoded3);
        if ($list3 !== null) return $list3;

        return [];
    }

    private function normalizeToList($decoded): ?array
    {
        if (!is_array($decoded)) return null;

        if (array_is_list($decoded)) return $decoded;

        foreach (['data', 'result', 'results', 'items'] as $k) {
            $cand = data_get($decoded, $k);
            if (is_array($cand) && array_is_list($cand)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * HTTP client untuk API B yang bisa auth basic/bearer/none.
     * API_AUTH: basic | bearer | none
     * API_SECRET:
     * - bearer: "token" atau "Bearer token"
     * - basic: "username|password"
     */
    private function httpClient()
    {
        $auth = config('services.employee.api_auth', 'none');
        $secret = config('services.employee.api_secret');

        $http = Http::acceptJson();

        if ($auth === 'bearer' && $secret) {
            $token = str_starts_with($secret, 'Bearer ') ? substr($secret, 7) : $secret;
            return $http->withToken($token);
        }

        if ($auth === 'basic' && $secret) {
            [$u, $p] = array_pad(explode('|', $secret, 2), 2, '');
            return $http->withBasicAuth($u, $p);
        }

        return $http;
    }
}
