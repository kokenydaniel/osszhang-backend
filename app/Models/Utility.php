<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Utility extends Model {
    protected $fillable = ['household_id', 'type', 'total', 'due_date', 'paid_date', 'paid_by', 'split_rule'];
}
