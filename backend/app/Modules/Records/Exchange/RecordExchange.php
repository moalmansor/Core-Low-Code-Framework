<?php

declare(strict_types=1);

namespace App\Modules\Records\Exchange;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * The import template: an empty workbook with the columns the user may
 * fill. Imports themselves run as background jobs (ImportService), exports
 * through ExportService.
 */
final class RecordExchange
{
    public function __construct(
        private readonly SheetCodec $codec,
        private readonly FieldAccessResolver $fieldAccess,
    ) {}

    /** An empty workbook with the importable columns the user may edit; returns its path. */
    public function template(FormRuntime $rt, User $user): string
    {
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'create')['fields'];
        $headers = [__('records.export.columns.id'), __('records.export.columns.version')];
        foreach ($rt->mainFields() as $f) {
            if ($this->codec->importable($rt, $f) && ($levels[$f['uuid']] ?? 'editable') === 'editable') {
                $headers[] = $this->codec->header($f);
            }
        }
        $path = (string) tempnam(sys_get_temp_dir(), 'lcf-template-');
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(new Row(array_map(static fn (string $h) => new StringCell($h, null), $headers), (new Style)->setFontBold()));
        $writer->close();

        return $path;
    }
}
