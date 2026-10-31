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
        body { font-family: 'DM Sans', sans-serif; background: #fafafa; margin: 0; padding: 30px 15px; }
        .email-wrapper { max-width: 580px; margin: 0 auto; }
        .container { background: #ffffff; border: 1px solid #e8e8e8; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .header { background: #ffffff; padding: 40px 30px 30px; text-align: center; border-bottom: 1px solid #f0f0f0; }
        .logo { height: 38px; width: auto; margin: 0 auto 20px; }
        .header h1 { color: #2c2c2c; font-size: 30px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .header .divider { width: 60px; height: 3px; background: #EA7516; margin: 20px auto; }
        .content { padding: 40px 35px; }
        .promotion-image { width: 100%; border-radius: 8px; margin-bottom: 30px; border: 1px solid #f0f0f0; }
        .promotion-content { color: #444; font-size: 17px; line-height: 1.9; margin-bottom: 30px; }
        .promotion-content h2 { color: #2c2c2c; font-size: 24px; font-weight: 600; margin-bottom: 18px; }
        .promotion-content p { margin-bottom: 18px; }
        .cta-container { text-align: center; margin-top: 35px; padding-top: 30px; border-top: 1px solid #f0f0f0; }
        .cta { display: inline-block; padding: 14px 35px; background: #2c2c2c; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600; letter-spacing: 0.5px; }
        .cta:hover { background: #EA7516; }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .content { padding: 30px 25px; }
            .header { padding: 30px 25px 25px; }
            .header h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('logo.png') }}" alt="Pazzi & Kebab" class="logo" onerror="this.style.display='none'">
                <h1>{{ $promotion->subject }}</h1>
                <div class="divider"></div>
            </div>

            <div class="content">
                @if($promotion->image)
                    <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion" class="promotion-image">
                @endif

                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>

                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">Discover More</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

