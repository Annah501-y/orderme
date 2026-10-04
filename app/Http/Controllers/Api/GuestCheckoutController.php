<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Services\AddressGeocodingService;
use App\Services\DeliveryFeeService;
use App\Services\RoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class GuestCheckoutController extends Controller
{
    /** Validate the product selection and delivery details shared by quote and checkout. */
    private function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'address_line' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'vehicle_type' => ['required', 'in:bodaboda,bajaji'],
        ];
    }

    public function calculate(
        Request $request,
        AddressGeocodingService $geocoding,
        RoutingService $routing,
        DeliveryFeeService $fees
    ): JsonResponse {
        // Checkout repeats these checks, so the displayed quote is never trusted as an order total.
        $payload = $request->validate($this->rules());
        $quote = $this->makeQuote($payload, $geocoding, $routing, $fees);
        if ($quote instanceof JsonResponse) {
            return $quote;
        }

        return response()->json(['success' => true, 'data' => [
            'subtotal' => round($quote['subtotal'], 2),
            'delivery_fee' => round($quote['delivery_fee'], 2),
            'total_amount' => round($quote['subtotal'] + $quote['delivery_fee'], 2),
        ]]);
    }

    public function store(
        Request $request,
        AddressGeocodingService $geocoding,
        RoutingService $routing,
        DeliveryFeeService $fees
    ): JsonResponse {
        // Contact details are required at order placement, after the visitor has reviewed delivery.
        $payload = $request->validate(array_merge($this->rules(), [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\\+?255|0)[0-9]{9}$/'],
        ]));
        $quote = $this->makeQuote($payload, $geocoding, $routing, $fees);
        if ($quote instanceof JsonResponse) {
            return $quote;
        }

        // Store only a hash; return the plain order-scoped token once for the payment step.
        $plainGuestToken = Str::random(64);
        $order = DB::transaction(function () use ($payload, $quote, $plainGuestToken) {
            // Persist the guest address with the order so failed checkout leaves no orphan record.
            $address = Address::create([
                'user_id' => null,
                'recipient_name' => $payload['name'],
                'phone' => $payload['phone'],
                'address_line' => $payload['address_line'],
                'district' => $payload['district'],
                'city' => $payload['city'],
                'region' => $payload['region'],
                'country' => $payload['country'],
                'latitude' => $quote['coordinates']['latitude'],
                'longitude' => $quote['coordinates']['longitude'],
                'place_id' => $quote['coordinates']['place_id'],
                'is_default' => false,
            ]);

            // Lock stock rows before decrementing inventory to prevent concurrent overselling.
            foreach ($quote['lines'] as $line) {
                $lockedProduct = Product::query()->lockForUpdate()->find($line['product']->id);
                if (
                    ! $lockedProduct
                    || ! $lockedProduct->is_active
                    || $lockedProduct->stock_quantity < $line['quantity']
                    || (float) $lockedProduct->price !== (float) $line['product']->price
                ) {
                    throw new \RuntimeException('A selected product is unavailable, its price changed, or it no longer has enough stock. Please review your cart.');
                }
            }

            $order = Order::create([
                'user_id' => null,
                'guest_name' => $payload['name'],
                'guest_phone' => $payload['phone'],
                'guest_access_token_hash' => hash('sha256', $plainGuestToken),
                'address_id' => $address->id,
                'status' => 'pending',
                'subtotal' => $quote['subtotal'],
                'delivery_fee' => $quote['delivery_fee'],
                'vehicle_type' => $payload['vehicle_type'],
                'total_amount' => $quote['subtotal'] + $quote['delivery_fee'],
            ]);

            foreach ($quote['lines'] as $line) {
                $product = Product::findOrFail($line['product']->id);
                $itemTotal = $line['quantity'] * $product->price;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $itemTotal,
                ]);
                $product->decrement('stock_quantity', $line['quantity']);
            }

            foreach ($quote['seller_delivery'] as $sellerId => $delivery) {
                SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'status' => 'pending',
                    'seller_total' => $delivery['seller_subtotal'],
                    'distance_km' => $delivery['distance_km'],
                    'duration_minutes' => $delivery['duration_minutes'],
                    'delivery_fee' => $delivery['delivery_fee'],
                ]);
            }

            return $order;
        });

        $order->load(['address', 'items.product.seller', 'sellerOrders.seller']);
        $order->makeHidden('guest_access_token_hash');

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => array_merge($order->toArray(), ['guest_access_token' => $plainGuestToken]),
        ], 201);
    }

    private function makeQuote(
        array $payload,
        AddressGeocodingService $geocoding,
        RoutingService $routing,
        DeliveryFeeService $fees
    ): array|JsonResponse {
        // Derive price totals from database records rather than values supplied by the browser.
        try {
            // Prefer the customer-selected map point; retain text geocoding for older API clients.
            $coordinates = isset($payload['latitude'], $payload['longitude'])
                ? [
                    'latitude' => (float) $payload['latitude'],
                    'longitude' => (float) $payload['longitude'],
                    'place_id' => null,
                ]
                : $geocoding->geocode([
                    'address_line' => $payload['address_line'],
                    'district' => $payload['district'],
                    'city' => $payload['city'],
                    'region' => $payload['region'],
                    'country' => $payload['country'],
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'We could not locate that delivery address. Check the address and try again.'], 503);
        }

        if (! $coordinates) {
            return response()->json(['success' => false, 'message' => 'We could not find that delivery address. Check it and try again.'], 422);
        }

        $products = Product::query()
            ->whereIn('id', collect($payload['items'])->pluck('product_id'))
            ->with(['seller.addresses', 'category'])
            ->get()
            ->keyBy('id');
        $lines = [];
        $groups = [];
        $subtotal = 0;

        foreach ($payload['items'] as $requested) {
            $product = $products->get($requested['product_id']);
            if (! $product || ! $product->is_active || $product->stock_quantity < $requested['quantity']) {
                return response()->json(['success' => false, 'message' => 'A selected product is unavailable or has insufficient stock. Please review your cart.'], 422);
            }
            $lineTotal = (float) $product->price * (int) $requested['quantity'];
            $subtotal += $lineTotal;
            $lines[] = ['product' => $product, 'quantity' => (int) $requested['quantity']];
            $groups[$product->seller_id][] = ['product' => $product, 'quantity' => (int) $requested['quantity'], 'total' => $lineTotal];
        }

        $sellerDelivery = [];
        $deliveryTotal = 0;
        foreach ($groups as $sellerId => $sellerLines) {
            $seller = $sellerLines[0]['product']->seller;
            $sellerAddress = $seller?->addresses?->sortByDesc('is_default')->sortByDesc('created_at')->first();
            if (! $seller || ! $sellerAddress) {
                return response()->json(['success' => false, 'message' => 'A store is missing its delivery address, so this order cannot be delivered yet.'], 422);
            }

            try {
                if (! $geocoding->ensureCoordinates($sellerAddress)) {
                    return response()->json(['success' => false, 'message' => 'We could not locate one of the store addresses.'], 422);
                }
                $route = $routing->getDrivingRoute(
                    (float) $sellerAddress->latitude,
                    (float) $sellerAddress->longitude,
                    (float) $coordinates['latitude'],
                    (float) $coordinates['longitude']
                );
                $fee = $fees->calculate($route['distance_km'], $payload['vehicle_type']);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json(['success' => false, 'message' => 'Unable to calculate delivery for one of the stores right now.'], 503);
            }

            $sellerDelivery[$sellerId] = [
                'seller_subtotal' => array_sum(array_column($sellerLines, 'total')),
                'distance_km' => $route['distance_km'],
                'duration_minutes' => $route['duration_minutes'],
                'delivery_fee' => $fee['delivery_fee'],
            ];
            $deliveryTotal += $fee['delivery_fee'];
        }

        return [
            'coordinates' => $coordinates,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryTotal,
            'seller_delivery' => $sellerDelivery,
        ];
    }

    /** Link a guest checkout to the authenticated buyer who can prove its order token. */
    public function claim(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('buyer')) {
            return response()->json(['success' => false, 'message' => 'Only a buyer account can claim a guest order.'], 403);
        }

        $payload = $request->validate([
            'guest_access_token' => ['required', 'string', 'size:64'],
        ]);
        $tokenHash = hash('sha256', $payload['guest_access_token']);

        // Repeating a claim from the same account is safe if a network retry occurs.
        if ($order->user_id === $user->id) {
            return response()->json(['success' => true, 'message' => 'Order is already linked to this account.']);
        }

        $claimed = DB::transaction(function () use ($order, $user, $tokenHash) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->user_id === $user->id) {
                return true;
            }

            // A token mismatch and an already-claimed order share the same response to avoid leaking order data.
            if (
                $lockedOrder->user_id !== null
                || ! $lockedOrder->guest_access_token_hash
                || ! hash_equals((string) $lockedOrder->guest_access_token_hash, $tokenHash)
            ) {
                return false;
            }

            $lockedOrder->user_id = $user->id;
            $lockedOrder->guest_access_token_hash = null;
            $lockedOrder->save();

            // Preserve the saved delivery address for this buyer after the guest order is claimed.
            $address = Address::query()->whereKey($lockedOrder->address_id)->lockForUpdate()->first();
            if ($address && $address->user_id === null) {
                $address->user_id = $user->id;
                $address->save();
            }

            return true;
        });

        if (! $claimed) {
            return response()->json(['success' => false, 'message' => 'This guest order could not be linked to your account.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Guest order linked to your account.']);
    }
}