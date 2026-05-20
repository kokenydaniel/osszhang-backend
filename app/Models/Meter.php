<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Meter extends Model {
    protected $fillable = ['household_id', 'name', 'icon', 'unit', 'location', 'encrypted_payload'];
    public function readings() { return $this->hasMany(MeterReading::class); }
}
