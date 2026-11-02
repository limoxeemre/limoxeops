<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TransferStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['transfer_id', 'from_status', 'to_status', 'causer_type', 'causer_id', 'note', 'lat', 'lng'];

    protected function casts(): array
    {
        return [
            'from_status' => TransferStatus::class,
            'to_status' => TransferStatus::class,
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }
}
