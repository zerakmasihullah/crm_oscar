{{-- Brand-aware classic theme: strong header bar + navy actions --}}
@php
    $colors = \App\Support\ThemeColors::current();
    $brand = $colors['brand'] ?? '#162238';
    $accent = $colors['accent'] ?? $brand;
    $panel = $colors['backgroundLight'] ?? '#f1f4f9';
    $text = '#334155';
    $heading = '#0f172a';
@endphp
/* Base */

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
    line-height: 1.5;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p, ul, ol, blockquote {
    line-height: 1.5;
    text-align: start;
}

a {
    color: {{ $brand }};
}

a img {
    border: none;
}

h1 {
    color: {{ $heading }};
    font-size: 20px;
    font-weight: 700;
    margin-top: 0;
    text-align: start;
}

h2 {
    font-size: 16px;
    font-weight: 700;
    margin-top: 0;
    text-align: start;
}

h3 {
    font-size: 14px;
    font-weight: 700;
    margin-top: 0;
    text-align: left;
}

p {
    font-size: 16px;
    line-height: 1.6em;
    margin-top: 0;
    text-align: left;
}

p.sub {
    font-size: 12px;
}

img {
    max-width: 100%;
}

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
    background-color: {{ $brand }};
    padding: 28px 24px;
    text-align: center;
}

.header a {
    color: #ffffff;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.02em;
    text-decoration: none;
}

.logo {
    height: 48px;
    margin-top: 0;
    margin-bottom: 0;
    max-height: 48px;
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
    border-color: transparent;
    border-radius: 12px;
    border-width: 0;
    box-shadow: 0 10px 30px -18px rgba(15, 23, 42, 0.35);
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a {
    word-break: break-all;
}

.subcopy {
    border-top: 1px solid #e2e8f0;
    margin-top: 25px;
    padding-top: 25px;
}

.subcopy p {
    font-size: 14px;
}

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 24px 0 32px;
    text-align: center;
    width: 570px;
}

.footer p {
    color: #94a3b8;
    font-size: 12px;
    text-align: center;
}

.footer a {
    color: #94a3b8;
    text-decoration: underline;
}

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid #e2e8f0;
    margin: 0;
    padding-bottom: 8px;
}

.table td {
    color: {{ $text }};
    font-size: 15px;
    line-height: 18px;
    margin: 0;
    padding: 10px 0;
}

.content-cell {
    max-width: 100vw;
    padding: 36px 32px;
}

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    padding: 0;
    text-align: center;
    width: 100%;
    float: unset;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 8px;
    color: #fff;
    display: inline-block;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {{ $brand }};
    border-bottom: 10px solid {{ $brand }};
    border-left: 20px solid {{ $brand }};
    border-right: 20px solid {{ $brand }};
    border-top: 10px solid {{ $brand }};
}

.button-green,
.button-success {
    background-color: {{ $accent }};
    border-bottom: 10px solid {{ $accent }};
    border-left: 20px solid {{ $accent }};
    border-right: 20px solid {{ $accent }};
    border-top: 10px solid {{ $accent }};
}

.button-red,
.button-error {
    background-color: #dc2626;
    border-bottom: 10px solid #dc2626;
    border-left: 20px solid #dc2626;
    border-right: 20px solid #dc2626;
    border-top: 10px solid #dc2626;
}

.panel {
    border-left: {{ $brand }} solid 4px;
    margin: 21px 0;
}

.panel-content {
    background-color: {{ $panel }};
    color: {{ $text }};
    padding: 16px;
}

.panel-content p {
    color: {{ $text }};
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

.break-all {
    word-break: break-all;
}
