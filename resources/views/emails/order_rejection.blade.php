<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Rejected</title>
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
            background: #dc2626;
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
            color: #dc2626;
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
        .message-box {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 20px;
            margin-top: 20px;
            border-radius: 4px;
        }
        .message-box strong {
            display: block;
            color: #dc2626;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .message-box p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
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
            <h1>Order Rejected</h1>
            <p>We're sorry, but your order could not be processed</p>
        </div>

        <div class="content">
            <div class="order-number">
                <strong>Order #{{ $order->id }}</strong>
            </div>

            <div class="section">
                <div class="section-title">Order Details</div>
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Date</span>
                        <span class="info-value">{{ $order->created_at->format('M d, Y h:i A') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type</span>
                        <span class="info-value">{{ $order->type == 1 ? 'Delivery' : 'Collection' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total</span>
                        <span class="info-value">€{{ number_format($order->total_price, 2) }}</span>
                    </div>
                    @if($order->address && $order->type == 1)
                    <div class="info-row">
                        <span class="info-label">Address</span>
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

            <div class="message-box">
                <strong>We're Sorry</strong>
                <p>Unfortunately, we were unable to process your order #{{ $order->id }} at this time. This may be due to various reasons such as item availability, delivery area restrictions, or operational constraints.</p>
                <p>If you have any questions or would like to place a new order, please don't hesitate to contact us. We're here to help!</p>
            </div>
        </div>

        <div class="footer">
            <p><strong>Oscar's Pizza & Kebab</strong></p>
            <p>If you have any questions, please contact us at your convenience.</p>
            <p>&copy; {{ date('Y') }} All rights reserved.</p>
        </div>
    </div>
</body>
</html>
