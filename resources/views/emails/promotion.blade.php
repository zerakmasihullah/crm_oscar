@php
    $template = $promotion->template ?? 'classic-banner';
@endphp

@if($template === 'classic-banner')
    @include('emails.templates.classic-banner')
@elseif($template === 'product-showcase')
    @include('emails.templates.product-showcase')
@elseif($template === 'product-grid')
    @include('emails.templates.product-grid')
@elseif($template === 'minimalist')
    @include('emails.templates.minimalist')
@elseif($template === 'discount-badge')
    @include('emails.templates.discount-badge')
@elseif($template === 'newsletter-style')
    @include('emails.templates.newsletter-style')
@elseif($template === 'modern-card')
    @include('emails.templates.modern-card')
@elseif($template === 'split-layout')
    @include('emails.templates.split-layout')
@elseif($template === 'full-width-image')
    @include('emails.templates.full-width-image')
@elseif($template === 'elegant')
    @include('emails.templates.elegant')
@else
    @include('emails.templates.classic-banner')
@endif
