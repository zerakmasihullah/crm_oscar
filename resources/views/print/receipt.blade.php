<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #{{ $order->id }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            color: #000;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html,
        body {
            background: #fff;
            color: #000 !important;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            color: #000 !important;
            display: flex;
            justify-content: center;
            -webkit-font-smoothing: none;
        }

        .receipt {
            width: 80mm;
            max-width: 80mm;
            padding: 2mm 2.5mm;
            background: #fff;
            color: #000 !important;
            -webkit-text-stroke: 0.25px #000;
        }

        .feed-top,
        .feed-bottom {
            width: 100%;
            display: block;
        }

        .feed-top {
            height: 14mm;
            min-height: 14mm;
        }

        .feed-bottom {
            height: 18mm;
            min-height: 18mm;
        }

        .center {
            text-align: center;
        }

        .brand {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .type {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.1;
            text-transform: uppercase;
            margin: 0 0 1px;
        }

        .order-no {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.1;
            margin: 1px 0 6px;
        }

        .schedule {
            margin: 0 0 2px;
        }

        .schedule-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
        }

        .schedule-label {
            flex: 0 0 auto;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .schedule-value {
            flex: 1 1 auto;
            text-align: right;
            white-space: nowrap;
        }

        .schedule-row.due-row .schedule-value {
            font-size: 14px;
            font-weight: 800;
        }

        .sep {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            white-space: nowrap;
            font-family: "Courier New", Courier, monospace;
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: 0;
            margin: 5px 0;
            color: #000;
        }

        .group {
            font-size: 13px;
            font-weight: 700;
            margin: 6px 0 2px;
        }

        .item-block {
            margin-bottom: 6px;
        }

        .choice-group {
            margin: 0;
        }

        .mini-sep {
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            margin: 2px 0;
        }

        .line {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 6px;
            font-size: 12px;
        }

        .line .name {
            flex: 1;
        }

        .line .price {
            white-space: nowrap;
            text-align: right;
            font-weight: 700;
        }

        .item-line,
        .item-line .name {
            font-size: 14px;
            font-weight: 700;
        }

        .choice {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            padding-left: 14px;
            font-size: 11px;
            font-weight: 400;
            line-height: 1.2;
            -webkit-text-stroke: 0.15px #000;
        }

        .choice-header {
            padding-left: 0;
            font-size: 12px;
            font-weight: 700;
            margin-top: 3px;
            margin-bottom: 0;
        }

        .choice-spacer {
            margin-top: 8px;
        }

        .totals {
            font-size: 13px;
            font-weight: 700;
        }

        .totals .line {
            margin: 1px 0;
        }

        .totals .total-due {
            font-size: 16px;
            font-weight: 800;
            margin-top: 4px;
        }

        .notes {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.3;
            margin: 4px 0;
        }

        .notes-label {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .allergen {
            font-size: 10px;
            line-height: 1.25;
        }

        .allergen strong {
            display: block;
            font-weight: 700;
        }

        .pay-status {
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .customer-id,
        .section-label,
        .contact-label {
            font-size: 13px;
            font-weight: 600;
        }

        .contact-label {
            margin-top: 8px;
        }

        .customer-name {
            font-size: 24px;
            font-weight: 700;
            line-height: 1.1;
            margin: 6px 0 4px;
        }

        .customer-line {
            font-size: 17px;
            font-weight: 700;
            line-height: 1.25;
        }

        .contact-phone {
            font-size: 18px;
            font-weight: 700;
        }

        .meta {
            font-size: 10px;
            line-height: 1.3;
        }

        .meta .row {
            display: flex;
            justify-content: space-between;
            gap: 6px;
        }

        .qr-wrap {
            text-align: center;
            margin: 10px 0 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .qr-wrap img {
            width: 24mm;
            height: 24mm;
            display: block;
            margin: 0 auto;
        }

        .qr-caption,
        .not-a-bill {
            font-size: 11px;
        }

        .not-a-bill {
            margin-top: 4px;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            html,
            body {
                width: 80mm;
                background: #fff;
            }

            body {
                display: block;
            }

            .receipt {
                width: 80mm;
                max-width: 80mm;
                padding: 2mm;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .feed-top {
                height: 14mm;
            }

            .feed-bottom {
                height: 18mm;
            }
        }
    </style>
</head>

<body>
    @php
        $isDelivery = (int) $order->type === 1;
        $customerName = $order->customer_name ?? ($order->customer?->name ?? null);
        $phoneNumber = $order->phone_number ?? ($order->customer?->phone_number ?? null);
        $address = $order->address ?? ($order->customer?->address ?? null);
        $areaCode = $order->area_code ?? ($order->customer?->area_code ?? null);

        // Limerick, Ireland (Europe/Dublin)
        $irelandTz = 'Europe/Dublin';
        $placedAt = $order->created_at->copy()->timezone($irelandTz);
        $acceptedAt = $order->updated_at->copy()->timezone($irelandTz);
        $defaultMinutes = $isDelivery ? 45 : 15;
        $asapDueAt = $placedAt->copy()->addMinutes($defaultMinutes);

        $selectedAt = $order->delivery_datetime
            ? $order->delivery_datetime->copy()->timezone($irelandTz)
            : null;

        // No delivery time (or only an ASAP-window time) → +45 delivery / +15 collection
        $isScheduled = $selectedAt && $selectedAt->gt($asapDueAt);
        $dueAt = $isScheduled ? $selectedAt : $asapDueAt;
        $orderDateLabel = $placedAt->format('d M · g:i A');
        if ($isScheduled) {
            $dueLabel = $dueAt->isSameDay($placedAt)
                ? $dueAt->format('g:i A')
                : $dueAt->format('d M · g:i A');
        } else {
            $dueLabel = 'ASAP · ' . $dueAt->format('g:i A');
        }
        $orderNotes = \App\Models\Order::stripSource($order->description ?? '');
        $phoneDigits = preg_replace('/\D+/', '', (string) $phoneNumber);
        if (str_starts_with($phoneDigits, '353')) {
            $phoneDigits = '0' . substr($phoneDigits, 3);
        }
        if (strlen($phoneDigits) === 10) {
            $formattedPhone = substr($phoneDigits, 0, 3) . ' ' . substr($phoneDigits, 3, 3) . ' ' . substr($phoneDigits, 6);
        } elseif (strlen($phoneDigits) === 9) {
            $formattedPhone = substr($phoneDigits, 0, 2) . ' ' . substr($phoneDigits, 2, 3) . ' ' . substr($phoneDigits, 5);
        } else {
            $formattedPhone = $phoneNumber;
        }

        $addressLines = [];
        if ($address) {
            foreach (preg_split('/[\n,]+/', $address) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $addressLines[] = $part;
                }
            }
        }
        if ($areaCode) {
            $hasEircode = collect($addressLines)->contains(fn ($line) => stripos($line, $areaCode) !== false);
            if (!$hasEircode) {
                $addressLines[] = $areaCode;
            }
        }

        $mapsQuery = trim(implode(', ', array_filter([...$addressLines, 'Limerick', 'Ireland'])));
        $mapsUrl = $mapsQuery !== ''
            ? 'https://maps.google.com/?q=' . rawurlencode($mapsQuery)
            : null;

        $groupedItems = $order->order_items->groupBy(function ($item) {
            return $item->menu_item?->menu?->title ?: 'Items';
        });

        $totalPrice = (float) $order->total_price;
        $serviceFees = (float) ($delivery->service_fees ?? 0);
        $deliveryPrice = 0;
        $deliveryIsFree = false;
        if ($isDelivery && $delivery) {
            $deliveryIsFree = $totalPrice >= (float) $delivery->free_delivery_limit;
            $deliveryPrice = $deliveryIsFree ? 0 : (float) $delivery->delivery_price;
        }
        $discount = (float) ($order->discount ?? 0);
        $subtotal = (float) ($order->subtotal ?? ($totalPrice - $serviceFees - $deliveryPrice + $discount));
        $orderTotal = max($subtotal - $discount, 0);

        $isPaid = $order->payment_method === 'online';
        $previousOrders = $previousOrders ?? 0;
        $orderNo = (string) $order->id;
        $formattedOrderNo = strlen($orderNo) > 4
            ? trim(strrev(chunk_split(strrev($orderNo), 3, ' ')))
            : $orderNo;
    @endphp

    <div class="receipt">
        <div class="feed-top">&nbsp;</div>
        <div class="brand center">Oscar's Pizza &amp; Kebab</div>

        <div class="type center">{{ $isDelivery ? 'Delivery' : 'Collection' }}</div>
        <div class="order-no center"># {{ $formattedOrderNo }}</div>

        <div class="schedule">
            <div class="schedule-row">
                <span class="schedule-label">Order</span>
                <span class="schedule-value">{{ $orderDateLabel }}</span>
            </div>
            <div class="schedule-row due-row">
                <span class="schedule-label">Due</span>
                <span class="schedule-value">{{ $dueLabel }}</span>
            </div>
        </div>

        <div class="sep">--------------------------------------------------------------------------------</div>

        @foreach ($groupedItems as $groupName => $items)
            <div class="group">{{ $groupName }}</div>

            @foreach ($items as $item)
                @php
                    $copy = $item->kitchenCopies();
                @endphp
                <div class="item-block">
                    <div class="line item-line">
                        <span class="name">
                            {{ $copy['quantity'] }} x {{ $item->menu_item?->title ?? 'Item' }}
                            @if ($item->size)
                                {{ $item->size->size }}
                            @endif
                        </span>
                                <span class="price">€{{ number_format($copy['price'], 2) }}</span>
                    </div>

                    @foreach ($copy['choice_groups'] as $choices)
                        <div class="choice-group">
                            @foreach ($choices as $choice)
                                @if (!empty($choice['separator']))
                                    <div class="mini-sep">---</div>
                                    @continue
                                @endif
                                <div class="choice{{ !empty($choice['header']) ? ' choice-header' : '' }}{{ !empty($choice['spacer_before']) ? ' choice-spacer' : '' }}">
                                    @if (!empty($choice['header']))
                                        {{ $choice['name'] }}
                                    @else
                                        <span>
                                            @if ((int) $choice['quantity'] > 1)
                                                {{ $choice['quantity'] }} x {{ $choice['name'] }}
                                            @else
                                                {{ $choice['name'] }}
                                            @endif
                                        </span>
                                        @if (!empty($choice['show_price']) && (float) ($choice['price'] ?? 0) > 0)
                                            <span class="price">€{{ number_format($choice['price'], 2) }}</span>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endforeach

        <div class="sep">--------------------------------------------------------------------------------</div>

        @if ($orderNotes !== '')
            <div class="notes">
                <div class="notes-label">Notes</div>
                <div>{{ $orderNotes }}</div>
            </div>
            <div class="sep">--------------------------------------------------------------------------------</div>
        @endif

        <div class="totals">
            <div class="line">
                <span>Subtotal</span>
                <span class="price">€{{ number_format($subtotal, 2) }}</span>
            </div>

            @if ($discount > 0)
                <div class="line">
                    <span>Discount</span>
                    <span class="price">-€{{ number_format($discount, 2) }}</span>
                </div>
            @endif

            <div class="line">
                <span>Order total</span>
                <span class="price">€{{ number_format($orderTotal, 2) }}</span>
            </div>

            @if ($serviceFees > 0)
                <div class="line">
                    <span>Service charge</span>
                    <span class="price">€{{ number_format($serviceFees, 2) }}</span>
                </div>
            @endif

            @if ($isDelivery)
                <div class="line">
                    <span>Delivery charge</span>
                    <span class="price">{{ $deliveryIsFree ? 'Free' : '€' . number_format($deliveryPrice, 2) }}</span>
                </div>
            @endif

            <div class="line total-due">
                <span>Total Due</span>
                <span class="price">€{{ number_format($totalPrice, 2) }}</span>
            </div>
        </div>

        <div class="sep">--------------------------------------------------------------------------------</div>

        @if ($orderNotes !== '')
            <div class="notes">
                <div class="notes-label">Notes</div>
                <div>{{ $orderNotes }}</div>
            </div>
            <div class="sep">--------------------------------------------------------------------------------</div>
        @endif


        <div class="pay-status center">{{ $isPaid ? 'Paid' : 'Order not paid' }}</div>

        <div class="sep">--------------------------------------------------------------------------------</div>

        @if ($order->customer_id)
            <div class="customer-id">Customer ID {{ $order->customer_id }}</div>
        @endif

        @if ($customerName || count($addressLines) || $formattedPhone)
            <div class="section-label">Customer details:</div>

            @if ($customerName)
                <div class="customer-name">{{ $customerName }}</div>
            @endif

            @if ($isDelivery)
                @foreach ($addressLines as $line)
                    <div class="customer-line">{{ $line }}</div>
                @endforeach
            @endif

            @if ($formattedPhone)
                <div class="contact-label">To contact customer call:</div>
                <div class="contact-phone">{{ $formattedPhone }}</div>
            @endif
        @endif

        <div class="sep">--------------------------------------------------------------------------------</div>

        <div class="meta">
            <div>Previous orders from this customer: {{ $previousOrders }}</div>
            <div class="row">
                <span>Order placed at</span>
                <span>{{ $placedAt->format('g:i A d M') }}</span>
            </div>
            @if ((int) $order->status >= 2)
                <div class="row">
                    <span>Order accepted at</span>
                    <span>{{ $acceptedAt->format('g:i A d M') }}</span>
                </div>
            @endif
        </div>

        @if ($isDelivery && $mapsUrl)
            <div class="qr-wrap">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data={{ rawurlencode($mapsUrl) }}" alt="Scan to deliver">
                <div class="qr-caption">Scan to deliver</div>
            </div>
        @endif

        <div class="not-a-bill center">This is not a bill</div>
        <div class="feed-bottom">&nbsp;</div>
    </div>

    <script>
        const page = @json((string) ($page ?? ''));
        let finished = false;

        function redirectAfterPrint() {
            if (finished) return;
            finished = true;

            if (page === 'order') {
                window.location.href = '/orders/create';
                return;
            }
            if (page === 'deal') {
                window.location.href = '/orders/create-deal';
                return;
            }
            if (page === 'allOrder') {
                window.location.href = '/orders';
                return;
            }

            // Dashboard / direct print: keep tab open until afterprint, then close.
            try {
                window.close();
            } catch (e) {}
        }

        window.addEventListener('afterprint', redirectAfterPrint);

        // Give the browser time to open the print dialog (do not close after 1s).
        window.setTimeout(function () {
            window.print();
        }, 250);

        // Fallback if afterprint never fires (some browsers).
        window.setTimeout(redirectAfterPrint, 120000);
    </script>
</body>

</html>
