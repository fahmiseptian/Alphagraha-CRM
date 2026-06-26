<?php

namespace App\Models\Espo;

use App\Models\Espo\Concerns\EspoEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumen EspoCRM (tabel `document`). Dipakai read-only untuk file lama
 * yang ditautkan ke Opportunity via `document_opportunity`.
 */
class Document extends Model
{
    use EspoEntity;

    protected $table = 'document';

    public function espoEntityType(): string
    {
        return 'Document';
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    /**
     * URL unduh file di EspoCRM lama (crm.alphagraha.co.id).
     */
    public function legacyDownloadUrl(): ?string
    {
        if (empty($this->file_id)) {
            return null;
        }

        $base = rtrim((string) config('crm.legacy_crm_url'), '/');

        return $base . '/?entryPoint=download&id=' . $this->file_id;
    }
}
