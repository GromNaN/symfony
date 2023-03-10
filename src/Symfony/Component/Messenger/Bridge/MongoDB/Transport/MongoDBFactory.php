<?php

namespace Symfony\Component\Messenger\Bridge\MongoDB\Transport;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Manager;

/**
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class MongoDBFactory
{
    public function createCollection(Client $manager, $database, $collection): Collection
    {
        return new Collection($manager, $database, $collection);
    }
}
