<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Opportunity;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OpportunityDocumentController extends Controller
{
    use ScopesToUser;

    /**
     * Unggah dokumen baru (disimpan via Spatie Media Library).
     */
    public function store(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20 MB
        ]);

        $opportunity->addMediaFromRequest('file')
            ->usingFileName($request->file('file')->getClientOriginalName())
            ->toMediaCollection('documents');

        return back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Hapus dokumen baru (Spatie media). Dokumen legacy EspoCRM tidak bisa dihapus dari sini.
     */
    public function destroy(Opportunity $opportunity, Media $media)
    {
        $this->authorizeAccess($opportunity);

        if ($media->model_type !== Opportunity::class || (string) $media->model_id !== (string) $opportunity->id) {
            abort(404);
        }

        $media->delete();

        return back()->with('success', 'Document deleted.');
    }

    protected function authorizeAccess(Opportunity $opportunity): void
    {
        if (! $this->isAdmin() && $opportunity->assigned_user_id !== auth()->id()) {
            abort(403, 'You do not have access to this opportunity.');
        }
    }
}
