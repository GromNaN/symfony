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

A message body that holds a JSON object, as produced by the
`messenger.transport.symfony_serializer` service with the `json` format, is stored
as a native BSON sub-document instead of a string. The message fields are then
queryable and indexable:

```javascript
db.messenger_messages.find({ 'body.orderId': 1234 })
```

Any other body is stored as a string: one that does not start with an opening brace,
such as the output of the default PHP serializer, and one that fails to parse as a
JSON object.

A body stored as a sub-document is read back as Relaxed Extended JSON, so a message
written straight into the collection by another producer is handled as well.

Beware that a field named `$date`, `$oid` or `$numberInt` in the message is
interpreted as a BSON type and comes back in another shape. Use the PHP serializer
for such messages.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
