<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LedgerEntry extends Model {
    protected $fillable = ['saving_id', 'transaction_id', 'date', 'amount', 'reason', 'encrypted_payload'];
    protected $casts = ['amount' => 'float'];
}
