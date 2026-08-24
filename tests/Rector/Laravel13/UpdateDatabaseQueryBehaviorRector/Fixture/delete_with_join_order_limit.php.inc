<?php

namespace App\Fixtures\Laravel13\UpdateDatabaseQueryBehaviorRector;

use Illuminate\Database\Query\Builder;

function deleteRows(Builder $query)
{
    $query->join('users', 'users.id', '=', 'posts.user_id')->orderBy('posts.id')->limit(5)->delete();
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateDatabaseQueryBehaviorRector;

use Illuminate\Database\Query\Builder;

function deleteRows(Builder $query)
{
    // @laravel-upgrade db-query-behavior MySQL joined delete queries now compile ORDER BY / LIMIT clauses. Review this delete() call if it targets MySQL or MariaDB.
    $query->join('users', 'users.id', '=', 'posts.user_id')->orderBy('posts.id')->limit(5)->delete();
}

?>
