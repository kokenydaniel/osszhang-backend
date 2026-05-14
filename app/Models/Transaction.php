<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model {
    protected $fillable = ['household_id', 'user_id', 'type', 'description', 'category', 'amount', 'due_date', 'paid_date', 'is_budget', 'is_reserve'];
    protected $casts = ['is_budget' => 'boolean', 'is_reserve' => 'boolean'];

    public function items() {
        return $this->hasMany(LedgerEntry::class);
    }
}
