<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    protected $table = 'crm_user_profiles';

    protected $fillable = [
        'user_id', 'signature_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function signatureUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return Storage::disk('public')->url($this->signature_path);
    }

    public function signatureAbsolutePath(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $path = Storage::disk('public')->path($this->signature_path);

        return is_file($path) ? $path : null;
    }
}
