<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessOrder;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BusinessOrderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(BusinessOrder::where('household_id', $request->user()->household_id)
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($o) => $this->mapOrder($o)));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'customerName' => 'required',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'channel' => 'nullable|string',
            'paymentMethod' => 'nullable|string',
            'provider' => 'nullable|string',
            'destination' => 'nullable|string',
            'paidDate' => 'nullable|date',
            'invoiceId' => 'nullable|string',
        ]);

        $o = BusinessOrder::create([
            'household_id' => $request->user()->household_id,
            'customer_name' => $v['customerName'],
            'amount' => $v['amount'],
            'date' => $v['date'],
            'channel' => $v['channel'] ?? 'Webshop',
            'payment_method' => $v['paymentMethod'] ?? 'Kártya',
            'provider' => $v['provider'] ?? 'Shopify',
            'destination' => $v['destination'] ?? 'Szolgáltatónál',
            'paid_date' => $v['paidDate'] ?? null,
            'invoice_id' => $v['invoiceId'] ?? null,
            'state' => ($v['paidDate'] ?? null) ? 'RENDBEN' : 'KINT'
        ]);

        return response()->json($this->mapOrder($o), 201);
    }

    public function update(Request $request, BusinessOrder $businessOrder)
    {
        $v = $request->validate([
            'customerName' => 'sometimes|required',
            'amount' => 'sometimes|required|numeric',
            'date' => 'sometimes|required|date',
        ]);

        $businessOrder->update([
            'customer_name' => $v['customerName'] ?? $businessOrder->customer_name,
            'amount' => $v['amount'] ?? $businessOrder->amount,
            'date' => $v['date'] ?? $businessOrder->date,
            'channel' => $request->channel ?? $businessOrder->channel,
            'payment_method' => $request->paymentMethod ?? $businessOrder->payment_method,
            'provider' => $request->provider ?? $businessOrder->provider,
            'destination' => $request->destination ?? $businessOrder->destination,
            'paid_date' => $request->paidDate ?? $businessOrder->paid_date,
            'invoice_id' => $request->invoiceId ?? $businessOrder->invoice_id,
            'state' => $request->state ?? (($request->paidDate ?? $businessOrder->paid_date) ? 'RENDBEN' : 'KINT')
        ]);

        return response()->json($this->mapOrder($businessOrder));
    }

    private function mapOrder($o) {
        return [
            'id' => $o->id,
            'date' => $o->date,
            'customerName' => $o->customer_name,
            'channel' => $o->channel,
            'paymentMethod' => $o->payment_method,
            'provider' => $o->provider,
            'destination' => $o->destination,
            'amount' => (float)$o->amount,
            'paidDate' => $o->paid_date,
            'invoiceId' => $o->invoice_id,
            'shopifyOrderId' => (string)$o->shopify_order_id,
            'shopifyOrderNumber' => $o->shopify_order_number,
            'state' => $o->state
        ];
    }

    public function destroy(BusinessOrder $businessOrder)
    {
        $businessOrder->delete();
        return response()->json(null, 204);
    }

    /**
     * Import orders from Shopify.
     */
    public function shopifyImport(Request $request, ShopifyService $shopifyService)
    {
        try {
            $user = $request->user();
            $household = $user->household;

            if (!$household || !$household->shopify_shop_url || !$household->shopify_access_token) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nincsenek beállítva Shopify hozzáférési adatok ehhez a háztartáshoz!'
                ], 400);
            }

            // Bind the household specific Shopify credentials
            $shopifyService->setCredentials($household->shopify_shop_url, $household->shopify_access_token);

            // Import all available orders
            $shopifyOrders = $shopifyService->getOrders();
            
            $importedCount = 0;
            $householdId = $user->household_id;

            foreach ($shopifyOrders as $so) {
                // Shopify order identifier (e.g., #1001 or long ID)
                $orderId = (string)($so['id']);
                $orderNumber = $so['name'] ?? $orderId;
                
                // Check if already imported
                $exists = BusinessOrder::where('household_id', $householdId)
                    ->where(function($q) use ($orderId, $orderNumber) {
                        $q->where('shopify_order_id', $orderId)
                          ->orWhere('invoice_id', $orderNumber);
                    })
                    ->exists();

                if (!$exists) {
                    // Smart Payment Method detection
                    $gateways = $so['payment_gateway_names'] ?? [];
                    $method = 'Kártya';
                    
                    $isCod = false;
                    foreach ($gateways as $g) {
                        $gl = strtolower($g);
                        if (str_contains($gl, 'cod') || str_contains($gl, 'manual') || str_contains($gl, 'Cash on Delivery') || str_contains($gl, 'utánvét')) {
                            $isCod = true;
                            break;
                        }
                    }
                    if ($isCod) $method = 'Utánvét';

                    // Invoice ID detection (look for E-LL-... or similar in note_attributes or tags)
                    $invoiceId = null;
                    
                    // 1. Check note_attributes
                    $noteAttribs = $so['note_attributes'] ?? [];
                    foreach ($noteAttribs as $attr) {
                        if (str_contains($attr['value'] ?? '', 'E-LL-')) {
                            $invoiceId = $attr['value'];
                            break;
                        }
                    }
                    
                    // 2. Check tags
                    if (!$invoiceId) {
                        $tags = $so['tags'] ?? '';
                        $tagArr = explode(',', $tags);
                        foreach ($tagArr as $tag) {
                            $t = trim($tag);
                            if (str_starts_with($t, 'E-LL-')) {
                                $invoiceId = $t;
                                break;
                            }
                        }
                    }
                    
                    // 3. Check note itself
                    if (!$invoiceId && str_contains($so['note'] ?? '', 'E-LL-')) {
                        preg_match('/E-LL-[0-9-]+/', $so['note'], $matches);
                        $invoiceId = $matches[0] ?? null;
                    }

                    // Destination and State logic
                    $destination = 'Szolgáltatónál parkol';
                    $state = 'KINT_PARKOL'; // Default for webshop (parked at provider)

                    if ($method === 'Utánvét') {
                        $destination = 'Futárnál (GLS)';
                        $state = 'KINT_PARKOL'; // Parked at courier
                    }

                    if ($so['financial_status'] !== 'paid') {
                        $destination = 'Vevőnél';
                        $state = 'KINT'; // Outstanding at customer
                    }

                    BusinessOrder::create([
                        'household_id' => $householdId,
                        'customer_name' => ($so['customer']['first_name'] ?? '') . ' ' . ($so['customer']['last_name'] ?? 'Vásárló'),
                        'amount' => $so['total_price'],
                        'date' => Carbon::parse($so['created_at'])->toDateString(),
                        'channel' => 'Webshop',
                        'payment_method' => $method,
                        'provider' => count($gateways) > 0 ? implode(', ', $gateways) : 'Shopify',
                        'destination' => $destination,
                        'paid_date' => $so['financial_status'] === 'paid' ? Carbon::parse($so['processed_at'])->toDateString() : null,
                        'invoice_id' => $invoiceId,
                        'shopify_order_id' => $orderId,
                        'shopify_order_number' => $orderNumber,
                        'state' => $state
                    ]);
                    $importedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'imported_count' => $importedCount,
                'total_fetched' => count($shopifyOrders)
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
