<?php

namespace App\Models;

use App\Services\EmployeeCacheService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tmrisk extends TrBaseModel
{
    protected $table = 'tmrisk';
    protected $primaryKey = 'i_id_risk';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_taxonomy' => 'integer',
        'f_risk_primary' => 'boolean',
        'c_risk_status' => 'integer',

        'v_threshold_safe' => 'integer',
        'v_threshold_caution' => 'integer',
        'v_threshold_danger' => 'integer',

        'i_entry' => 'integer',
        'd_entry' => 'datetime',
        'i_update' => 'integer',
        'd_update' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->setAttribute('c_risk_status', 0);

            if (blank($model->getAttribute('c_org_owner'))) {
                $model->setAttribute('c_org_owner', self::resolveOwnerOrgPrefix());
            }

            $val = trim((string) $model->getAttribute('c_org_owner'));
            if ($val !== '') {
                $model->setAttribute('c_org_owner', self::normalizeOrgPrefix($val));
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('c_org_owner')) {
                $model->setAttribute('c_org_owner', (string) $model->getOriginal('c_org_owner'));
            }
        });
    }

    private static function resolveOwnerOrgPrefix(): string
    {
        $user = Auth::user();
        $userId = (int) ($user?->getAuthIdentifier() ?? 0);

        if ($userId <= 0) {
            return '';
        }

        try {
            $svc = app(EmployeeCacheService::class);
            $row = $svc->findById($userId);

            if (! $row) {
                $nik = trim((string) ($user?->nik ?? ''));
                if ($nik !== '') {
                    foreach ($svc->data() as $r) {
                        if (! is_array($r)) continue;
                        if ((string) ($r['nik'] ?? '') === $nik) {
                            $row = $r;
                            break;
                        }
                    }
                }
            }

            $org = is_array($row)
                ? trim((string) ($row['organisasi'] ?? $row['organization'] ?? $row['org'] ?? ''))
                : '';

            if ($org === '') {
                return '';
            }

            return self::normalizeOrgPrefix($org);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function normalizeOrgPrefix(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^([A-Za-z]{2})/', $value, $m)) {
            return strtoupper($m[1]);
        }

        return strtoupper(substr($value, 0, 2));
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Tmtaxonomy::class, 'i_id_taxonomy', 'i_id_taxonomy');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Tmriskapprove::class, 'i_id_risk', 'i_id_risk');
    }

    public function latestApproval(): HasOne
    {
        return $this->hasOne(Tmriskapprove::class, 'i_id_risk', 'i_id_risk')
            ->latestOfMany('d_entry');
    }

    public static function statusOptions(): array
    {
        return [
            0  => 'draft',
            1  => 'approved officer tahap 1 (RSA + Primary Risk)',
            2  => 'approved kadiv tahap 1 (RSA)',
            3  => 'pengajuan admin tahap 1 (RSA)',
            4  => 'approved tahap 1 (RSA)',
            5  => 'pengajuan hapus',
            6  => 'approved officer tahap 2 (Risk Register + Profil Risiko)',
            7  => 'approved kadiv tahap 2 (Risk Register + Profil Risiko)',
            8  => 'pengajuan admin tahap 2 (Risk Register + Profil Risiko)',
            9  => 'approved tahap 2 (Risk Register + Profil Risiko)',
            10 => 'approved officer tahap 3 (Realisasi Risiko)',
            11 => 'approved kadiv tahap 3 (Realisasi Risiko)',
            12 => 'pengajuan admin tahap 3 (Realisasi Risiko)',
            13 => 'approved tahap 3 (Realisasi Risiko)',
            14 => 'approved officer LED',
            15 => 'approved kadiv LED',
            16 => 'pengajuan admin LED',
            17 => 'approved LED',
        ];
    }

    public function statusLabelWithActor(): string
    {
        $status = (int) ($this->c_risk_status ?? 0);
        $opts = self::statusOptions();
        $label = trim((string) ($opts[$status] ?? (string) $status));

        $this->loadMissing('latestApproval');

        if ($status === 0) {
            $creator = self::employeeNameByIdOrNik((int) ($this->i_entry ?? 0));
            return $creator !== '' ? "draft by {$creator}" : $label;
        }

        $name = trim((string) ($this->latestApproval?->n_emp ?? ''));

        if ($name === '') {
            $name = self::employeeNameByIdOrNik((int) ($this->i_entry ?? 0));
        }

        if ($name !== '' && preg_match('/^(approved|pengajuan)\b/i', $label, $m)) {
            $verb = strtolower($m[1]);
            return preg_replace('/^(approved|pengajuan)\b\s*/i', $verb . ' ' . $name . ' ', $label, 1) ?: $label;
        }

        return $label;
    }

    protected static function employeeNameByIdOrNik(int $id): string
    {
        static $cache = [];

        if ($id <= 0) {
            return '';
        }

        if (array_key_exists($id, $cache)) {
            return (string) $cache[$id];
        }

        $name = '';

        try {
            $svc = app(EmployeeCacheService::class);

            $row = $svc->findById($id);
            if (is_array($row)) {
                $name = trim((string) ($row['nama'] ?? $row['name'] ?? $row['n_name'] ?? ''));
            }

            if ($name === '') {
                $nik = (string) $id;

                if (method_exists($svc, 'findByNik')) {
                    $row2 = $svc->findByNik($nik);
                    if (is_array($row2)) {
                        $name = trim((string) ($row2['nama'] ?? $row2['name'] ?? $row2['n_name'] ?? ''));
                    }
                }

                if ($name === '') {
                    foreach ($svc->data() as $r) {
                        if (! is_array($r)) continue;
                        if ((string) ($r['nik'] ?? '') === $nik) {
                            $name = trim((string) ($r['nama'] ?? $r['name'] ?? $r['n_name'] ?? ''));
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            $name = '';
        }

        $cache[$id] = $name;

        return $name;
    }


    public function getIRiskAttribute($value): string
    {
        $v = trim((string) $value);
        return ($v === '' || strtolower($v) === 'null') ? '' : (string) $value;
    }


    public function setIRiskAttribute($value): void
    {
        $v = trim((string) ($value ?? ''));
        $this->attributes['i_risk'] = ($v === '' || strtolower($v) === 'null') ? '' : (string) $value;
    }
}