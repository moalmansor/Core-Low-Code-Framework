<?php

declare(strict_types=1);

namespace App\Support\Models;

use Illuminate\Support\Facades\Auth;

/** Fills created_by / updated_by from the authenticated user. */
trait TracksActor
{
    public static function bootTracksActor(): void
    {
        static::creating(static function (self $model): void {
            $id = Auth::id();
            if ($id !== null) {
                $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $id);
                $model->setAttribute('updated_by', $id);
            }
        });
        static::updating(static function (self $model): void {
            $id = Auth::id();
            if ($id !== null) {
                $model->setAttribute('updated_by', $id);
            }
        });
    }
}
