<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Old public URL to new public URL (ED-018). Filled by the legacy migration; no admin screen yet. */
final class SeoRedirect extends Model
{
    protected $table = 'seo_redirects';

    protected $fillable = ['from_path', 'to_path', 'status_code'];

    protected function casts(): array
    {
        return ['status_code' => 'integer'];
    }
}
