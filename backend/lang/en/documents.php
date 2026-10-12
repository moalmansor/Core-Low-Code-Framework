<?php

declare(strict_types=1);

return [
    'unknown_form' => 'This form does not exist.',
    'key_taken' => 'Another template already uses this key.',
    'type_fixed' => 'A template keeps the kind it was created with. Create a new template instead.',
    'formats_required' => 'Choose at least one format.',
    'converter_missing' => 'PDF from a Word template needs the document converter (LibreOffice), which this installation does not have. Choose Word only, or design the template instead.',
    'bad_margin' => 'Enter a margin from 0 to 60 mm.',
    'blocks_required' => 'Add at least one block.',
    'file_required' => 'Upload the Word file for the default language.',
    'file_missing' => 'Upload this Word file again.',
    'in_use' => 'Notification rules or actions use this template. Remove it from them first.',
    'changed' => 'Someone else changed this template. Reload to see their changes.',
    'generate_failed' => 'The document could not be generated. The details are in Error Monitoring.',
    'placeholder_gone' => 'The placeholder {{ :placeholder }} no longer names a value of the form; it prints nothing.',
    'problem' => [
        'not_docx' => 'This is not a Word document (.docx).',
        'bad_xml' => 'This Word document is damaged or uses features that cannot be read.',
        'too_large' => 'This Word document is too large once unpacked.',
        'macros' => 'This Word document contains macros. Save it as a .docx without macros.',
        'embedded_objects' => 'This Word document contains embedded objects. Remove them and upload it again.',
        'external_content' => 'This Word document links to content outside itself. Remove linked pictures and imported parts.',
        'external_field' => 'This Word document has fields that read outside files (INCLUDETEXT, INCLUDEPICTURE, LINK or DDE). Remove them.',
    ],
];
