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
        .header { background: #ffffff; padding: 25px 20px; text-align: center; border-bottom: 2px solid #f0f0f0; }
        .logo { height: 35px; width: auto; margin: 0 auto; }
        .split-section { display: table; width: 100%; }
        .split-image { display: table-cell; width: 50%; vertical-align: middle; background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); }
        .split-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .split-content { display: table-cell; width: 50%; vertical-align: middle; padding: 40px 30px; background: #ffffff; }
        .split-content h1 { color: #333; font-size: 26px; font-weight: 700; margin-bottom: 15px; }
        .promotion-content { color: #555; font-size: 15px; line-height: 1.8; margin-bottom: 20px; }
        .promotion-content h2 { color: #EA7516; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        .cta { display: inline-block; padding: 12px 28px; background: #EA7516; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: 600; }
        .full-content { padding: 35px 28px; }
        .full-content .promotion-content { color: #333; font-size: 16px; }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .split-section { display: block; }
            .split-image, .split-content { display: block; width: 100%; }
            .split-content { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('logo.png') }}" alt="Pazzi & Kebab" class="logo" onerror="this.style.display='none'">
            </div>

            @if($promotion->image)
                <div class="split-section">
                    <div class="split-image">
                        <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion">
                    </div>
                    <div class="split-content">
                        <h1>{{ $promotion->subject }}</h1>
                        <div class="promotion-content">
                            {!! \Illuminate\Support\Str::limit(strip_tags($promotion->content), 150) !!}
                        </div>
                        <a href="https://www.oscarlimerick.com/" class="cta">Learn More →</a>
                    </div>
                </div>
            @endif

            <div class="full-content">
                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>
                <div style="text-align: center; margin-top: 25px;">
                    <a href="https://www.oscarlimerick.com/" class="cta">Order Now →</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

