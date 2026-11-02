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
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; margin: 0; padding: 16px 10px; }
        .email-wrapper { max-width: 600px; margin: 0 auto; }
        .container { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08); }
        .header { background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); padding: 40px 20px; text-align: center; position: relative; }
        .header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></svg>'); opacity: 0.3; }
        .logo { height: 40px; width: auto; margin: 0 auto 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); position: relative; z-index: 1; }
        .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0 0 10px 0; position: relative; z-index: 1; }
        .promo-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; color: #ffffff; position: relative; z-index: 1; border: 1px solid rgba(255, 255, 255, 0.3); }
        .hero-image { width: 100%; height: 300px; object-fit: cover; display: block; }
        .content { padding: 35px 28px; }
        .promotion-content { color: #333; font-size: 16px; line-height: 1.8; margin-bottom: 30px; }
        .promotion-content h2 { color: #EA7516; font-size: 24px; font-weight: 700; margin-bottom: 15px; }
        .promotion-content p { margin-bottom: 15px; }
        .cta-container { text-align: center; margin-top: 30px; }
        .cta { display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); color: #ffffff !important; text-decoration: none; border-radius: 10px; font-size: 18px; font-weight: 600; box-shadow: 0 4px 15px rgba(234, 117, 22, 0.4); }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }
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
            .header h1 { font-size: 22px; }
            .hero-image { height: 200px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('logo_white.png') }}" alt="Doner House" class="logo" onerror="this.style.display='none'">
                <h1>{{ $promotion->subject }}</h1>
                <div class="promo-badge">🎉 Special Offer</div>
            </div>
            
            @if($promotion->image)
                <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion" class="hero-image">
            @endif

            <div class="content">
                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>
                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">Order Now →</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

