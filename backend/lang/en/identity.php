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
    'already_impersonating' => 'Stop the current impersonation before starting another.',
    'not_yourself' => 'You cannot impersonate yourself.',
    'target_inactive' => 'Only active accounts can be impersonated.',
    'target_super_admin' => 'Only a Super Admin can impersonate a Super Admin.',
    'bad_minutes' => 'Choose between 5 and :max minutes.',
    'impersonation_ended' => 'The impersonation has ended. You are signed in as yourself again.',
    'impersonation_expired' => 'The impersonation reached its time limit and has ended. You are signed in as yourself again.',
    'impersonation_blocked' => 'This is not allowed while impersonating someone.',
    'preference_locked' => 'Your organization sets this for everyone.',
    'too_many_pins' => 'You can pin up to :max records. Unpin one first.',
    'shortcut_form' => 'Choose a form you can open.',
];
