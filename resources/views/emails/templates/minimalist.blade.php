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
        body { font-family: 'DM Sans', sans-serif; background: #ffffff; margin: 0; padding: 40px 20px; }
        .email-wrapper { max-width: 550px; margin: 0 auto; }
        .container { background: #ffffff; }
        .header { text-align: center; padding-bottom: 30px; border-bottom: 1px solid #e5e5e5; margin-bottom: 35px; }
        .logo { height: 32px; width: auto; margin: 0 auto 20px; }
        .header h1 { color: #333; font-size: 28px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .content { padding: 0; }
        .promotion-content { color: #555; font-size: 16px; line-height: 1.9; margin-bottom: 35px; }
        .promotion-content h2 { color: #333; font-size: 20px; font-weight: 600; margin-bottom: 15px; }
        .promotion-content p { margin-bottom: 15px; }
        .image-container { margin: 30px 0; }
        .promotion-image { width: 100%; border-radius: 8px; }
        .cta-container { text-align: center; margin-top: 40px; padding-top: 30px; border-top: 1px solid #e5e5e5; }
        .cta { display: inline-block; padding: 14px 32px; background: #333; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600; }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            body { padding: 30px 15px; }
            .header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('logo.png') }}" alt="Pazzi & Kebab" class="logo" onerror="this.style.display='none'">
                <h1>{{ $promotion->subject }}</h1>
            </div>

            <div class="content">
                @if($promotion->image)
                    <div class="image-container">
                        <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion" class="promotion-image">
                    </div>
                @endif

                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>

                <div class="cta-container">
                    <a href="https://www.oscarlimerick.com/" class="cta">Order Now</a>
                </div>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

