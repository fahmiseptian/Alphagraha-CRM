<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ActivityMediaController extends Controller
{
    use ScopesToUser;

    public function store(Request $request, Activity $activity)
    {
        $this->authorizeOwnership($activity);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'collection' => ['required', Rule::in(array_keys(Activity::MEDIA_COLLECTIONS))],
        ]);

        $activity->addMediaFromRequest('file')
            ->usingFileName($request->file('file')->getClientOriginalName())
            ->toMediaCollection($request->collection);

        return back()->with('success', 'Media berhasil diunggah.');
    }

    public function destroy(Activity $activity, Media $media)
    {
        $this->authorizeOwnership($activity);

        if ($media->model_type !== Activity::class || (string) $media->model_id !== (string) $activity->id) {
            abort(404);
        }

        if (! in_array($media->collection_name, array_keys(Activity::MEDIA_COLLECTIONS), true)) {
            abort(404);
        }

        $media->delete();

        return back()->with('success', 'Media berhasil dihapus.');
    }

    protected function authorizeOwnership(Activity $activity): void
    {
        if (! $this->isAdmin() && $activity->assigned_to !== auth()->id() && $activity->created_by !== auth()->id()) {
            abort(403, 'You do not have access to this activity.');
        }
    }
}
