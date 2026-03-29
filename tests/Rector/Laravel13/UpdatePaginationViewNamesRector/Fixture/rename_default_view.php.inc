<?php

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

class PaginationExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::bootstrap-3');
    }
}

?>
