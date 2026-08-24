<?php

namespace App\Fixtures\Laravel13\UpdatePaginationViewNamesRector;

class PaginationExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::default');
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdatePaginationViewNamesRector;

class PaginationExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::bootstrap-3');
    }
}

?>
