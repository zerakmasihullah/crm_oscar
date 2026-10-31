{{-- Minimal clean theme: white canvas + slim brand accent --}}
@php
    $colors = \App\Support\ThemeColors::current();
    $brand = $colors['brand'] ?? '#162238';
    $accent = $colors['accent'] ?? $brand;
    $text = '#374151';
    $heading = '#111827';
@endphp
body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: #ffffff;
    color: {{ $text }};
    height: 100%;
    line-height: 1.5;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p, ul, ol, blockquote { line-height: 1.5; text-align: start; }
a { color: {{ $brand }}; }
a img { border: none; }

h1 { color: {{ $heading }}; font-size: 20px; font-weight: 650; margin-top: 0; text-align: start; }
h2 { font-size: 16px; font-weight: 650; margin-top: 0; text-align: start; }
h3 { font-size: 14px; font-weight: 650; margin-top: 0; text-align: left; }
p { font-size: 15px; line-height: 1.65em; margin-top: 0; text-align: left; }
p.sub { font-size: 12px; }
img { max-width: 100%; }

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #ffffff;
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
    border-top: 4px solid {{ $brand }};
    padding: 28px 8px 8px;
    text-align: left;
}

.header a {
    color: {{ $brand }};
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -0.01em;
    text-decoration: none;
}

.logo {
    height: 36px;
    margin: 0;
    max-height: 36px;
    width: auto;
}

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #ffffff;
    border-bottom: 1px solid #ffffff;
    border-top: 1px solid #ffffff;
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: #ffffff;
    border-color: transparent;
    border-radius: 0;
    border-width: 0;
    box-shadow: none;
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a { word-break: break-all; }

.subcopy {
    border-top: 1px solid #f3f4f6;
    margin-top: 28px;
    padding-top: 20px;
}

.subcopy p { font-size: 13px; color: #9ca3af; }

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 12px 0 28px;
    text-align: left;
    width: 570px;
}

.footer p { color: #d1d5db; font-size: 11px; text-align: left; }
.footer a { color: #9ca3af; text-decoration: underline; }

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 24px auto;
    width: 100%;
}

.table th { border-bottom: 1px solid #e5e7eb; margin: 0; padding-bottom: 8px; }
.table td { color: {{ $text }}; font-size: 14px; line-height: 18px; margin: 0; padding: 10px 0; }

.content-cell { max-width: 100vw; padding: 16px 8px 8px; }

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 28px auto;
    padding: 0;
    text-align: left;
    width: 100%;
    float: unset;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 6px;
    color: #fff;
    display: inline-block;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {{ $brand }};
    border-bottom: 10px solid {{ $brand }};
    border-left: 18px solid {{ $brand }};
    border-right: 18px solid {{ $brand }};
    border-top: 10px solid {{ $brand }};
}

.button-green,
.button-success {
    background-color: {{ $accent }};
    border-bottom: 10px solid {{ $accent }};
    border-left: 18px solid {{ $accent }};
    border-right: 18px solid {{ $accent }};
    border-top: 10px solid {{ $accent }};
}

.button-red,
.button-error {
    background-color: #dc2626;
    border-bottom: 10px solid #dc2626;
    border-left: 18px solid #dc2626;
    border-right: 18px solid #dc2626;
    border-top: 10px solid #dc2626;
}

.panel { border-left: {{ $brand }} solid 3px; margin: 21px 0; }
.panel-content { background-color: #f9fafb; color: {{ $text }}; padding: 14px; }
.panel-content p { color: {{ $text }}; }
.panel-item { padding: 0; }
.panel-item p:last-of-type { margin-bottom: 0; padding-bottom: 0; }
.break-all { word-break: break-all; }
