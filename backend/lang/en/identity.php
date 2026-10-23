<?php

declare(strict_types=1);

// Access policies, trusted devices and impersonation (specification §4.36).
return [
    'policy' => 'Access policy',
    'bad_key' => 'Use 2 to 48 lowercase letters, digits and underscores, starting with a letter.',
    'key_taken' => 'Another access policy uses this key.',
    'name_required' => 'Enter a name in at least one language.',
    'bad_cidr' => '":value" is not an address or address range, such as 10.0.0.0/8 or 2001:db8::/32.',
    'too_many' => 'Use at most :max entries.',
    'bad_days' => 'Choose at least one day.',
    'bad_times' => 'Enter a start and an end time as hours and minutes, and make them different.',
    'bad_timezone' => 'Choose a time zone.',
    'bad_sessions' => 'Allow between 1 and 100 sessions at once, or leave it empty for no limit.',
    'bad_idle' => 'End idle sessions after 5 to 1,440 minutes.',
    'bad_absolute' => 'End every session after 15 to 43,200 minutes, and no sooner than the idle limit.',
    'changed_meanwhile' => 'Someone else changed this policy meanwhile. Reload it and make your change again.',
    'locks_you_out' => 'This would sign you out: :reason Adjust the policy so it still allows you.',
    'denied_ip' => 'Your access policy does not allow sign-in from this network.',
    'denied_time' => 'Your access policy does not allow sign-in at this time.',
    'denied_device' => 'Your access policy allows only trusted devices. Sign in again and confirm with your second factor to trust this device.',
];
