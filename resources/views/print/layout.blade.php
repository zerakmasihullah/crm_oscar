@php
    use App\Models\OrgSetting;
    use App\Support\ThemeColors;

    $printAppName = OrgSetting::getValue('app_name', config('app.name'));
    $printLogoUrl = OrgSetting::logoUrl();
    $theme = ThemeColors::current();
    $brand = $theme['brand'] ?? '#171717';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $printAppName) · {{ $printAppName }}</title>
    <style>
        :root {
            color-scheme: light;
            --brand: {{ $brand }};
            --ink: #0a0a0a;
            --muted: #737373;
            --line: #e5e5e5;
            --paper: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #f5f5f5;
            font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .page {
            min-height: 100vh;
            padding: 36px 16px 48px;
        }
        .sheet {
            max-width: 880px;
            margin: 0 auto;
            padding: 28px 32px 32px;
            background: var(--paper);
            border: 1px solid #ebebeb;
            border-radius: 20px;
            box-shadow: 0 18px 50px -28px rgba(0, 0, 0, 0.28);
        }
        .mast {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px 20px;
            align-items: center;
            padding-bottom: 20px;
            margin-bottom: 22px;
            border-bottom: 1px solid var(--line);
        }
        .identity {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .logo,
        .logo-fallback {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .logo {
            object-fit: contain;
            background: #fafafa;
            border: 1px solid var(--line);
        }
        .logo-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--brand);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .org {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .actions {
            grid-column: 2;
            grid-row: 1 / span 2;
            align-self: start;
        }
        .actions button {
            appearance: none;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.45rem 0.95rem;
            cursor: pointer;
        }
        .actions button:hover { background: #fafafa; }
        .section {
            grid-column: 1;
            margin: 0;
            font-size: 1.35rem;
            font-weight: 650;
            letter-spacing: -0.035em;
            line-height: 1.2;
        }
        .meta {
            grid-column: 1;
            margin: 0;
            color: var(--muted);
            font-size: 0.78rem;
        }
        .table-wrap {
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        th, td {
            padding: 0.8rem 0.95rem;
            text-align: start;
            vertical-align: top;
            border-bottom: 1px solid var(--line);
        }
        th {
            background: #fafafa;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 650;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        tbody tr:last-child td { border-bottom: 0; }
        .empty {
            color: var(--muted);
            text-align: center;
            padding: 2rem 1rem;
        }
        @media print {
            body { background: #fff; }
            .page { padding: 0; }
            .sheet {
                max-width: none;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .no-print { display: none !important; }
            .mast {
                grid-template-columns: 1fr;
                padding-bottom: 14px;
                margin-bottom: 16px;
            }
            .table-wrap { border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="sheet">
            <header class="mast">
                <div class="identity">
                    @if ($printLogoUrl)
                        <img class="logo" src="{{ $printLogoUrl }}" alt="{{ $printAppName }}">
                    @else
                        <span class="logo-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($printAppName, 0, 1)) }}</span>
                    @endif
                    <p class="org">{{ $printAppName }}</p>
                </div>
                <div class="actions no-print">
                    <button type="button" onclick="window.print()">{{ __('Print') }}</button>
                </div>
                <h1 class="section">@yield('title', $printAppName)</h1>
                <p class="meta">
                    @hasSection('meta')
                        @yield('meta')
                    @else
                        {{ now()->timezone(config('app.timezone'))->toDayDateTimeString() }}
                    @endif
                </p>
            </header>

            <div class="table-wrap">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
