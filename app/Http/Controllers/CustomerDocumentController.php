<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToUser;
use App\Models\CustomerDocument;
use App\Models\Espo\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerDocumentController extends Controller
{
    use ScopesToUser;

    public function store(Request $request, string $accountId): RedirectResponse
    {
        $this->authorizeManage();
        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $file = $request->file('file');
        $original = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $safeBase = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'dokumen';
        $filename = $safeBase.'-'.Str::lower(Str::random(8)).($ext ? '.'.$ext : '');
        $dir = 'customer-documents/'.$account->id;
        $path = $file->storeAs($dir, $filename, 'public');

        CustomerDocument::query()->create([
            'account_id' => $account->id,
            'name' => trim($data['name']),
            'file_path' => $path,
            'original_name' => $original,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()
            ->route('customers.show', $account->id)
            ->with('success', 'Dokumen customer berhasil diunggah.');
    }

    public function download(string $accountId, CustomerDocument $document): StreamedResponse
    {
        if (! auth()->user()?->canViewCustomers()) {
            abort(403);
        }

        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);
        abort_unless((string) $document->account_id === (string) $account->id, 404);

        $path = (string) $document->file_path;
        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);

        $downloadName = $document->original_name ?: ($document->name.($this->extensionFromPath($path) ? '.'.$this->extensionFromPath($path) : ''));

        return Storage::disk('public')->download($path, $downloadName);
    }

    public function destroy(string $accountId, CustomerDocument $document): RedirectResponse
    {
        $this->authorizeManage();
        $account = $this->scopeAssigned(Account::query())->findOrFail($accountId);
        abort_unless((string) $document->account_id === (string) $account->id, 404);

        $document->deleteFile();
        $document->delete();

        return redirect()
            ->route('customers.show', $account->id)
            ->with('success', 'Dokumen customer berhasil dihapus.');
    }

    protected function authorizeManage(): void
    {
        if (! auth()->user()?->canManageCustomerDocuments()) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola dokumen customer.');
        }
    }

    protected function extensionFromPath(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
    }
}
