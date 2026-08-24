<?php

namespace App\Fixtures\Laravel11\UpdateQueueAfterCommitRector;

return [
    'driver' => 'sync',
    'after_commit' => true,
];

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateQueueAfterCommitRector;

return [
    'driver' => 'sync',
    // Laravel 11: sync queue jobs now respect after_commit. Review any sync jobs dispatched inside database transactions.
    'after_commit' => true,
];

?>
