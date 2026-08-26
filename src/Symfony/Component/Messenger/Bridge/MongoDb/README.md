MongoDB Messenger
=================

Provides MongoDB integration for Symfony Messenger.

DSN example
-----------

```
MESSENGER_TRANSPORT_DSN=mongodb://user:pass@mongodb1.example.com:27017/db_name?collection_name=messenger_messages&queue_name=default
```

The transport reads its own settings (`database`, `collection_name`, `queue_name`,
`redeliver_timeout`) from the query string and passes any other parameter to the
MongoDB driver, so [connection options](https://www.mongodb.com/docs/manual/reference/connection-string-options/)
keep working:

```
MESSENGER_TRANSPORT_DSN=mongodb+srv://mongodb.example.com/db_name?replicaSet=repl&connectTimeoutMS=3000
```

Message body
------------

The Messenger serializer produces a JSON body with this configuration:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        serializer:
            default_serializer: messenger.transport.symfony_serializer
            symfony_serializer:
                format: json

        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
```

A body holding a JSON object is stored as a native BSON sub-document, so the message
fields are queryable and indexable:

```php
$collection = $client->getCollection('db_name', 'messenger_messages');
$pending = $collection->find(['body.orderId' => 1234]);
```

Any other body, such as the output of the PHP serializer, is stored as a string.

The document is read back as Relaxed Extended JSON, so a nested `$date` or
`$number*` value comes back in another shape: `{"count":{"$numberInt":"7"}}` becomes
`{"count":7}`.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
