<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Unsubscribed — Oscar's Pizza & Kebab</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0a0a0a; color: #f5f5f5; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { max-width: 420px; width: 100%; background: #171717; border: 1px solid #333; border-radius: 16px; padding: 28px; text-align: center; }
        h1 { font-size: 1.35rem; margin: 0 0 8px; }
        p { color: #a3a3a3; font-size: 0.95rem; line-height: 1.5; margin: 0 0 16px; }
        a { display: inline-block; margin-top: 8px; background: #f97316; color: #fff; text-decoration: none; font-weight: 600; padding: 10px 18px; border-radius: 999px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>You’re unsubscribed</h1>
        <p>
            We won’t send marketing emails to
            <strong style="color:#fff">{{ $email }}</strong>
            anymore. You can still order anytime on our website.
        </p>
        <a href="{{ config('app.url', 'https://www.oscarlimerick.com') }}">Back to menu</a>
    </div>
</body>
</html>
