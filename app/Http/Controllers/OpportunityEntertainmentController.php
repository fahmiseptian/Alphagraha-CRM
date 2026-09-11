<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\Espo\Opportunity;
use App\Models\OpportunityEntertainment;
use App\Models\OpportunityLog;
use App\Services\OpportunityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class OpportunityEntertainmentController extends Controller
{
    use ScopesToUser;

    public function store(Request $request, Opportunity $opportunity)
    {
        $this->authorizeAccess($opportunity);

        $data = $request->validate([
            'entertainment_name' => ['required', 'string', 'max:255'],
            'entertainment_description' => ['nullable', 'string', 'max:2000'],
            'entertainment_amount' => ['required', 'numeric', 'min:0'],
            'entertainment_photo' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'])->max(10 * 1024),
            ],
        ], [], [
            'entertainment_name' => 'nama',
            'entertainment_description' => 'deskripsi',
            'entertainment_amount' => 'nominal',
            'entertainment_photo' => 'foto',
        ]);

        $item = $opportunity->entertainments()->create([
            'name' => trim($data['entertainment_name']),
            'description' => filled($data['entertainment_description'] ?? null)
                ? trim((string) $data['entertainment_description'])
                : null,
            'amount' => round((float) $data['entertainment_amount'], 2),
            'status' => OpportunityEntertainment::STATUS_PENDING,
            'created_by' => auth()->id(),
        ]);

        $photo = $request->file('entertainment_photo');
        if ($photo instanceof UploadedFile && $photo->isValid()) {
            $item->photo_path = $photo->store('opportunity-entertainments/'.$opportunity->id, 'public');
            $item->save();
        }

        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_ENTERTAINMENT_ADDED,
            null,
            [
                'force' => true,
                'summary' => 'Menambah entertainment: '.$item->name,
            ]
        );

        return back()->with('success', 'Entertainment ditambahkan.');
    }

    public function complete(Opportunity $opportunity, OpportunityEntertainment $entertainment)
    {
        $this->authorizeAccess($opportunity);
        $this->ensureBelongs($opportunity, $entertainment);

        if (! auth()->user()?->canCompleteEntertainment()) {
            abort(403, 'Hanya Finance dan Superadmin yang dapat menandai complete.');
        }

        if ($entertainment->isComplete()) {
            return back()->with('error', 'Entertainment ini sudah complete.');
        }

        $entertainment->status = OpportunityEntertainment::STATUS_COMPLETE;
        $entertainment->completed_by = auth()->id();
        $entertainment->completed_at = now();
        $entertainment->save();

        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_ENTERTAINMENT_COMPLETED,
            null,
            [
                'force' => true,
                'summary' => 'Entertainment complete: '.$entertainment->name,
            ]
        );

        return back()->with('success', 'Entertainment ditandai complete.');
    }

    public function report(Opportunity $opportunity)
    {
        if (! auth()->user()?->canViewEntertainmentReport()) {
            abort(403, 'Laporan Entertainment hanya untuk Superadmin.');
        }

        $this->authorizeAccess($opportunity);

        $items = $opportunity->entertainments()
            ->with(['creator', 'completedByUser'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $currency = $opportunity->amount_currency ?: 'IDR';
        $total = round((float) $items->sum('amount'), 2);
        $totalPending = round((float) $items->where('status', OpportunityEntertainment::STATUS_PENDING)->sum('amount'), 2);
        $totalComplete = round((float) $items->where('status', OpportunityEntertainment::STATUS_COMPLETE)->sum('amount'), 2);

        $opportunity->loadMissing(['account', 'assignedUser']);

        return view('opportunities.entertainments.report', compact(
            'opportunity',
            'items',
            'currency',
            'total',
            'totalPending',
            'totalComplete'
        ));
    }

    public function destroy(Opportunity $opportunity, OpportunityEntertainment $entertainment)
    {
        $this->authorizeAccess($opportunity);
        $this->ensureBelongs($opportunity, $entertainment);

        $user = auth()->user();
        $canDelete = $user?->isAdmin()
            || $user?->isFinance()
            || $entertainment->created_by === $user?->id;

        if (! $canDelete) {
            abort(403);
        }

        $name = $entertainment->name;
        $entertainment->deletePhoto();
        $entertainment->delete();

        app(OpportunityLogService::class)->record(
            $opportunity,
            OpportunityLog::ACTION_ENTERTAINMENT_DELETED,
            null,
            [
                'force' => true,
                'summary' => 'Menghapus entertainment: '.$name,
            ]
        );

        return back()->with('success', 'Entertainment dihapus.');
    }

    protected function ensureBelongs(Opportunity $opportunity, OpportunityEntertainment $entertainment): void
    {
        if ((string) $entertainment->opportunity_id !== (string) $opportunity->id) {
            abort(404);
        }
    }

    protected function authorizeAccess(Opportunity $opportunity): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if ($user->isSuperAdmin() || $user->role === \App\Models\User::ROLE_ADMIN) {
            return;
        }

        if ($user->isPurchasing() || $user->isFinance()) {
            if ($opportunity->stage !== Opportunity::WON_STAGE) {
                abort(403, 'Akses hanya untuk deal Closed Won.');
            }

            return;
        }

        if ($user->isSales() && $opportunity->assigned_user_id === $user->id) {
            return;
        }

        abort(403, 'You do not have access to this opportunity.');
    }
}
