MongoDB Messenger
=================

Provides MongoDB integration for Symfony Messenger.

DSN example
-----------

```
MESSENGER_TRANSPORT_DSN=mongodb://user:pass@mongodb1.example.com:27017/db_name?collection_name=messenger_messages&queue_name=default
```

The transport reads its own settings (`database`, `collection_name`, `queue_name`,
`redeliver_timeout`, `wait_time`) from the query string and passes any
other parameter to the MongoDB driver, so
[connection options](https://www.mongodb.com/docs/manual/reference/connection-string-options/)
keep working:

```
MESSENGER_TRANSPORT_DSN=mongodb+srv://mongodb.example.com/db_name?replicaSet=repl&connectTimeoutMS=3000
```

Change streams
--------------

The transport uses a MongoDB [change stream](https://www.mongodb.com/docs/manual/changeStreams/)
only to be notified as soon as a message is inserted, reducing the latency of
fresh messages versus polling for the whole `--sleep` period. The trade-off:
delayed (`DelayStamp`) and redelivered messages fire no event, so they wait for
the poll claim and can be delayed by up to `wait_time`.

Change streams are best effort: they require a replica set or a sharded cluster
and fall back to polling otherwise. A fresh stream is opened per wait cycle and
dropped once a message is claimed or the budget is spent, so no cursor or
session stays open. `wait_time` (default `1`, `0` disables change streams)
bounds each wait and is capped at `300` seconds so delayed and redelivered
messages are never starved.

Listening to several queues
---------------------------

All queues live in the same collection, discriminated by the `queueName` field.
A worker can therefore listen to several queues with a single server request,
instead of one request per queue:

```
messenger:consume <receiver> --queues=foo --queues=bar
```

The transport claims the oldest available message across all the listed queues
with one `findOneAndUpdate` and listens on a single change stream that covers
every queue. Queues are served in FIFO order (sorted by `availableAt`); there is
no priority between queues.

Declare one transport per queue for sending (so the routing can target each
queue), and consume them all from the same receiver:

```yaml
framework:
    messenger:
        transports:
            foo: 'mongodb://host/db?queue_name=foo'
            bar: 'mongodb://host/db?queue_name=bar'
        routing:
            App\FooMessage: foo
            App\BarMessage: bar
```

Then one worker drains both queues with one request:

```
messenger:consume foo --queues=foo --queues=bar
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
