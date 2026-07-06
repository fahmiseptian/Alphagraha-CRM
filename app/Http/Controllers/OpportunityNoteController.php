<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Opportunity;
use App\Models\OpportunityNote;
use Illuminate\Http\Request;

class OpportunityNoteController extends Controller
{
    use ScopesToUser;

    public function store(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $opportunity->notes()->create([
            'body' => trim($data['body']),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Note added successfully.');
    }

    public function destroy(Opportunity $opportunity, OpportunityNote $note)
    {
        $this->authorizeAccess($opportunity);

        if ($note->opportunity_id !== $opportunity->id) {
            abort(404);
        }

        if (! $this->isAdmin() && $note->created_by !== auth()->id()) {
            abort(403);
        }

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    protected function authorizeAccess(Opportunity $opportunity): void
    {
        if (! $this->isAdmin() && $opportunity->assigned_user_id !== auth()->id()) {
            abort(403, 'You do not have access to this opportunity.');
        }
    }
}
