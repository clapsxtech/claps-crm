@php
    $dialNumber = preg_replace('/[^0-9+]/', '', (string) $phone);
    // Imported Indian numbers include 91 without '+'. Use an international dial link.
    $dialNumber = preg_replace('/^91(?=[0-9]{10}$)/', '+91', $dialNumber);
@endphp
<a class="contact-link" href="tel:{{ $dialNumber }}">{{ $phone }}</a>
