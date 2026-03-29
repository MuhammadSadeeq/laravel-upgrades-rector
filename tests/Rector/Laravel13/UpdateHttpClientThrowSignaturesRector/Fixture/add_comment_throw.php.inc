<?php

namespace App\Http;

use Illuminate\Http\Client\Response;

class CustomResponse extends Response
{
    public function throw()
    {
        parent::throw();
    }

    public function throwIf($condition)
    {
        parent::throwIf($condition);
    }
}

?>
-----
<?php

namespace App\Http;

use Illuminate\Http\Client\Response;

class CustomResponse extends Response
{
    // Laravel 13: The throw()/throwIf() method signatures have changed. Update: public function throw($callback = null) and public function throwIf($condition, $callback = null)
    public function throw()
    {
        parent::throw();
    }

    // Laravel 13: The throw()/throwIf() method signatures have changed. Update: public function throw($callback = null) and public function throwIf($condition, $callback = null)
    public function throwIf($condition)
    {
        parent::throwIf($condition);
    }
}

?>
