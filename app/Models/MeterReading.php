<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MeterReading extends Model {
    protected $fillable = ['meter_id', 'date', 'month', 'year', 'value', 'is_reset', 'consumption', 'is_estimated'];
}
