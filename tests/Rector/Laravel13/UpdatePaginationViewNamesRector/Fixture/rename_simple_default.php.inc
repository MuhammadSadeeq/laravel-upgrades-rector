<?php

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

class SimplePaginationExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::simple-bootstrap-3');
    }
}

?>
