<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation</title>
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
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px 20px;
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
            padding: 10px 0;
            font-size: 14px;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-weight: 600;
        }
        .info-value {
            color: #333;
            font-weight: 500;
            text-align: right;
        }
        .message-box {
            background: #fff9f0;
            border-left: 4px solid #EA7516;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        .message-box strong {
            display: block;
            color: #EA7516;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .message-box p {
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
            <h1>New Reservation</h1>
        </div>

        <div class="content">
            <div class="section-title">Reservation Details</div>
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value">{{ $reservation->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $reservation->email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $reservation->phone }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Number of Guests</span>
                    <span class="info-value">{{ $reservation->person }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date</span>
                    <span class="info-value">{{ $reservation->reservation_date }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time</span>
                    <span class="info-value">{{ $reservation->reservation_time }}</span>
                </div>
            </div>

            @if($reservation->message)
            <div class="message-box">
                <strong>Message</strong>
                <p>{{ $reservation->message }}</p>
            </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>Oscar's Pizza & Kebab</strong></p>
            <p>&copy; {{ date('Y') }} All rights reserved.</p>
        </div>
    </div>
</body>
</html>
