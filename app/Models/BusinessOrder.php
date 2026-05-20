<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessOrder extends Model {
    protected $fillable = ['household_id', 'date', 'customer_name', 'channel', 'payment_method', 'provider', 'destination', 'amount', 'paid_date', 'has_invoice', 'invoice_id', 'state', 'shopify_order_id', 'shopify_order_number', 'encrypted_payload'];
    protected $casts = ['amount' => 'float'];
}
