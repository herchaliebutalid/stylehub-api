<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneVerificationCode extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Hidden Security Fields
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'code_hash',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'attempts' =>
                'integer',

            'expires_at' =>
                'datetime',

            'last_sent_at' =>
                'datetime',

            'last_attempt_at' =>
                'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }
}