<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MySQLStore;

/**
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @requires extension pdo_mysql
 * @group integration
 */
class MySQLStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    public function getStore(): PersistingStoreInterface
    {
        if (!getenv('MYSQL_HOST')) {
            $this->markTestSkipped('Missing MYSQL_HOST env variable');
        }

        return new MySQLStore('mysql:host='.getenv('MYSQL_HOST').';port=33102', ['db_username' => 'root', 'db_password' => 'jugakwj3wjnhhsrh4e1ygd4gk']);
    }

    /**
     * @requires extension pdo_sqlite
     */
    public function testInvalidDriver()
    {
        $store = new MySQLStore('sqlite:/tmp/foo.db');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The adapter "Symfony\Component\Lock\Store\MySQLStore" does not support');
        $store->exists(new Key('foo'));
    }
}
