<?php

namespace App\Services;

use App\Models\Espo\Account;
use App\Models\Espo\Contact;
use Illuminate\Support\Carbon;

class ContactService
{
    public function __construct(
        protected EspoEntityWriter $writer
    ) {}

    public function createForAccount(Account $account, array $data, ?string $createdById = null): Contact
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name'] ?? '');

        $contact = new Contact();
        $contact->id = $this->writer->generateId();
        $contact->deleted = 0;
        $contact->account_id = $account->id;
        $contact->first_name = $firstName;
        $contact->last_name = $lastName ?: null;
        $contact->salutation_name = $this->normalizeTitle($data['salutation_name'] ?? null);
        $contact->job_role = $this->normalizeJobRole($data['job_role'] ?? null);
        $contact->name = trim($firstName.' '.$lastName) ?: $firstName;
        $contact->assigned_user_id = $account->assigned_user_id ?: ($createdById ?? auth()->id());
        $contact->created_at = $now;
        $contact->modified_at = $now;
        $contact->created_by_id = $createdById ?? auth()->id();
        $contact->save();

        $this->writer->syncPrimaryEmail($contact->id, 'Contact', $data['email'] ?? null);
        $this->writer->syncPrimaryPhone($contact->id, 'Contact', $data['phone'] ?? null);

        return $contact;
    }

    public function update(Contact $contact, Account $account, array $data): Contact
    {
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name'] ?? '');

        $contact->account_id = $account->id;
        $contact->first_name = $firstName;
        $contact->last_name = $lastName ?: null;
        $contact->salutation_name = $this->normalizeTitle($data['salutation_name'] ?? null);
        $contact->job_role = $this->normalizeJobRole($data['job_role'] ?? null);
        $contact->name = trim($firstName.' '.$lastName) ?: $firstName;
        $contact->modified_at = Carbon::now()->format('Y-m-d H:i:s');
        $contact->save();

        $this->writer->syncPrimaryEmail($contact->id, 'Contact', $data['email'] ?? null);
        $this->writer->syncPrimaryPhone($contact->id, 'Contact', $data['phone'] ?? null);

        return $contact;
    }

    protected function normalizeTitle(mixed $value): ?string
    {
        $title = trim((string) ($value ?? ''));

        return $title !== '' ? $title : null;
    }

    protected function normalizeJobRole(mixed $value): ?string
    {
        $role = trim((string) ($value ?? ''));

        return $role !== '' ? $role : null;
    }
}
