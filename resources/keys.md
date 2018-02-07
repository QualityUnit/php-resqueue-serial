resqu-v4:

    // === jobs
    unassigned: list(<job_data>)
    q:<queue_id>: list(<job_data>)
    // allocator buffer (1 element at most)
    allocator:<hostname>:<allocator_code>: list(<buffer_data>)
    job:allocation_failures: list(<job_data>)

    // === batches
    // <batch_id> == <source_id>:<job_name>:<generated_suffix>
    uncommitted:<batch_id>: list(<job_data>)
    committed: list(<batch_id>)
    committed:<batch_id>: list(<job_data>)
    pool:<pool_name>:unit_queues: sorted_set(<unit_queues_key>)
    pool:<pool_name>:<unit_id>:queues: set(<batch_key>)
    pool:<pool_name>: hash(<source_id>, <worker_id>)
    pool:<pool_name>:backlog:<source_id>: list(<batch_id>)
    batch:allocation-failures: list(<batch_id>)

    processes: set(<process_id>)
    process:<hostname>:
        // <worker_id> == <hostname>~<pool name>~<code>~<pid>
        static_pool:<pool_name>: set(<worker_id>)
        // <worker_id> == <hostname>~<pool name>~<code>~<pid>
        batch_pool:<pool_name>: set(<worker_id>)
        // <process_id> == <hostname>~<pid>
        scheduler: set(<process_id>)
        // <allocator_id> == <hostname>~<code>~<pid>
        allocator: set(<allocator_id>)

    worker:<worker_id>: list(<job_data>)

    unique:<unique_id>:
        state: {'queued', 'running'}
        deferred: job_data

    // === TODO

    delayed_queue_schedule: sorted_set(timestamp, timestamp)
    delayed:<timestamp>: list(<json_encoded JobImage>)

    plan_schedule: sorted_set(timestamp, timestamp)
    plan_schedule:<timestamp>: list(plan_id)
    plan:<id>: <json_encoded timestamp, period, queue, JobImage>