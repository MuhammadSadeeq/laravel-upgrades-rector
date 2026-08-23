<?php

namespace App\Reports;

final class PdfDiff
{
    public function gap($start, $end): int
    {
        // Receiver is not a Carbon type — never transform on a guess
        return $end->diffInDays($start);
    }
}

?>
