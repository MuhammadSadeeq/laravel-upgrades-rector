<?php // @loads

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
<?php // @loads

namespace App\Http;

use Illuminate\Http\Client\Response;

class CustomResponse extends Response
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
