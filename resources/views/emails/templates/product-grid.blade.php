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
        .header { background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); padding: 30px 20px; text-align: center; }
        .logo { height: 35px; width: auto; margin: 0 auto 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .header h1 { color: #ffffff; font-size: 26px; font-weight: 700; margin: 0; }
        .content { padding: 30px 25px; }
        .promotion-content { color: #333; font-size: 16px; line-height: 1.8; margin-bottom: 30px; text-align: center; }
        .promotion-content h2 { color: #EA7516; font-size: 22px; font-weight: 700; margin-bottom: 15px; }
        .product-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 25px 0; }
        .product-card { background: #f9f9f9; border-radius: 12px; overflow: hidden; border: 2px solid #f0f0f0; transition: all 0.3s; }
        .product-card:hover { border-color: #EA7516; box-shadow: 0 4px 12px rgba(234, 117, 22, 0.2); }
        .product-card-image { width: 100%; height: 150px; object-fit: cover; }
        .product-card-content { padding: 15px; text-align: center; }
        .product-card-title { color: #333; font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .product-card-price { color: #EA7516; font-size: 18px; font-weight: 700; }
        .cta-container { text-align: center; margin-top: 30px; }
        .cta { display: inline-block; padding: 15px 35px; background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); color: #ffffff !important; text-decoration: none; border-radius: 10px; font-size: 17px; font-weight: 600; box-shadow: 0 4px 15px rgba(234, 117, 22, 0.4); }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .product-grid { grid-template-columns: 1fr; }
            .content { padding: 25px 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('logo_white.png') }}" alt="Pazzi & Kebab" class="logo" onerror="this.style.display='none'">
                <h1>{{ $promotion->subject }}</h1>
            </div>

            <div class="content">
                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>
                
                @if($promotion->image)
                    <div class="product-grid">
                        <div class="product-card">
                            <img src="{{ asset('storage/' . $promotion->image) }}" alt="Product" class="product-card-image">
                            <div class="product-card-content">
                                <div class="product-card-title">Special Offer</div>
                                <div class="product-card-price">Order Now</div>
                            </div>
                        </div>
                        <div class="product-card" style="background: linear-gradient(135deg, #EA7516 0%, #f97316 100%);">
                            <div style="padding: 40px 20px; text-align: center; color: #ffffff;">
                                <div style="font-size: 24px; font-weight: 700; margin-bottom: 10px;">🎉</div>
                                <div style="font-size: 18px; font-weight: 600;">Limited Time</div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">View All Products →</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

