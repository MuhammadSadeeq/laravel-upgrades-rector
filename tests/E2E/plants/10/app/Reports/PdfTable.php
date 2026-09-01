<?php

namespace App\Reports;

/** A non-Blueprint receiver that must not be rewritten by migration rules. */
final class PdfTable
{
    public function addColumn(): void
    {
        $table = new self;
        $table->float('x', 8, 2);
    }
}
