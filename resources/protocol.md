STATIC POOL
- constant number of workers
- all jobs in one queue
- FIFO queue

MANAGED POOL
- many closed queues
- worker units (X workers in a unit)
- each unit works on N queues
- pool has fixed maximum units
- each unit can work on infinite number of queues

- each unit has assigned set of queues to work on
- worker cycles through all assigned queues then updates the list after each cycle
-- blpop to dequeue, in manageable chunks from assigned queues
