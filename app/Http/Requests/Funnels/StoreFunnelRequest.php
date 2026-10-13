<?php

declare(strict_types=1);

namespace App\Http\Requests\Funnels;

use App\Models\Funnel;

class StoreFunnelRequest extends FunnelRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Funnel::class) ?? false;
    }
}
