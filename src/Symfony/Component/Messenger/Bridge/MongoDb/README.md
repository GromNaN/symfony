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

The transport listens on a MongoDB [change stream](https://www.mongodb.com/docs/manual/changeStreams/)
to wake workers up as soon as a message is inserted, instead of sleeping for the
whole worker `--sleep` period. The stream is opened once per consumer and stays
open for the whole worker session, between messages and between batches. The
atomic claim on the message remains the source of truth, so delayed messages
(`DelayStamp`), redelivered messages and multi-consumer semantics are unchanged.

Change streams are best effort: they require a replica set or a sharded cluster,
and the transport falls back to polling whenever they cannot be used. Setting
`wait_time` to `0` or less disables them entirely, so the transport always
polls.

`wait_time` (default `1`) is the blocking wait in seconds inside the receiver
for each worker cycle. Keep it at or below the messenger `--sleep` option
(default `1` second): the worker subtracts the time spent waiting from its own
sleep, so a value close to `sleep` gives the lowest latency for newly inserted
messages. It is capped at `300` seconds: delayed and redelivered messages never
fire a stream event, so they are only picked up by the poll claim that runs once
per cycle, and a huge wait_time would starve them.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
