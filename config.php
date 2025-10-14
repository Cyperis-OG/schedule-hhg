<?php
// config.php
// Central configuration for Schedule NG.

// Path to the shared job scheduler configuration outside public_html.
const JOB_SCHEDULER_PATH = '/home/freeman/hhg_scheduler.php';
require_once JOB_SCHEDULER_PATH;

// Absolute path to the schedule directory.
const SCHEDULE_DIR = __DIR__ . '/';

// Human-readable schedule name.
const SCHEDULE_NAME = 'HHG Schedule';

// Base path for this schedule within the web server.
// Used to construct URLs like "{BASE_PATH}/api/...".
const BASE_PATH = '/095/schedule-hhg';

// Absolute base URL for emails and external links. Override via SCHEDULE_BASE_URL env var.
define('BASE_URL', rtrim(getenv('SCHEDULE_BASE_URL') ?: 'https://armstrong-scheduler.com/095/schedule-hhg', '/'));

?>