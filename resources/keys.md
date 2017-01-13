resque:
    
    queues: set(queue_name)
    queue:<queue_name>: list(job_data)
    queue:<queue_name>:temp:<time>: list(job_data)              # temporary queue created by removeItems()
    queue:<queue_name>:temp:<time>:requeue: list(job_data)      # temporary queue created by removeItems()
    
    failed: list(fail_data)
    job:<job_id>:status: <status_data>
    
    workers: set(worker_id)
    worker:<worker_id>: <job_run_data>
    worker:<worker_id>:started: <time_started>
    
    stat:failed: <failed_count>
    stat:failed:<worker_id>: <failed_count>
    stat:processed: <processed_count>
    stat:processed:<worker_id>: <processed_count>
    
resque:serial:
    
    workers: set(worker_id)                                     # Reque-Serial workers
    worker:<worker_id>: <job_run_data>
    worker:<worker_id>:started: <time_started>
    worker:<worker_id>:serial_workers: set(serial_worker_id)    # Serial job workers
    
    serial_workers: set(serial_worker_id)                       # Serial job workers
    serial_worker:<serial_worker_id>: <job_run_data>
    serial_worker:<serial_worker_id>:parent: <worker_id>
    serial_worker:<serial_worker_id>:runners: set(serial_runner_id)
    serial_worker:<serial_worker_id>:started: <time_started>
    
    queue:<queue_name>: list(serial_job_data)
    queue:<queue>:config: <queue_config>
    queue:<queue>:lock: <lock_value>
    queue:<queue>:completed_count: <completed_count>

serial_worker_id: <hostname>:<pid>:<queue>:<queue_count>:<queue_num>