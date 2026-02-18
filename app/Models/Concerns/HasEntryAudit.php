<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasEntryAudit
{
    public static function bootHasEntryAudit(): void
    {
        static::creating(function (Model $model) {
            $userId = (int) (Auth::user()?->getAuthIdentifier() ?? 0);

            if (blank($model->getAttribute('i_entry'))) {
                $model->setAttribute('i_entry', $userId);
            }

            if (blank($model->getAttribute('d_entry'))) {
                $model->setAttribute('d_entry', now());
            }

            if (! is_null($model->getAttribute('i_update'))) {
                $model->setAttribute('i_update', null);
            }
            if (! is_null($model->getAttribute('d_update'))) {
                $model->setAttribute('d_update', null);
            }
        });

        static::updating(function (Model $model) {
            $userId = (int) (Auth::user()?->getAuthIdentifier() ?? 0);

            $model->setAttribute('i_update', $userId);
            $model->setAttribute('d_update', now());
        });
    }
}
