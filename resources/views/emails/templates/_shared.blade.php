@php
    $brandName = "Doner House";
    $siteUrl = config('app.url', 'https://www.oscarlimerick.com');
    $unsubscribeUrl = $unsubscribeUrl ?? ($recipientEmail ? \App\Http\Controllers\MarketingUnsubscribeController::signedUrl($recipientEmail) : $siteUrl);
@endphp
<div class="footer">
    <div class="footer-brand">{{ $brandName }}</div>
    <p class="footer-text">Pizza & kebab · John's Street, Limerick</p>
    <p class="footer-text">
        <a href="{{ $siteUrl }}" class="footer-link">Order online</a>
    </p>
    <div class="unsubscribe">
        <p class="unsubscribe-text">
            You’re receiving this because you ordered with us or signed up for offers.
            <br>
            <a href="{{ $unsubscribeUrl }}" class="unsubscribe-link">Unsubscribe</a>
        </p>
    </div>
</div>
@if (!empty($openPixelUrl))
<img src="{{ $openPixelUrl }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" />
@endif
