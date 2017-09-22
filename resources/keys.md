resque-v2:
    
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
    
    unique_list: hash(uniqueId, jobId)
    
    serial:
    
        link:<serial_queue_name>: null # key presence signifies existence of a link job for the serial queue
    
        workers: set(worker_id)                                     # standard workers
        worker:<worker_id>: <job_run_data>
            started: <time_started>
            serial_workers: set(serial_worker_id)    # Serial job workers
        
        serial_workers: set(serial_worker_id)                       # Serial job workers
        serial_worker:<serial_worker_id>: <job_run_data>
            parent: <worker_id>
            runners: set(serial_runner_id)
            started: <time_started>
        
        queue:<serial_queue_name>: list(serial_job_data)
        queuedata:<queue>:
            config: <queue_config>
            lock: <lock_value>
            completed_count: <completed_count>

serial_worker_id: <hostname>:<pid>:<queue>:<queue_count>:<queue_num>