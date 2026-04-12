<?php

namespace App\Http;

use Illuminate\Http\Client\Response;

class AlreadyCommentedResponse extends Response
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
-----
<?php

namespace App\Http;

use Illuminate\Http\Client\Response;

class AlreadyCommentedResponse extends Response
{
    public function throw($callback = null)
    {
        parent::throw($callback);
    }

    public function throwIf($condition, $callback = null)
    {
        parent::throwIf($condition, $callback);
    }
}

?>
