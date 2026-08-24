<?php

namespace App\Fixtures\Laravel13\UpdatePaginationViewNamesRector;

class PaginationConfig
{
    public function values()
    {
        return [
            'view' => 'pagination::default',
            'simple' => 'pagination::simple-default',
        ];
    }
}
