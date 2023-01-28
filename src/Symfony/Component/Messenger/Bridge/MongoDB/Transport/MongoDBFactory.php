<?php

namespace Symfony\Component\Messenger\Bridge\MongoDB\Transport;

use MongoDB\Collection;
use MongoDB\Driver\Manager;
class MongoDBFactory
{
    public function createCollection(Manager $manager, $database, $collection): Collection
    {
        return new Collection($manager, $database, $collection);
    }
}
