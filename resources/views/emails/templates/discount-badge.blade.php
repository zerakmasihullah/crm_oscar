<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $promotion->subject }} — Doner House</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; margin: 0; padding: 16px 10px; }
        .email-wrapper { max-width: 600px; margin: 0 auto; }
        .container { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); }
        .header { background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); padding: 50px 20px; text-align: center; position: relative; }
        .logo { height: 35px; width: auto; margin: 0 auto 25px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .discount-badge { background: #ffffff; color: #EA7516; padding: 20px 40px; border-radius: 50%; width: 180px; height: 180px; margin: 0 auto 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 8px 25px rgba(0,0,0,0.2); }
        .discount-percent { font-size: 48px; font-weight: 800; line-height: 1; }
        .discount-text { font-size: 16px; font-weight: 600; margin-top: 5px; }
        .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; }
        .content { padding: 35px 28px; }
        .promotion-content { color: #333; font-size: 16px; line-height: 1.8; margin-bottom: 25px; text-align: center; }
        .promotion-content h2 { color: #EA7516; font-size: 22px; font-weight: 700; margin-bottom: 12px; }
        .promotion-image { width: 100%; border-radius: 12px; margin: 25px 0; }
        .cta-container { text-align: center; margin-top: 30px; }
        .cta { display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); color: #ffffff !important; text-decoration: none; border-radius: 10px; font-size: 18px; font-weight: 600; box-shadow: 0 4px 15px rgba(234, 117, 22, 0.4); }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .discount-badge { width: 150px; height: 150px; padding: 15px 30px; }
            .discount-percent { font-size: 36px; }
            .content { padding: 25px 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('storefront/logo.png') }}" alt="Doner House" class="logo" onerror="this.style.display='none'">
                <div class="discount-badge">
                    <div class="discount-percent" style="font-size:28px;">DEAL</div>
                    <div class="discount-text">TODAY</div>
                </div>
                <h1>{{ $promotion->subject }}</h1>
            </div>

            <div class="content">
                @if($promotion->image)
                    <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion" class="promotion-image">
                @endif

                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>

                <div class="cta-container">
                    <a href="{{ $orderUrl ?? url('/') }}" class="cta">Order now →</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

