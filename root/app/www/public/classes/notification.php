<?php

/*
----------------------------------
 ------  Created: 111723   ------
 ------  Austin Best	   ------
----------------------------------
*/


//-- BRING IN THE TRAITS
$traits     = ABSOLUTE_PATH . 'classes/traits/notification/';
$traitsDir  = opendir($traits);
while ($traitFile = readdir($traitsDir)) {
    if (strpos($traitFile, '.php') !== false) {
        require $traits . $traitFile;
    }
}
closedir($traitsDir);

class Notifications
{
    use Notifiarr;

    protected $platforms;
    protected $platformSettings;
    protected $headers;
    protected $logpath;
    public function __construct()
    {
        global $platforms;

        $settings = getFile(SETTINGS_FILE);

        $this->platforms        = $platforms; //-- includes/platforms.php
        $this->platformSettings = $settings['notifications']['platforms'];
        $this->logpath          = LOGS_PATH . 'notifications/';
    }

    public function __toString()
    {
        return 'Notifications initialized';
    }

    public function notify($platform, $payload)
    {
        $platformData   = $this->getNotificationPlatformFromId($platform);
        $logfile        = $this->logpath . $platformData['name'] . '-'. date('Ymd') .'.log';

        logger($logfile, 'notification request to ' . $platformData['name'], 'info');
        logger($logfile, 'notification payload: ' . json_encode($payload), 'info');

        /*
            Everything should return an array with code => ..., error => ... (if no error, just code is fine)
        */
        switch ($platform) {
            case 1: //-- Notifiarr
                return $this->notifiarr($logfile, $payload);
        }
    }

    public function getPlatforms()
    {
        return $this->platforms;
    }

    public function getNotificationPlatformFromId($platform)
    {
        return $this->platforms[$platform];
    }
}
