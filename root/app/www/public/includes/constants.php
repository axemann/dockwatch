<?php

/*
----------------------------------
 ------  Created: 111723   ------
 ------  Austin Best	   ------
----------------------------------
*/

define('APP_NAME', 'DockWatch');
define('APP_IMAGE', 'ghcr.io/notifiarr/dockwatch:main');
define('APP_MAINTENANCE_IMAGE', 'ghcr.io/notifiarr/dockwatch:develop');

define('ICON_REPO', 'Notifiarr/images');
define('ICON_URL', 'https://gh.notifiarr.com/images/icons/');

//-- SOCKET DEFAULTS
define('SOCKET_HOST', $_SERVER['SERVER_NAME']);
define('SOCKET_PORT', 9998);

//-- CRON FREQUENCY
define('DEFAULT_CRON', '0 0 * * *');

//-- FOLDERS
define('APP_DATA_PATH', '/config/');
define('BACKUP_PATH', APP_DATA_PATH . 'backups/');
define('LOGS_PATH', APP_DATA_PATH . 'logs/');
define('TMP_PATH', APP_DATA_PATH . 'tmp/');
define('COMPOSE_PATH', APP_DATA_PATH . 'compose/');

//-- DATA FILES
define('SERVERS_FILE', APP_DATA_PATH . 'servers.json');
define('LOGIN_FILE', APP_DATA_PATH . 'logins');
define('LOGIN_FAILURE_FILE', APP_DATA_PATH . 'login_failures');
define('SETTINGS_FILE', APP_DATA_PATH . 'settings.json');
define('STATE_FILE', APP_DATA_PATH . 'state.json');
define('PULL_FILE', APP_DATA_PATH . 'pull.json');
define('LOGO_FILE', APP_DATA_PATH . 'logos.json');
define('HEALTH_FILE', APP_DATA_PATH . 'health.json');
define('INTERNAL_ICON_ALIAS_FILE', 'container-alias.json');
define('EXTERNAL_ICON_ALIAS_FILE', APP_DATA_PATH . 'container-alias.json');
define('STATS_FILE', APP_DATA_PATH . 'stats.json');
define('DEPENDENCY_FILE', APP_DATA_PATH . 'dependencies.json');

//-- LOG FILES
define('SYSTEM_LOG', LOGS_PATH . 'system/app.log');
define('UI_LOG', LOGS_PATH . 'system/ui.log');
define('API_LOG', LOGS_PATH . 'system/api.log');
define('MAINTENANCE_LOG', LOGS_PATH . 'system/maintenance.log');
define('STARTUP_LOG', LOGS_PATH . 'system/startup.log');
define('CRON_HOUSEKEEPER_LOG', LOGS_PATH . 'crons/housekeeper.log');
define('CRON_PRUNE_LOG', LOGS_PATH . 'crons/prune.log');
define('CRON_PULLS_LOG', LOGS_PATH . 'crons/pulls.log');
define('CRON_STATE_LOG', LOGS_PATH . 'crons/state.log');
define('CRON_STATS_LOG', LOGS_PATH . 'crons/stats.log');
define('CRON_HEALTH_LOG', LOGS_PATH . 'crons/health.log');
define('LOG_ROTATE_SIZE', 2); //-- MB UNTIL ROTATE

//-- MEMCACHE
define('MEMCACHE_PREFIX', 'dockwatch-' . substr(md5($_SERVER['SERVER_NAME']), 0, 10) . '-');
define('MEMCACHE_DOCKER_STATS', 10);
define('MEMCACHE_DOCKER_PROCESS', 10);
define('MEMCACHE_DOCKER_INSPECT', 10);

//-- REGCTL
define('REGCTL_PATH', '/usr/local/bin/');
define('REGCTL_BINARY', 'regctl');

//-- AVAILABLE PAGES
$pages      = ['overview', 'containers', 'compose', 'orphans', 'notification', 'settings', 'tasks', 'commands', 'logs'];

//-- WHAT DATA TO GET WHEN VIEWING A PAGE
$getStats   = ['overview', 'containers'];
$getProc    = ['overview', 'containers', 'notifications'];
$getInspect = ['overview', 'containers'];

//-- SKIP UPDATING CONTAINERS THAT CAN BREAK THINGS
define('SKIP_OFF', 0);
define('SKIP_FORCE', 1);
define('SKIP_OPTIONAL', 2);

$skipContainerActions   = [
                            'dockwatch',    //-- IF THIS GOES DOWN, IT WILL STOP THE CONTAINER WHICH MEANS IT CAN NEVER FINISH
                            'cloudflared',  //-- IF THIS GOES DOWN, IT WILL KILL THE NETWORK TRAFFIC TO DOCKWATCH
                            'swag'          //-- IS THIS GOES DOWN, IT WILL KILL THE WEB SERVICE TO DOCKWATCH
                        ];