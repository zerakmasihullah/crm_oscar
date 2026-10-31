<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\MenuItem;
use App\Models\Size;

class OrderPricingService
{
    /**
     * @param  list<array{menu_item_id?: int|null, size_id?: int|null, quantity?: int|null, price?: float|int|null}>  $orderItems
     * @return array{subtotal: float, total: float, delivery_fee: float, service_fee: float, hint: string}
     */
    public function calculate(array $orderItems, int $type = 2, float $discount = 0): array
    {
        $subtotal = collect($orderItems)->sum(function (array $item): float {
            $quantity = max((int) ($item['quantity'] ?? 1), 1);

            // Prefer the final unit price (base + toppings) when provided.
            if (array_key_exists('price', $item) && $item['price'] !== null && $item['price'] !== '') {
                $unit = (float) $item['price'];
            } elseif (! empty($item['size_id'])) {
                $size = Size::query()->find($item['size_id']);
                $unit = (float) ($size?->price ?? 0);
            } else {
                $menuItem = MenuItem::query()->find($item['menu_item_id'] ?? null);
                $unit = (float) ($menuItem?->price ?? 0);
            }

            return $unit * $quantity;
        });

        $service = Delivery::query()->first();
        $serviceFee = (float) ($service?->service_fees ?? 0);
        $storeDiscount = (float) ($service?->discount_for_all ?? 0);
        $deliveryFee = 0.0;

        if ($type === 1) {
            $deliveryFee = (float) ($service?->delivery_price ?? 0);
            $freeLimit = (float) ($service?->free_delivery_limit ?? 0);

            if ($freeLimit > 0 && $subtotal >= $freeLimit) {
                $deliveryFee = 0.0;
            }

            $total = $subtotal + $deliveryFee + $serviceFee;
        } else {
            $total = $subtotal + $serviceFee;
        }

        $total = max(0, $total - max(0, $discount) - max(0, $storeDiscount));

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'service_fee' => round($serviceFee, 2),
            'store_discount' => round($storeDiscount, 2),
            'hint' => $this->hint($subtotal, $discount, $type, $deliveryFee, $serviceFee, $total, $service),
        ];
    }

    protected function hint(
        float $subtotal,
        float $discount,
        int $type,
        float $deliveryFee,
        float $serviceFee,
        float $total,
        ?Delivery $service,
    ): string {
        $parts = [];

        if ($subtotal > 0) {
            $parts[] = 'Subtotal: €'.number_format($subtotal, 2);
        }

        if ($discount > 0) {
            $parts[] = 'Discount: -€'.number_format($discount, 2);
        }

        if ($type === 1) {
            $freeLimit = (float) ($service?->free_delivery_limit ?? 0);
            if ($freeLimit > 0 && $subtotal >= $freeLimit) {
                $parts[] = 'Free delivery applied!';
            } elseif ($deliveryFee > 0) {
                $parts[] = 'Delivery fee: €'.number_format($deliveryFee, 2);
            }
        }

        if ($serviceFee > 0) {
            $parts[] = 'Service fee: €'.number_format($serviceFee, 2);
        }

        $parts[] = 'Total: €'.number_format($total, 2);

        return implode(' | ', $parts);
    }
}
