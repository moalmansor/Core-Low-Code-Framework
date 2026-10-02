<?php

declare(strict_types=1);

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for every metadata model: microsecond UTC datetimes stored in
 * DATETIME(6) / DATETIME2(6) (ADR-0006) and explicit fillable lists.
 */
abstract class BaseModel extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @var list<string> */
    protected $guarded = ['id'];
}
