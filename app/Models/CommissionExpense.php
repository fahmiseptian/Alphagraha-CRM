<?php

namespace App\Models;

use App\Models\Espo\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CommissionExpense extends Model
{
    protected $table = 'crm_commission_expenses';

    protected $fillable = [
        'user_id',
        'year',
        'period_key',
        'category_id',
        'sales_order_id',
        'sales_order_number',
        'account_id',
        'expense_date',
        'name',
        'amount',
        'note',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'sales_order_id' => 'integer',
        'expense_date' => 'date',
        'amount' => 'float',
        'file_size' => 'integer',
    ];

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(OpportunitySalesOrder::class, 'sales_order_id');
    }

    public function isLinkedToSalesOrder(): bool
    {
        return $this->sales_order_id !== null;
    }

    public function hasFile(): bool
    {
        $path = (string) ($this->file_path ?? '');

        return $path !== '' && Storage::disk('public')->exists($path);
    }

    public function deleteFile(): void
    {
        $path = (string) ($this->file_path ?? '');
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
