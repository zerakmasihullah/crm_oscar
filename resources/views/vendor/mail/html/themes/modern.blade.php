{{-- Modern accent theme: soft panel, teal CTAs, rounded --}}
@php
    $colors = \App\Support\ThemeColors::current();
    $brand = $colors['brand'] ?? '#162238';
    $accent = $colors['accent'] ?? '#0d9488';
    $panel = $colors['backgroundLight'] ?? '#f1f4f9';
    $text = '#475569';
    $heading = '#0f172a';
@endphp
body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: {{ $panel }};
    color: {{ $text }};
    height: 100%;
    line-height: 1.55;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p, ul, ol, blockquote { line-height: 1.55; text-align: start; }
a { color: {{ $accent }}; }
a img { border: none; }

h1 { color: {{ $heading }}; font-size: 22px; font-weight: 700; margin-top: 0; text-align: start; }
h2 { font-size: 16px; font-weight: 700; margin-top: 0; text-align: start; }
h3 { font-size: 14px; font-weight: 700; margin-top: 0; text-align: left; }
p { font-size: 16px; line-height: 1.65em; margin-top: 0; text-align: left; }
p.sub { font-size: 12px; }
img { max-width: 100%; }

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $panel }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 0;
    padding: 0;
    width: 100%;
}

.header {
    padding: 32px 24px 12px;
    text-align: center;
}

.header a {
    color: {{ $brand }};
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -0.02em;
    text-decoration: none;
}

.logo {
    height: 44px;
    margin: 0;
    max-height: 44px;
    width: auto;
}

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $panel }};
    border-bottom: 1px solid {{ $panel }};
    border-top: 1px solid {{ $panel }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: #ffffff;
    border-color: #e2e8f0;
    border-radius: 20px;
    border-width: 1px;
    box-shadow: 0 18px 40px -28px rgba(15, 23, 42, 0.45);
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a { word-break: break-all; }

.subcopy {
    border-top: 1px solid #e2e8f0;
    margin-top: 28px;
    padding-top: 24px;
}

.subcopy p { font-size: 14px; }

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 28px 0 36px;
    text-align: center;
    width: 570px;
}

.footer p { color: #94a3b8; font-size: 12px; text-align: center; }
.footer a { color: #94a3b8; text-decoration: underline; }

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    width: 100%;
}

.table th { border-bottom: 1px solid #e2e8f0; margin: 0; padding-bottom: 8px; }
.table td { color: {{ $text }}; font-size: 15px; line-height: 18px; margin: 0; padding: 10px 0; }

.content-cell { max-width: 100vw; padding: 40px 36px; }

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 32px auto;
    padding: 0;
    text-align: center;
    width: 100%;
    float: unset;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 999px;
    color: #fff;
    display: inline-block;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {{ $accent }};
    border-bottom: 12px solid {{ $accent }};
    border-left: 28px solid {{ $accent }};
    border-right: 28px solid {{ $accent }};
    border-top: 12px solid {{ $accent }};
}

.button-green,
.button-success {
    background-color: {{ $accent }};
    border-bottom: 12px solid {{ $accent }};
    border-left: 28px solid {{ $accent }};
    border-right: 28px solid {{ $accent }};
    border-top: 12px solid {{ $accent }};
}

.button-red,
.button-error {
    background-color: #dc2626;
    border-bottom: 12px solid #dc2626;
    border-left: 28px solid #dc2626;
    border-right: 28px solid #dc2626;
    border-top: 12px solid #dc2626;
}

.panel { border-left: {{ $accent }} solid 4px; margin: 21px 0; }
.panel-content { background-color: {{ $panel }}; color: {{ $text }}; padding: 16px; }
.panel-content p { color: {{ $text }}; }
.panel-item { padding: 0; }
.panel-item p:last-of-type { margin-bottom: 0; padding-bottom: 0; }
.break-all { word-break: break-all; }
