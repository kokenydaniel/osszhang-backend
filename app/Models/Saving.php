<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Saving extends Model {
    protected $fillable = ['household_id', 'institution', 'currency', 'owner', 'count_in_savings', 'encrypted_payload'];
    protected $casts = ['count_in_savings' => 'boolean'];
    public function ledger() { return $this->hasMany(LedgerEntry::class, 'saving_id'); }
}
