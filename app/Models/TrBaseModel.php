<?php

namespace App\Models;

use App\Models\Concerns\HasEntryAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

abstract class TrBaseModel extends Model
{
    use HasEntryAudit;
    use LogsActivity;

    public $timestamps = false;

    protected $guarded = [];

 
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->getTable()) // biar jelas log-nya datang dari tabel mana
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly([
                'i_entry', 'd_entry', 'i_update', 'd_update',
            ]);
    }

   
    public function getDescriptionForEvent(string $eventName): string
    {
        return sprintf('%s %s', class_basename(static::class), $eventName);
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $req = request();

            $activity->properties = $activity->properties->merge([
                'meta' => [
                    'ip'         => $req->ip(),
                    'url'        => $req->fullUrl(),
                    'user_agent' => $req->userAgent(),
                ],
            ]);
        } catch (\Throwable $e) {
        }
    }
}
