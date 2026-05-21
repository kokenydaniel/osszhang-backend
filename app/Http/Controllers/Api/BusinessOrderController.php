<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessOrder;
use App\Services\EncryptedRecordService;
use App\Services\ShopifyService;
use App\Support\BusinessSettings;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BusinessOrderController extends Controller
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function index(Request $request)
    {
        $household = $request->user()->household;

        return response()->json(BusinessOrder::where('household_id', $household->id)
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($o) => $this->mapOrder($o, $household)));
    }

    public function store(Request $request)
    {
        $biz = $request->user()->household?->resolvedBusinessSettings() ?? BusinessSettings::defaults();

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

        $household = $request->user()->household;
        $o = new BusinessOrder([
            'household_id' => $household->id,
            'date' => $v['date'],
            'paid_date' => $v['paidDate'] ?? null,
            'state' => ($v['paidDate'] ?? null) ? 'RENDBEN' : 'KINT',
        ]);
        $this->crypto->persistBusinessOrder($o, $household, [
            'customer_name' => $v['customerName'],
            'amount' => (float) $v['amount'],
            'channel' => $v['channel'] ?? ($biz['channels'][0] ?? 'Webshop'),
            'payment_method' => $v['paymentMethod'] ?? ($biz['payment_methods'][0] ?? 'Kártya'),
            'provider' => $v['provider'] ?? ($biz['providers'][0] ?? 'Nincs'),
            'destination' => $v['destination'] ?? ($biz['destinations'][0] ?? 'Szolgáltatónál parkol'),
            'invoice_id' => $v['invoiceId'] ?? null,
        ]);
        $o->save();

        return response()->json($this->mapOrder($o, $household), 201);
    }

    public function update(Request $request, BusinessOrder $businessOrder)
    {
        $household = $request->user()->household;
        $v = $request->validate([
            'customerName' => 'sometimes|required',
            'amount' => 'sometimes|required|numeric',
            'date' => 'sometimes|required|date',
        ]);

        $sensitive = $this->crypto->businessOrderResolved($businessOrder, $household);
        if (array_key_exists('customerName', $v)) {
            $sensitive['customer_name'] = $v['customerName'];
        }
        if (array_key_exists('amount', $v)) {
            $sensitive['amount'] = (float) $v['amount'];
        }
        if ($request->has('channel')) {
            $sensitive['channel'] = $request->channel;
        }
        if ($request->has('paymentMethod')) {
            $sensitive['payment_method'] = $request->paymentMethod;
        }
        if ($request->has('provider')) {
            $sensitive['provider'] = $request->provider;
        }
        if ($request->has('destination')) {
            $sensitive['destination'] = $request->destination;
        }
        if ($request->has('invoiceId')) {
            $sensitive['invoice_id'] = $request->invoiceId;
        }
        if (array_key_exists('date', $v)) {
            $businessOrder->date = $v['date'];
        }
        $businessOrder->paid_date = $request->paidDate ?? $businessOrder->paid_date;
        $businessOrder->state = $request->state ?? ($businessOrder->paid_date ? 'RENDBEN' : 'KINT');

        $this->crypto->persistBusinessOrder($businessOrder, $household, $sensitive);
        $businessOrder->save();

        return response()->json($this->mapOrder($businessOrder, $household));
    }

    private function mapOrder(BusinessOrder $o, $household)
    {
        return $this->crypto->formatBusinessOrder($o, $household);
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
            $biz = $household ? $household->resolvedBusinessSettings() : BusinessSettings::defaults();

            if (!$household || !$household->shopify_import_enabled) {
                return response()->json([
                    'success' => false,
                    'error' => 'A Shopify import nincs engedélyezve. Kapcsold be a Beállítások → Modulok menüpontban.',
                ], 400);
            }

            if (!$household->shopify_shop_url || !$household->shopify_access_token) {
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
                    $method = $biz['payment_methods'][0] ?? 'Kártya';
                    
                    $isCod = false;
                    foreach ($gateways as $g) {
                        $gl = strtolower($g);
                        if (str_contains($gl, 'cod') || str_contains($gl, 'manual') || str_contains($gl, 'Cash on Delivery') || str_contains($gl, 'utánvét')) {
                            $isCod = true;
                            break;
                        }
                    }
                    if ($isCod) {
                        $cod = collect($biz['payment_methods'])->first(
                            fn ($m) => stripos($m, 'utánvét') !== false || stripos($m, 'utanvet') !== false
                        );
                        $method = $cod ?? 'Utánvét';
                    }

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
                    $destination = $biz['destinations'][0] ?? 'Szolgáltatónál parkol';
                    $state = 'KINT_PARKOL';

                    $providerLabel = count($gateways) > 0
                        ? implode(', ', $gateways)
                        : ($biz['providers'][0] ?? 'Nincs');

                    if ($so['financial_status'] !== 'paid') {
                        $state = 'KINT';
                    }

                    $o = new BusinessOrder([
                        'household_id' => $householdId,
                        'date' => Carbon::parse($so['created_at'])->toDateString(),
                        'paid_date' => $so['financial_status'] === 'paid' ? Carbon::parse($so['processed_at'])->toDateString() : null,
                        'shopify_order_id' => $orderId,
                        'shopify_order_number' => $orderNumber,
                        'state' => $state,
                    ]);
                    $this->crypto->persistBusinessOrder($o, $household, [
                        'customer_name' => ($so['customer']['first_name'] ?? '').' '.($so['customer']['last_name'] ?? 'Vásárló'),
                        'amount' => (float) $so['total_price'],
                        'channel' => BusinessSettings::shopifyChannelLabel($biz),
                        'payment_method' => $method,
                        'provider' => $providerLabel,
                        'destination' => $destination,
                        'invoice_id' => $invoiceId,
                    ]);
                    $o->save();
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
