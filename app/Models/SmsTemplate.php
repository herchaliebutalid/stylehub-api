<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    /**
     * Only template content may be mass assigned.
     *
     * Template type and editor identity are server-controlled.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}