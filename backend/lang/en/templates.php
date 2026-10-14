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
    'unknown_link' => 'Choose a form that links to this one, and the field it links with.',
    'columns_required' => 'Choose at least one column.',
    'signatories_count' => 'Add one to four people who sign.',
    'code_needs_form' => 'A QR code or barcode needs a template for a form.',
    'bad_format' => 'Choose a format from the list: a date style, decimal places, a currency or digits.',
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
        'created_by' => 'Created by',
        'updated_by' => 'Last changed by',
        'assignee' => 'Assigned to',
        'user' => 'Person producing it',
        'now' => 'Date and time now',
    ],
];
