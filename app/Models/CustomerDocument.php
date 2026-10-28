<?php

namespace App\Models;

use App\Models\Espo\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomerDocument extends Model
{
    protected $table = 'crm_customer_documents';

    protected $fillable = [
        'account_id',
        'name',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): ?string
    {
        $path = (string) $this->file_path;
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function humanSize(): string
    {
        $bytes = (int) ($this->file_size ?? 0);
        if ($bytes <= 0) {
            return '—';
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    public function deleteFile(): void
    {
        $path = (string) $this->file_path;
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
};
