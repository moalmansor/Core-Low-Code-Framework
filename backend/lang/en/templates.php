<?php

declare(strict_types=1);

// Messages shared by email templates and document templates.
return [
    'text_required' => 'Enter the text in the default language.',
    'bad_colour' => 'Choose a colour.',
    'too_many_blocks' => 'A template can have at most 100 blocks.',
    'bad_url' => 'Enter a web address starting with https:// or http://.',
    'record_link_needs_form' => 'A button that opens the record needs a template for a form.',
    'unknown_placeholder' => 'The placeholder {{ :placeholder }} does not name a value this template can show.',
    'unknown_repeater' => 'Choose a table of this form.',
    'unknown_column' => 'Choose columns of the table.',
    'condition_needs_form' => 'Conditions need a template for a form.',
    'placeholder' => [
        'record_number' => 'Record number',
        'status' => 'Status',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'link' => 'Link to the record',
        'form' => 'Form name',
        'recipient' => 'Recipient name',
        'today' => 'Today’s date',
        'app' => 'System name',
    ],
];
