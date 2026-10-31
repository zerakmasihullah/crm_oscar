{{-- Formal gold theme: institutional cream + gold accent --}}
@php
    $colors = \App\Support\ThemeColors::current();
    $brand = $colors['brand'] ?? '#162238';
    $gold = $colors['secondary'] ?? '#d4a853';
    $accent = $colors['accent'] ?? $gold;
    $surface = '#f8f5ef';
    $text = '#44403c';
    $heading = $brand;
@endphp
body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: Georgia, 'Times New Roman', Times, serif;
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: {{ $surface }};
    color: {{ $text }};
    height: 100%;
    line-height: 1.55;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p, ul, ol, blockquote { line-height: 1.55; text-align: start; }
a { color: {{ $brand }}; }
a img { border: none; }

h1 {
    color: {{ $heading }};
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.01em;
    margin-top: 0;
    text-align: center;
}

h2 { font-size: 16px; font-weight: 700; margin-top: 0; text-align: start; }
h3 { font-size: 14px; font-weight: 700; margin-top: 0; text-align: left; }
p { font-size: 16px; line-height: 1.7em; margin-top: 0; text-align: left; }
p.sub { font-size: 12px; }
img { max-width: 100%; }

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $surface }};
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
    border-bottom: 3px solid {{ $gold }};
    padding: 30px 24px 22px;
    text-align: center;
}

.header a {
    color: {{ $brand }};
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-decoration: none;
    text-transform: uppercase;
}

.logo {
    height: 52px;
    margin: 0;
    max-height: 52px;
    width: auto;
}

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $surface }};
    border-bottom: 1px solid {{ $surface }};
    border-top: 1px solid {{ $surface }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: #ffffff;
    border-color: #e7e5e4;
    border-radius: 2px;
    border-width: 1px;
    box-shadow: none;
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a { word-break: break-all; }

.subcopy {
    border-top: 1px solid #e7e5e4;
    margin-top: 28px;
    padding-top: 24px;
}

.subcopy p { font-size: 13px; }

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 24px 0 32px;
    text-align: center;
    width: 570px;
}

.footer p { color: #a8a29e; font-size: 12px; text-align: center; }
.footer a { color: #a8a29e; text-decoration: underline; }

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    width: 100%;
}

.table th { border-bottom: 1px solid {{ $gold }}; margin: 0; padding-bottom: 8px; }
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
    border-radius: 2px;
    color: #fff;
    display: inline-block;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {{ $brand }};
    border-bottom: 10px solid {{ $brand }};
    border-left: 22px solid {{ $brand }};
    border-right: 22px solid {{ $brand }};
    border-top: 10px solid {{ $brand }};
}

.button-green,
.button-success {
    background-color: {{ $accent }};
    border-bottom: 10px solid {{ $accent }};
    border-left: 22px solid {{ $accent }};
    border-right: 22px solid {{ $accent }};
    border-top: 10px solid {{ $accent }};
}

.button-red,
.button-error {
    background-color: #dc2626;
    border-bottom: 10px solid #dc2626;
    border-left: 22px solid #dc2626;
    border-right: 22px solid #dc2626;
    border-top: 10px solid #dc2626;
}

.panel { border-left: {{ $gold }} solid 4px; margin: 21px 0; }
.panel-content { background-color: {{ $surface }}; color: {{ $text }}; padding: 16px; }
.panel-content p { color: {{ $text }}; }
.panel-item { padding: 0; }
.panel-item p:last-of-type { margin-bottom: 0; padding-bottom: 0; }
.break-all { word-break: break-all; }
