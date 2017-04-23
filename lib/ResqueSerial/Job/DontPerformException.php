<?php

namespace ResqueSerial\Job;
use Exception;

/**
 * Exception to be thrown if a job should not be performed/run.
 *
 * @package        Resque/Job
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class DontPerformException extends Exception {

}