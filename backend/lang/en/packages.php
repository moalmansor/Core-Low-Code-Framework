<?php

declare(strict_types=1);

return [
    'ambiguous' => 'This package cannot be imported as it stands: some objects changed both here and in the package, have unpublished changes here, or their tables here differ from their design. Resolve those here first, then compare again.',
    'bad_key' => 'Choose a new key for :name that is not in use (lowercase letters, digits and underscores).',
    'bad_object' => 'The package contains an object it cannot read.',
    'change_note' => 'Imported from a configuration package.',
    'checksum_mismatch' => 'The file was changed after it was exported: its checksum does not match.',
    'choose' => 'Choose what to do with :name.',
    'form_invalid' => 'The design of :form in the package is not valid here: :detail',
    'form_key_differs' => ':form exists here under another key; a form keeps its key.',
    'form_not_exportable' => ':form has changes that are not published. Publish or discard them first: a package carries what is live.',
    'key_taken' => 'Another object here already uses the key of :name: import it under another key.',
    'missing_application' => 'The application of this form is neither here nor in the package.',
    'missing_dependency' => ':name needs something that is neither here nor in the package.',
    'missing_form' => 'The form of this download profile is neither here nor in the package.',
    'not_a_package' => 'This file is not a configuration package.',
    'not_applicable' => 'This package cannot be applied now.',
    'publish_blocked' => ':form could not be published: :reason',
    'publish_failed' => ':form could not be published; nothing after it was applied.',
    'rename_new_only' => ':name already exists here: it can be taken from the package or kept, not renamed.',
    'skipped_subject' => 'A grant or access rule of :form names a :what that does not exist here; it was left out.',
    'too_large' => 'The package is larger than 20 MB.',
    'unknown_object' => 'An object chosen for the package no longer exists.',
    'unpublished_changes' => 'Has unpublished changes',
    'dropped_numbering' => 'The numbering sequence of :form does not exist here; the form was imported without it.',
    'dropped_calendar' => 'The business calendar of :form does not exist here; the form was imported without it.',
    'subject' => ['role' => 'role', 'department' => 'department', 'user' => 'person', 'everyone' => 'everyone'],
];
