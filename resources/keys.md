resque-v3:
    
    queues: set(queue_name)
    queue:<queue_name>: list(job_data)
        temp:<time>: list(job_data)              # temporary queue created by removeItems()
        temp:<time>:requeue: list(job_data)      # temporary queue created by removeItems()
    
    failed: list(fail_data)
    retries: list(fail_data)
    job:<job_id>:status: <status_data>
    
    queuestat:<hostname>:
        dequeued:<queue_name>: <dequeued_count>
        failed:<queue_name>: <failed_count>
        processed:<queue_name>: <processed_count>
        processing_time:<queue_name>: <time_spent_processing_ms>
        queue_time:<queue_name>: <time_spent_queued_ms>
        retries:<queue_name>: <retry_count>
        
    
    stat:
        failed: <failed_count>
        processed: <processed_count>
        retries: <retry_count>
    
    scheduler_pid:<hostname>: <pid>
    
    delayed:<timestamp>: list(<json_encoded JobImage>)
    delayed_queue_schedule: sorted_set(timestamp, timestamp)
    
    plan_schedule: sorted_set(timestamp, timestamp)
    plan_schedule:<timestamp>: list(plan_id)
    plan:<id>: <json_encoded timestamp, period, queue, JobImage>
    
    unique:<unique_id>:
        state: {'queued', 'running'}
        deferred: job_data
    
    queuedata:<queue>:lock: <lock_value>
    workers: set(worker_id)                                     # standard workers
    worker:<worker_id>: <job_run_data>
        started: <time_started>
        
resqu-v4:
    
    // === static
    unassigned: set(<source_id>:<job_name>)
    assigned: set(<source_id>:<job_name>)
    queue:<source_id>:<job_name>: list(job_data)
    q:<queue_id>: list(job_data)
    
    // === mass actions (managed)
    uncommited:<source_id>:<job_name>:<generated_suffix>: list(job_data)
    commited: set(<source_id>:<job_name>:<generated_suffix>)
    commited:<source_id>:<job_name>:<generated_suffix>: list(job_data)
    // <mass_queue_id> == <source_id>:<job_name>:<generated_suffix>
    mass:<pool_name>:<unit_id>:queues: set(<mass_queue_id>)
    mass:<pool_name>: hash(<source_id>, <worker_id>)
    
    
    src:<source_id>:cfg:<job_name>: <pool_name>