<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

class SyncUsersJob implements ShouldQueue
{
    public Collection $users;
}

?>
-----
<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

// Laravel 13: serialized Eloquent collections now restore eager-loaded relations. Review queued-job logic that expected relations to be absent after deserialization.
class SyncUsersJob implements ShouldQueue
{
    public Collection $users;
}

?>
