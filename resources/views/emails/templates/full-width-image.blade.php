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
        body { font-family: 'DM Sans', sans-serif; background: #ffffff; margin: 0; padding: 0; }
        .email-wrapper { max-width: 600px; margin: 0 auto; }
        .hero-image { width: 100%; height: 400px; object-fit: cover; display: block; }
        .overlay-content { position: relative; margin-top: -150px; background: #ffffff; border-radius: 20px 20px 0 0; padding: 40px 30px; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 25px; }
        .header h1 { color: #333; font-size: 32px; font-weight: 700; margin-bottom: 10px; }
        .header .logo { height: 35px; width: auto; margin: 0 auto 15px; }
        .promotion-content { color: #333; font-size: 16px; line-height: 1.8; margin-bottom: 30px; }
        .promotion-content h2 { color: #EA7516; font-size: 24px; font-weight: 700; margin-bottom: 15px; }
        .cta-container { text-align: center; margin-top: 30px; }
        .cta { display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); color: #ffffff !important; text-decoration: none; border-radius: 12px; font-size: 18px; font-weight: 600; box-shadow: 0 4px 15px rgba(234, 117, 22, 0.4); }
        .no-image-header { background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); padding: 60px 30px; text-align: center; }
        .no-image-header h1 { color: #ffffff; font-size: 32px; font-weight: 700; margin: 0; }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .hero-image { height: 300px; }
            .overlay-content { margin-top: -100px; padding: 30px 20px; }
            .header h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        @if($promotion->image)
            <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion" class="hero-image">
            <div class="overlay-content">
                <div class="header">
                    <img src="{{ asset('storefront/logo.png') }}" alt="Doner House" class="logo" onerror="this.style.display='none'">
                    <h1>{{ $promotion->subject }}</h1>
                </div>
                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>
                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">Order Now →</a>
                </div>
            </div>
        @else
            <div class="no-image-header">
                <img src="{{ asset('logo_white.png') }}" alt="Doner House" class="logo" onerror="this.style.display='none'" style="height: 35px; margin-bottom: 20px;">
                <h1>{{ $promotion->subject }}</h1>
            </div>
            <div style="padding: 40px 30px;">
                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>
                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">Order Now →</a>
                </div>
            </div>
        @endif
        
        @include('emails.templates._shared')
    </div>
</body>
</html>

