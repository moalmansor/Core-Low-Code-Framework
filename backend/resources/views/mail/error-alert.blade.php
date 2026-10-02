<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr' }}">
<body style="font-family: Arial, sans-serif;">
<h2>{{ __('ui.mail.error_alert.heading') }}</h2>
<p>{{ __('ui.mail.error_alert.reference') }}: <strong>{{ $reference }}</strong></p>
<p>{{ __('ui.mail.error_alert.class') }}: {{ $group->exception_class }}</p>
<p>{{ __('ui.mail.error_alert.severity') }}: {{ $group->severity }} · {{ __('ui.mail.error_alert.occurrences') }}: {{ $group->occurrences }}</p>
<p><a href="{{ $url }}">{{ __('ui.mail.error_alert.open') }}</a></p>
</body>
</html>
