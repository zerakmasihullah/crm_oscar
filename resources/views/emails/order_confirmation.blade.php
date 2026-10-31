<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background: #EA7516;
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header p {
            margin: 0;
            font-size: 14px;
            opacity: 0.95;
        }
        .content {
            padding: 30px 20px;
        }
        .order-number {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .order-number strong {
            font-size: 18px;
            color: #EA7516;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-box {
            background: #f9f9f9;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-weight: 500;
        }
        .info-value {
            color: #333;
            font-weight: 600;
            text-align: right;
        }
        .items-list {
            margin-top: 15px;
        }
        .item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .item:last-child {
            border-bottom: none;
        }
        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        .item-details {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }
        .item-price {
            color: #EA7516;
            font-weight: 600;
            margin-top: 4px;
        }
        .total-box {
            background: #EA7516;
            color: #ffffff;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
        }
        .total-label {
            font-weight: 600;
        }
        .total-amount {
            font-size: 24px;
            font-weight: 700;
        }
        .notes {
            background: #fff9f0;
            border-left: 4px solid #EA7516;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        .notes strong {
            display: block;
            color: #EA7516;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .notes p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        .footer {
            background: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
        .footer p {
            margin: 5px 0;
        }
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            .content {
                padding: 20px 15px;
            }
            .info-row {
                flex-direction: column;
                gap: 4px;
            }
            .info-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Order Confirmed!</h1>
            <p>Thank you for your order</p>
        </div>

        <div class="content">
            <div class="order-number">
                <strong>Order #{{ $order->id }}</strong>
            </div>

            <div class="section">
                <div class="section-title">Order Information</div>
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Date</span>
                        <span class="info-value">{{ $order->created_at->format('M d, Y h:i A') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type</span>
                        <span class="info-value">{{ $order->type == 1 ? 'Delivery' : 'Collection' }}</span>
                    </div>
                    @if($order->delivery_datetime)
                    <div class="info-row">
                        <span class="info-label">{{ $order->type == 1 ? 'Delivery' : 'Collection' }} Time</span>
                        <span class="info-value">{{ $order->delivery_datetime->format('M d, Y h:i A') }}</span>
                    </div>
                    @endif
                    <div class="info-row">
                        <span class="info-label">Payment Method</span>
                        <span class="info-value">{{ ucfirst($order->payment_method ?? 'Cash') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            @if($order->status == 1) Pending
                            @elseif($order->status == 2) Confirmed
                            @elseif($order->status == 3) Preparing
                            @elseif($order->status == 4) Out for Delivery
                            @elseif($order->status == 5) Completed
                            @else {{ $order->status }}
                            @endif
                        </span>
                    </div>
                    @if($order->address && $order->type == 1)
                    <div class="info-row">
                        <span class="info-label">Delivery Address</span>
                        <span class="info-value">{{ $order->address }}</span>
                    </div>
                    @endif
                    @if($order->phone_number)
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value">{{ $order->phone_number }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="section">
                <div class="section-title">Order Items</div>
                <div class="items-list">
                    @foreach($order->order_items as $orderItem)
                    <div class="item">
                        <div class="item-name">{{ $orderItem->menu_item->title ?? 'Item' }} × {{ $orderItem->quantity }}</div>
                        @if($orderItem->size || ($orderItem->orderAttachments && $orderItem->orderAttachments->count() > 0))
                        <div class="item-details">
                            @if($orderItem->size){{ $orderItem->size->size }}@endif
                            @if($orderItem->size && $orderItem->orderAttachments && $orderItem->orderAttachments->count() > 0) • @endif
                            @if($orderItem->orderAttachments && $orderItem->orderAttachments->count() > 0)
                                @foreach($orderItem->orderAttachments as $attachment)
                                    {{ $attachment->attachment->name ?? '' }}@if($attachment->quantity > 1) ×{{ $attachment->quantity }}@endif
                                    @if(!$loop->last), @endif
                                @endforeach
                            @endif
                        </div>
                        @endif
                        <div class="item-price">€{{ number_format($orderItem->price * $orderItem->quantity, 2) }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            @php
                $subtotal = $order->order_items->sum(function($item) {
                    return $item->price * $item->quantity;
                });
                
                $deliverySettings = \App\Models\Delivery::first();
                $serviceFee = $deliverySettings->service_fees ?? 0;
                $deliveryFee = 0;
                
                if ($order->type == 1) {
                    $deliveryPrice = $deliverySettings->delivery_price ?? 0;
                    $freeDeliveryLimit = $deliverySettings->free_delivery_limit ?? 0;
                    
                    if ($subtotal < $freeDeliveryLimit) {
                        $deliveryFee = $deliveryPrice;
                    }
                }
            @endphp

            <div class="section">
                <div class="section-title">Price Summary</div>
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Subtotal</span>
                        <span class="info-value">€{{ number_format($subtotal, 2) }}</span>
                    </div>
                    @if($serviceFee > 0)
                    <div class="info-row">
                        <span class="info-label">Service Fee</span>
                        <span class="info-value">€{{ number_format($serviceFee, 2) }}</span>
                    </div>
                    @endif
                    @if($deliveryFee > 0)
                    <div class="info-row">
                        <span class="info-label">Delivery Fee</span>
                        <span class="info-value">€{{ number_format($deliveryFee, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="total-box">
                <div class="total-row">
                    <span class="total-label">Total</span>
                    <span class="total-amount">€{{ number_format($order->total_price, 2) }}</span>
                </div>
            </div>

            @if($order->description)
            <div class="notes">
                <strong>Order Notes</strong>
                <p>{{ $order->description }}</p>
            </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>Oscar's Pizza & Kebab</strong></p>
            <p>We'll notify you when your order is ready!</p>
            <p>&copy; {{ date('Y') }} All rights reserved.</p>
        </div>
    </div>
</body>
</html>
