<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LedgerEntry extends Model {
    protected $fillable = ['saving_id', 'date', 'amount', 'reason'];
}
