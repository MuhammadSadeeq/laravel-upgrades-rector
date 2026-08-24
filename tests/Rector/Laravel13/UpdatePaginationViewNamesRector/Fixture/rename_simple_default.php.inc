<?php

namespace App\Fixtures\Laravel13\UpdatePaginationViewNamesRector;

class SimplePaginationExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::simple-default');
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdatePaginationViewNamesRector;

class SimplePaginationExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::simple-bootstrap-3');
    }
}

?>
