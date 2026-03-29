<?php

class OtherViewExample
{
    public function render()
    {
        $paginator = new stdClass();
        $paginator->links('pagination::tailwind');
        $paginator->links('pagination::bootstrap-4');
        $paginator->links('pagination::bootstrap-5');
        $paginator->links('pagination::simple-tailwind');
        $paginator->links('custom.pagination');
    }
}

?>
