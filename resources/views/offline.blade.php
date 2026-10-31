<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Offline — {{ config('app.name') }}</title>
        <style>
            :root {
                color-scheme: light dark;
                font-family: system-ui, sans-serif;
            }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: #0f172a;
                color: #e2e8f0;
                padding: 1.5rem;
            }
            main {
                max-width: 28rem;
                text-align: center;
            }
            h1 {
                font-size: 1.5rem;
                margin: 0 0 0.75rem;
            }
            p {
                margin: 0;
                line-height: 1.5;
                opacity: 0.85;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>You are offline</h1>
            <p>
                The app shell is available. Pending form submissions will sync
                automatically when your connection returns.
            </p>
        </main>
    </body>
</html>
