<?php

namespace App\Http;

use Illuminate\Http\Client\Response;

class IncompatibleResponse extends Response
{
    public function throw($handler)
    {
        parent::throw($handler);
    }

    public function throwIf($when)
    {
        parent::throwIf($when);
    }
}

?>
-----
<?php

namespace App\Http;

use Illuminate\Http\Client\Response;

class IncompatibleResponse extends Response
{
    // TODO: Laravel 13 changed this signature to accept a nullable callback; the override does not match positionally — reconcile it manually.
    public function throw($handler)
    {
        parent::throw($handler);
    }

    // TODO: Laravel 13 changed this signature to accept a nullable callback; the override does not match positionally — reconcile it manually.
    public function throwIf($when)
    {
        parent::throwIf($when);
    }
}

?>
