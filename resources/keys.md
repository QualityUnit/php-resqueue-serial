resqu-v4:

    // === jobs
    unassigned: list(<job_data>)
    static:queue:<pool_name>: list(<job_data>)
    // allocator buffer (1 element at most)
    allocator:<node_id>:<allocator_code>: list(<buffer_data>)
    job:allocation_failures: list(<job_data>)

    // === batches
    // <batch_id> == <source_id>:<job_name>:<generated_suffix>
    uncommitted:<batch_id>: list(<job_data>)
    committed: list(<batch_id>)
    committed:<batch_id>: list(<job_data>)
    pool:<pool_name>: hash(<source_id>, <node_id>)
        unit_queues: sorted_set(<unit_queues_key>)
        <unit_id>:queues: list(<batch_id>)
        backlog:<source_id>: list(<batch_id>)
    batch:allocation-failures: list(<batch_id>)

    processes: set(<process_id>)
    process:<node_id>:
        // <worker_id> == <node_id>~<pool name>~<code>~<pid>
        pool:<pool_name>: set(<worker_id>)
        // <process_id> == <node_id>~<pid>
        scheduler: set(<process_id>)
        // <allocator_id> == <node_id>~<code>~<pid>
        allocator: set(<allocator_id>)

    worker:<worker_id>: list(<job_data>)

    unique:<unique_id>:
        state: {'queued', 'running'}
        deferred: job_data
        
    plan_schedule: sorted_set(timestamp, timestamp)
    plan_schedule:<timestamp>: list(plan_id)
    plan_list:<source_id>: set(<plan_id>)
    plan:<id>: <json_encoded timestamp, period, source_id, Job>
    
    delayed_queue_schedule: sorted_set(timestamp, timestamp)
    delayed:<timestamp>: list(<json_encoded Job>)
