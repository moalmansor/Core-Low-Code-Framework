@php
    $locale = app()->getLocale();
    try {
        $direction = app(\App\Modules\Core\I18n\Translator::class)->localeTable()[$locale]['direction'] ?? 'ltr';
    } catch (\Throwable) {
        $direction = 'ltr';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/api/v1/branding/favicon">
    @vite(['src/main.ts'])
</head>
<body>
    <div id="app"></div>
    <noscript>JavaScript is required. / يلزم تفعيل JavaScript.</noscript>
</body>
</html>
