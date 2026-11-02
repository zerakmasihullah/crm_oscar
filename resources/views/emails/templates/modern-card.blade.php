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
        body { font-family: 'DM Sans', sans-serif; background: #f0f0f0; margin: 0; padding: 20px 10px; }
        .email-wrapper { max-width: 600px; margin: 0 auto; }
        .card { background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12); }
        .card-header { background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); padding: 25px 20px; text-align: center; }
        .logo { height: 32px; width: auto; margin: 0 auto 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .card-header h1 { color: #ffffff; font-size: 26px; font-weight: 700; margin: 0; }
        .card-body { padding: 35px 28px; }
        .promotion-image { width: 100%; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .promotion-content { color: #333; font-size: 16px; line-height: 1.8; margin-bottom: 25px; }
        .promotion-content h2 { color: #EA7516; font-size: 22px; font-weight: 700; margin-bottom: 12px; }
        .card-footer { background: #f9f9f9; padding: 25px 28px; text-align: center; border-top: 1px solid #f0f0f0; }
        .cta { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #EA7516 0%, #f97316 100%); color: #ffffff !important; text-decoration: none; border-radius: 12px; font-size: 17px; font-weight: 600; box-shadow: 0 4px 15px rgba(234, 117, 22, 0.3); }
        .footer { text-align: center; padding: 24px; background: #fafafa; border-top: 1px solid #f0f0f0; }
        .footer-brand { color: #EA7516; font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .footer-text { color: #999; font-size: 12px; margin: 6px 0; line-height: 1.6; }
        .footer-link { color: #EA7516; text-decoration: none; font-size: 12px; }
        .unsubscribe { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
        .unsubscribe-text { color: #999; font-size: 11px; margin: 0; }
        .unsubscribe-link { color: #999; text-decoration: underline; font-size: 11px; }

        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; }
            .card-body { padding: 25px 20px; }
            .card-footer { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="card">
            <div class="card-header">
                <img src="{{ asset('logo_white.png') }}" alt="Doner House" class="logo" onerror="this.style.display='none'">
                <h1>{{ $promotion->subject }}</h1>
            </div>

            <div class="card-body">
                @if($promotion->image)
                    <img src="{{ asset('storage/' . $promotion->image) }}" alt="Promotion" class="promotion-image">
                @endif

                <div class="promotion-content">
                    {!! $promotion->content !!}
                </div>
            </div>

            <div class="card-footer">
                <a href="https://www.oscarlimerick.com/" class="cta">Order Now →</a>
            </div>
            
            @include('emails.templates._shared')
        </div>
    </div>
</body>
</html>

