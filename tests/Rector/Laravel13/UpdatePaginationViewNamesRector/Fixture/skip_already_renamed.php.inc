<?php

namespace App\Fixtures\Laravel13\UpdatePaginationViewNamesRector;

class AlreadyRenamedExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::bootstrap-3');
        $paginator->links('pagination::simple-bootstrap-3');
    }
}

?>
