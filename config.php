<?php
// config.php
// Central configuration for Schedule NG.

// Path to the shared job scheduler configuration outside public_html.
const JOB_SCHEDULER_PATH = '/home/freeman/job_scheduler.php';
require_once JOB_SCHEDULER_PATH;

// Absolute path to the schedule directory.
const SCHEDULE_DIR = __DIR__ . '/';

// Human-readable schedule name.
const SCHEDULE_NAME = 'Commercial Schedule';

?>