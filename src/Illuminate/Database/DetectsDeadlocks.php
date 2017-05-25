<?php

namespace Illuminate\Database;

use Exception;
use Illuminate\Support\Str;

trait DetectsDeadlocks
{
    /*
     * 判断给定的异常是否由死锁引起
     *
     * @param  \Exception  $e
     * @return bool
     */
    protected function causedByDeadlock(Exception $e)
    {
        $message = $e->getMessage();

        return Str::contains($message, [
            'Deadlock found when trying to get lock',
            'deadlock detected',
            'The database file is locked',
            'database is locked',
            'database table is locked',
            'A table in the database is locked',
            'has been chosen as the deadlock victim',
        ]);
    }
}
