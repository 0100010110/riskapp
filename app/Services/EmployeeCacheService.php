<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class EmployeeCacheService
{
    /** cache key hasil merge */
    public const CACHE_KEY = 'employee:merged';

    /** @var array<int,array>|null */
    private ?array $indexById = null;

    /** @return array{refreshed_at?:string,count?:int,data?:array} */
    public function payload(): array
    {
        $payload = Cache::get(self::CACHE_KEY);

        return is_array($payload) ? $payload : ['data' => []];
    }

    /** @return array<int,array> */
    public function data(): array
    {
        $payload = $this->payload();
        $data = $payload['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    /** @return array<int,array> */
    public function indexById(): array
    {
        if ($this->indexById !== null) {
            return $this->indexById;
        }

        $idx = [];
        foreach ($this->data() as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!array_key_exists('id', $row) || $row['id'] === null) {
                continue;
            }
            $idx[(int) $row['id']] = $row;
        }

        return $this->indexById = $idx;
    }

    public function findById(int $id): ?array
    {
        $idx = $this->indexById();
        return $idx[$id] ?? null;
    }

    public function labelForId(?int $id): string
    {
        if (!$id) {
            return '-';
        }

        $row = $this->findById($id);
        if (!$row) {
            return "id={$id}";
        }

        return $this->labelFromRow($row);
    }

    public function labelFromRow(array $row): string
    {
        $nik   = (string) ($row['nik'] ?? '-');
        $id    = (string) ($row['id'] ?? '-');
        $nama  = (string) ($row['nama'] ?? $row['name'] ?? '-');
        $email = (string) ($row['email'] ?? '-');

        
        return "{$nik} | {$id} | {$nama} | {$email}";
    }

    /**
     * @return array<int,string> [id => label]
     */
    public function searchOptions(string $search, int $limit = 50): array
    {
        $search = trim(mb_strtolower($search));
        if ($search === '') {
            return [];
        }

        $results = [];
        foreach ($this->data() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            if ($id === null) {
                continue; 
            }

            $haystack = mb_strtolower(
                implode(' ', [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['nik'] ?? ''),
                    (string) ($row['nama'] ?? $row['name'] ?? ''),
                    (string) ($row['email'] ?? ''),
                ])
            );

            if (str_contains($haystack, $search)) {
                $results[(int) $id] = $this->labelFromRow($row);
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }
}
