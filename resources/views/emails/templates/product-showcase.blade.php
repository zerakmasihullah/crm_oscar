<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $promotion->subject }} - Pazzi & Kebab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; margin: 0; padding: 16px 10px; }
        .email-wrapper { max-width: 600px; margin: 0 auto; }
        .container { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); }
        .header { background: #ffffff; padding: 25px 20px; text-align: center; border-bottom: 3px solid #EA7516; }
        .logo { height: 35px; width: auto; margin: 0 auto; }
        .product-showcase { position: relative; }
        .product-image { width: 100%; height: 350px; object-fit: cover; display: block; }
        .product-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 40px 30px 30px; }
        .product-title { color: #ffffff; font-size: 32px; font-weight: 800; margin-bottom: 10px; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        .product-badge { display: inline-block; background: #EA7516; color: #ffffff; padding: 8px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; margin-bottom: 15px; }
        .content { padding: 35px 28px; background: #ffffff; }
        .promotion-content { color: #333; font-size: 16px; line-height: 1.8; margin-bottom: 25px; }
        .promotion-content h2 { color: #EA7516; font-size: 22px; font-weight: 700; margin-bottom: 12px; }
        .cta-container { text-align: center; margin-top: 25px; }
        .cta { display: inline-block; padding: 15px 35px; background: #EA7516; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-size: 17px; font-weight: 600; box-shadow: 0 4px 12px rgba(234, 117, 22, 0.3); }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .product-image { height: 250px; }
            .product-overlay { padding: 30px 20px 20px; }
            .product-title { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('logo_white.png') }}" alt="Pazzi & Kebab" class="logo" onerror="this.style.display='none'">
            </div>
            
            <div class="product-showcase">
                @if($promotion->image)
                    <img src="{{ asset('storage/' . $promotion->image) }}" alt="Product" class="product-image">
                    <div class="product-overlay">
                        <div class="product-badge">⭐ Featured Product</div>
                        <h1 class="product-title">{{ $promotion->subject }}</h1>
                    </div>
                @else
                    <div style="background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); padding: 60px 30px; text-align: center;">
                        <h1 style="color: #ffffff; font-size: 32px; font-weight: 800; margin-bottom: 15px;">{{ $promotion->subject }}</h1>
                        <div class="product-badge" style="background: rgba(255,255,255,0.2);">⭐ Featured Product</div>
                    </div>
                @endif
            </div>

            <div class="content">
                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>
                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">Order This Now →</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

