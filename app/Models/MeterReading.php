<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MeterReading extends Model {
    protected $fillable = ['meter_id', 'date', 'month', 'year', 'value', 'is_reset', 'consumption', 'is_estimated'];
    protected $casts = ['value' => 'float', 'consumption' => 'float', 'is_reset' => 'boolean', 'is_estimated' => 'boolean', 'month' => 'integer', 'year' => 'integer'];
}
