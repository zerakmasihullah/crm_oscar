<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $promotion->subject }} - Doner House</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; margin: 0; padding: 20px 10px; }
        .email-wrapper { max-width: 600px; margin: 0 auto; }
        .container { background: #ffffff; border: 1px solid #e0e0e0; }
        .header { background: #ffffff; padding: 25px 20px; border-bottom: 3px solid #EA7516; }
        .logo { height: 35px; width: auto; }
        .date { color: #999; font-size: 12px; margin-top: 10px; }
        .content { padding: 30px 25px; }
        .promotion-content { color: #333; font-size: 16px; line-height: 1.8; margin-bottom: 25px; }
        .promotion-content h2 { color: #EA7516; font-size: 24px; font-weight: 700; margin-bottom: 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .promotion-content h3 { color: #333; font-size: 18px; font-weight: 600; margin: 20px 0 10px; }
        .promotion-image { width: 100%; margin: 20px 0; border: 1px solid #e0e0e0; }
        .cta-container { text-align: center; margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #EA7516; }
        .cta { display: inline-block; padding: 12px 30px; background: #EA7516; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: 600; }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .content { padding: 25px 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('storefront/logo.png') }}" alt="Doner House" class="logo" onerror="this.style.display='none'">
                <div class="date">{{ now()->format('F d, Y') }}</div>
            </div>

            <div class="content">
                <div class="promotion-content">
                    <h2>{{ $promotion->subject }}</h2>
                    {!! $promotion->content !!}
                </div>

                @if($promotion->image)
                    <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion" class="promotion-image">
                @endif

                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">Read More & Order</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

