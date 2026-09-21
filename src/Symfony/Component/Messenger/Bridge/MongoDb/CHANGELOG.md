CHANGELOG
=========

8.2
---

 * Introduce the MongoDB Messenger bridge
 * Add Change Streams to wake idle workers up as soon as a message is inserted, with the `wait_time` option (0 disables it)
 * Listen to several queues with a single server request through the `--queues` option of `messenger:consume`
