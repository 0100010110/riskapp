<?php

namespace App\Models;

use App\Services\EmployeeCacheService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tmlostevent extends TrBaseModel
{
    protected $table = 'tmlostevent';

    protected $primaryKey = 'i_id_lostevent';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    // =========================
    // STATUS CONSTANTS (LED)
    // =========================
    public const STATUS_DRAFT            = 0;
    public const STATUS_DELETE_REQUEST   = 5;
    public const STATUS_APPROVED_OFFICER = 14;
    public const STATUS_APPROVED_KADIV   = 15;
    public const STATUS_SUBMITTED_ADMIN  = 16;
    public const STATUS_APPROVED         = 17;

    protected $fillable = [
        'i_id_taxonomy',
        'e_lost_event',
        'v_lost_event',
        'c_lostevent_status',
        'd_lost_event',

        'i_entry', 'd_entry', 'i_update', 'd_update',
    ];

    protected $casts = [
        'i_id_lostevent'     => 'int',
        'i_id_taxonomy'      => 'int',
        'v_lost_event'       => 'int',
        'c_lostevent_status' => 'int',

        'i_entry'            => 'int',
        'i_update'           => 'int',

        'd_entry'            => 'datetime',
        'd_update'           => 'datetime',

        'd_lost_event'       => 'date',
    ];

    protected $attributes = [
        'c_lostevent_status' => self::STATUS_DRAFT,
    ];

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Tmtaxonomy::class, 'i_id_taxonomy', 'i_id_taxonomy');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT            => 'draft',
            self::STATUS_DELETE_REQUEST   => 'Pengajuan Penghapusan',
            self::STATUS_APPROVED_OFFICER => 'Approved Officer LED',
            self::STATUS_APPROVED_KADIV   => 'Approved Kadiv LED',
            self::STATUS_SUBMITTED_ADMIN  => 'Pengajuan Admin LED',
            self::STATUS_APPROVED         => 'Approved LED',
        ];
    }

    /** @var array<int,string> */
    protected static array $userNameCache = [];

    public function statusLabelWithActor(): string
    {
        $status = (int) ($this->c_lostevent_status ?? 0);

        // actor: default pakai i_update, fallback i_entry
        $actorId = (int) ($this->i_update ?? 0);
        if ($actorId <= 0) {
            $actorId = (int) ($this->i_entry ?? 0);
        }

        $actorName = self::resolveUserName($actorId);
        if ($actorName === '') {
            $actorName = $actorId > 0 ? "User #{$actorId}" : 'Unknown';
        }

        return match ($status) {
            0  => 'draft',
            5  => "Pengajuan Penghapusan Entry by {$actorName}",
            14 => "approved by officer {$actorName} LED",
            15 => "approved by kadiv {$actorName} LED",
            16 => "pengajuan by admin {$actorName} LED",
            17 => "approved completely by {$actorName} LED",
            default => (string) $status,
        };
    }

    protected static function resolveUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        if (array_key_exists($userId, static::$userNameCache)) {
            return static::$userNameCache[$userId];
        }

        $name = '';

        try {
            $svc = app(EmployeeCacheService::class);

            $row = null;
            try {
                $row = $svc->findById($userId);
            } catch (\Throwable) {
                $row = null;
            }

            if (! is_array($row)) {
                $nik = (string) $userId;
                $data = $svc->data();

                if (is_iterable($data)) {
                    foreach ($data as $r) {
                        if (! is_array($r)) continue;
                        if (trim((string) ($r['nik'] ?? '')) === $nik) {
                            $row = $r;
                            break;
                        }
                    }
                }
            }

            $name = is_array($row)
                ? trim((string) ($row['nama'] ?? $row['name'] ?? $row['n_name'] ?? ''))
                : '';
        } catch (\Throwable) {
            $name = '';
        }

        static::$userNameCache[$userId] = $name;

        return $name;
    }
}
