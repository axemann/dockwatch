<?php

/*
----------------------------------
 ------  Created: 111723   ------
 ------  Austin Best	   ------
----------------------------------
*/

define('ABSOLUTE_PATH', str_replace('crons', '', __DIR__));
require ABSOLUTE_PATH . 'loader.php';

set_time_limit(0);

logger(SYSTEM_LOG, 'Cron: running pulls');
logger(CRON_PULLS_LOG, 'run ->');
echo date('c') . ' Cron run started: pulls' . "\n";

if ($settingsFile['tasks']['pulls']['disabled']) {
    logger(CRON_PULLS_LOG, 'Cron run stopped: disabled in tasks menu');
    echo date('c') . ' Cron run cancelled: disabled in tasks menu' . "\n";
    exit();
}

$updateSettings = $settingsFile['containers'];
$notify         = [];
$startStamp     = new DateTime();

if ($updateSettings) {
    $imagesUpdated = [];

    foreach ($updateSettings as $containerHash => $containerSettings) {
        //-- SET TO IGNORE
        if (!$containerSettings['updates']) {
            continue;
        }

        $containerState = $docker->findContainer(['hash' => $containerHash, 'data' => $stateFile]);
        $preVersion = $postVersion = $cron = '';
        if ($containerState) {
            $isDockwatch = isDockwatchContainer($containerState) ? true : false;
            $pullHistory = $pullsFile[$containerHash];

            try {
                $cron = Cron\CronExpression::factory($containerSettings['frequency']);
            } catch (Exception $e) {
                $msg = 'Skipping: ' . $containerState['Names'] . ' frequency setting is not a valid cron syntax \'' . $containerSettings['frequency'] . '\'';
                logger(CRON_PULLS_LOG, $msg);
                echo date('c') . ' ' . $msg . "\n";
                continue;
            }

            if ($cron->isDue($startStamp)) {
                $image          = $docker->isIO($containerState['inspect'][0]['Config']['Image']);
                $currentImageID = $containerState['ID'];

                if (!$image) {
                    $msg = 'Skipping local (has no Config.Image): ' . $containerState['Names'];
                    logger(CRON_PULLS_LOG, $msg, 'error');
                    echo date('c') . ' ' . $msg . "\n";
                    continue;
                }

                $msg = 'Inspecting image: ' . $image;
                logger(CRON_PULLS_LOG, $msg);
                echo date('c') . ' ' . $msg . "\n";
                $inspectImage = $docker->inspect($image, false);
                logger(CRON_PULLS_LOG, '$inspectImage=' . $inspectImage);
                $inspectImage = json_decode($inspectImage, true);

                if ($inspectImage) {
                    foreach ($inspectImage[0]['Config']['Labels'] as $label => $val) {
                        if (str_contains($label, 'image.version')) {
                            $preVersion = $val;
                            break;
                        }
                    }
                }

                $msg = 'Pre image version: ' . $preVersion;
                logger(CRON_PULLS_LOG, $msg);
                echo date('c') . ' ' . $msg . "\n";

                $msg = 'Getting registry digest: ' . $image;
                logger(CRON_PULLS_LOG, $msg);
                echo date('c') . ' ' . $msg . "\n";
                $regctlDigest = trim(regctlCheck($image));

                if (!$regctlDigest || str_contains($regctlDigest, 'Error')) {
                    $msg = 'Skipping checks (regctl failed): \'' . $regctlDigest . '\'';
                    logger(CRON_PULLS_LOG, $msg, 'error');
                    echo date('c') . ' ' . $msg . "\n";
                    continue;
                }

                //-- LOOP ALL IMAGE DIGESTS, STOP AT A MATCH
                foreach ($inspectImage[0]['RepoDigests'] as $digest) {
                    list($cr, $imageDigest) = explode('@', $digest);

                    if ($imageDigest == $regctlDigest) {
                        break;
                    }
                }

                $msg = 'Updating pull data: ' . $containerState['Names'] . "\n";
                $msg .= '|__ regctl \'' . truncateMiddle(str_replace('sha256:', '', $regctlDigest), 30) . '\' image \'' . truncateMiddle(str_replace('sha256:', '', $imageDigest), 30) .'\'';
                logger(CRON_PULLS_LOG, $msg);
                echo date('c') . ' ' . $msg . "\n";

                $pullsFile[$containerHash]  = [
                                                'checked'       => time(),
                                                'name'          => $containerState['Names'],
                                                'regctlDigest'  => $regctlDigest,
                                                'imageDigest'   => $imageDigest
                                            ];

                //-- DONT AUTO UPDATE THIS CONTAINER, CHECK ONLY
                if (skipContainerActions($image, $skipContainerActions)) {
                    if ($isDockwatch) {
                        $msg = '[BYPASS] Skipping container update: ' . $containerState['Names'] . ' (image is listed in skipContainerActions())';
                    } else {
                        $msg = 'Skipping container update: ' . $containerState['Names'] . ' (image is listed in skipContainerActions())';

                        if ($containerSettings['updates'] == 1) {
                            $containerSettings['updates'] = 2;
                        }
                    }

                    logger(CRON_PULLS_LOG, $msg);
                    echo date('c') . ' ' . $msg . "\n";
                }

                if (!$dockerCommunicateAPI) {
                    $msg = 'Skipping container update: ' . $containerState['Names'] . ' (docker engine api access is not available)';
                    logger(CRON_PULLS_LOG, $msg);
                    echo date('c') . ' ' . $msg . "\n";

                    if ($containerSettings['updates'] == 1) {
                        $containerSettings['updates'] = 2;
                    }
                }

                if (in_array($image, $imagesUpdated) || $regctlDigest != $imageDigest) {
                    $imagesUpdated[] = $image; //-- THIS IS TO TRACK WHAT UPDATES SO MULTIPLE CONTAINERS ON THE SAME IMAGE ALL UPDATE

                    switch ($containerSettings['updates']) {
                        case 1: //-- Auto update
                            if ($isDockwatch) {
                                $msg = '$maintenance->apply(), check the maintenance log for update details';
                                logger(CRON_PULLS_LOG, $msg);
                                echo date('c') . ' ' . $msg . "\n";

                                $pullsFile[$containerHash]  = [
                                                                'checked'       => time(),
                                                                'name'          => $containerState['Names'],
                                                                'regctlDigest'  => $regctlDigest,
                                                                'imageDigest'   => $regctlDigest
                                                            ];

                                //-- UPDATE THE PULLS FILE BEFORE THIS CALL SINCE THIS STOPS THE PROCESS
                                setServerFile('pull', $pullsFile);

                                $maintenance = new Maintenance();
                                $maintenance->apply('update');
                            } else {
                                $msg = 'Inspecting container: ' . $containerState['Names'];
                                logger(CRON_PULLS_LOG, $msg);
                                echo date('c') . ' ' . $msg . "\n";
                                $inspect = $docker->inspect($containerState['Names'], false);

                                $msg = 'Pulling image: ' . $image;
                                logger(CRON_PULLS_LOG, $msg);
                                echo date('c') . ' ' . $msg . "\n";
                                $docker->pullImage($image);

                                $msg = 'Stopping container: ' . $containerState['Names'];
                                logger(CRON_PULLS_LOG, $msg);
                                echo date('c') . ' ' . $msg . "\n";
                                $stop = $docker->stopContainer($containerState['Names']);
                                logger(CRON_PULLS_LOG, trim($stop));

                                $msg = 'Removing container: ' . $containerState['Names'] . ' (' . $containerState['ID'] . ')';
                                logger(CRON_PULLS_LOG, $msg);
                                echo date('c') . ' ' . $msg . "\n";
                                $remove = $docker->removeContainer($containerState['Names']);
                                logger(CRON_PULLS_LOG, trim($remove));

                                $msg = 'Updating container: ' . $containerState['Names'];
                                logger(CRON_PULLS_LOG, $msg);
                                echo date('c') . ' ' . $msg . "\n";
                                $update = dockerCreateContainer(json_decode($inspect, true));
                                logger(CRON_PULLS_LOG, 'dockerCreateContainer: ' . trim(json_encode($update, JSON_UNESCAPED_SLASHES)));

                                if (strlen($update['Id']) == 64) {
                                    // REMOVE THE IMAGE AFTER UPDATE
                                    $msg = 'Removing old image: ' . $currentImageID;
                                    logger(CRON_PULLS_LOG, $msg);
                                    echo date('c') . ' ' . $msg . "\n";
                                    $removeImage = $docker->removeImage($currentImageID);
                                    logger(CRON_PULLS_LOG, '$docker->removeImage: ' . trim(json_encode($removeImage, JSON_UNESCAPED_SLASHES)));

                                    $msg = 'Updating pull data: ' . $containerState['Names'];
                                    logger(CRON_PULLS_LOG, $msg);
                                    echo date('c') . ' ' . $msg . "\n";
                                    $pullsFile[$containerHash]  = [
                                                                    'checked'       => time(),
                                                                    'name'          => $containerState['Names'],
                                                                    'regctlDigest'  => $regctlDigest,
                                                                    'imageDigest'   => $regctlDigest
                                                                ];

                                    if (str_contains($containerState['State'], 'running')) {
                                        $msg = 'Starting container: ' . $containerState['Names'];
                                        logger(CRON_PULLS_LOG, $msg);
                                        echo date('c') . ' ' . $msg . "\n";
                                        $start = $docker->startContainer($containerState['Names']);
                                        logger(CRON_PULLS_LOG, 'dockerStartContainer:' . trim($start));
                                    } else {
                                        logger(CRON_PULLS_LOG, 'container was not running, not starting it');
                                    }

                                    $msg = 'Inspecting image: ' . $image;
                                    logger(CRON_PULLS_LOG, $msg);
                                    echo date('c') . ' ' . $msg . "\n";
                                    $inspectImage   = $docker->inspect($image, false);
                                    $inspectImage   = json_decode($inspectImage, true);

                                    if ($inspectImage) {
                                        foreach ($inspectImage[0]['Config']['Labels'] as $label => $val) {
                                            if (str_contains($label, 'image.version')) {
                                                $postVersion = $val;
                                                break;
                                            }
                                        }
                                    }

                                    $msg = 'Post image version: ' . $postVersion;
                                    logger(CRON_PULLS_LOG, $msg);
                                    echo date('c') . ' ' . $msg . "\n";

                                    $dependencyFile = getServerFile('dependencies');
                                    $dependencyFile = $dependencyFile['file'];
                                    $dependencies   = $dependencyFile[$containerState['Names']]['containers'];

                                    if (is_array($dependencies)) {
                                        $msg = 'dependencies found ' . count($dependencies);
                                        logger(CRON_PULLS_LOG, $msg);
                                        echo date('c') . ' ' . $msg . "\n";

                                        updateDependencyParentId($containerState['Names'], $update['Id']);

                                        foreach ($dependencies as $dependencyContainer) {
                                            $msg = '[dependency] dockerInspect: ' . $dependencyContainer;
                                            logger(CRON_PULLS_LOG, $msg);
                                            echo date('c') . ' ' . $msg . "\n";
                                            $apiResponse = apiRequest('dockerInspect', ['name' => $dependencyContainer, 'useCache' => false, 'format' => true]);
                                            logger(CRON_PULLS_LOG, 'dockerInspect:' . json_encode($apiResponse, JSON_UNESCAPED_SLASHES));
                                            $inspectImage = $apiResponse['response']['docker'];

                                            $msg = '[dependency] dockerStopContainer: ' . $dependencyContainer;
                                            logger(CRON_PULLS_LOG, $msg);
                                            echo date('c') . ' ' . $msg . "\n";
                                            $apiResult = apiRequest('dockerStopContainer', [], ['name' => $dependencyContainer]);
                                            logger(CRON_PULLS_LOG, 'dockerStopContainer:' . json_encode($apiResult, JSON_UNESCAPED_SLASHES));

                                            $msg = '[dependency] dockerRemoveContainer: ' . $dependencyContainer;
                                            logger(CRON_PULLS_LOG, $msg);
                                            echo date('c') . ' ' . $msg . "\n";
                                            $apiResult = apiRequest('dockerRemoveContainer', [], ['name' => $dependencyContainer]);
                                            logger(CRON_PULLS_LOG, 'dockerRemoveContainer:' . json_encode($apiResult, JSON_UNESCAPED_SLASHES));

                                            $msg = '[dependency] dockerCreateContainer: ' . $dependencyContainer;
                                            logger(CRON_PULLS_LOG, $msg);
                                            echo date('c') . ' ' . $msg . "\n";
                                            $apiResponse = apiRequest('dockerCreateContainer', [], ['inspect' => $inspectImage]);
                                            logger(CRON_PULLS_LOG, 'dockerCreateContainer:' . json_encode($apiResponse, JSON_UNESCAPED_SLASHES));
                                            $update         = $apiResponse['response']['docker'];
                                            $createResult   = 'failed';

                                            if (strlen($update['Id']) == 64) {
                                                $createResult = 'complete';
                                                logger(CRON_PULLS_LOG, 'Container ' . $dependencyContainer . ' re-create: ' . $createResult);

                                                if (str_contains($containerState['State'], 'running')) {
                                                    $msg = '[dependency] dockerStartContainer: ' . $dependencyContainer;
                                                    logger(CRON_PULLS_LOG, $msg);
                                                    echo date('c') . ' ' . $msg . "\n";
                                                    $apiResponse = apiRequest('dockerStartContainer', [], ['name' => $dependencyContainer]);
                                                    logger(CRON_PULLS_LOG, 'dockerStartContainer:' . json_encode($apiResponse, JSON_UNESCAPED_SLASHES));
                                                } else {
                                                    logger(CRON_PULLS_LOG, 'container was not running, not starting it');
                                                }
                                            }
                                        }
                                    }

                                    if ($settingsFile['notifications']['triggers']['updated']['active'] && !$settingsFile['containers'][$containerHash]['disableNotifications']) {
                                        $notify['updated'][]    = [
                                                                    'container' => $containerState['Names'],
                                                                    'image'     => $image,
                                                                    'pre'       => ['digest' => str_replace('sha256:', '', $imageDigest), 'version' => $preVersion],
                                                                    'post'      => ['digest' => str_replace('sha256:', '', $regctlDigest), 'version' => $postVersion]
                                                                ];
                                    }
                                } else {
                                    $msg = 'Invalid hash length: \'' . $update .'\'=' . strlen($update['Id']);
                                    logger(CRON_PULLS_LOG, $msg);
                                    echo date('c') . ' ' . $msg . "\n";

                                    if ($settingsFile['notifications']['triggers']['updated']['active'] && !$settingsFile['containers'][$containerHash]['disableNotifications']) {
                                        $notify['failed'][] = ['container' => $containerState['Names']];
                                    }
                                }
                            }
                            break;
                        case 2: //-- Check for updates
                            if ($settingsFile['notifications']['triggers']['updates']['active'] && $inspectImage[0]['Id'] != $inspectContainer[0]['Image'] && !$settingsFile['containers'][$containerHash]['disableNotifications']) {
                                $notify['available'][] = ['container' => $containerState['Names']];
                            }
                            break;
                    }
                }
            } else {
                $msg = 'Skipping: ' . $containerState['Names'] . ', frequency setting will run: ' . $cron->getNextRunDate()->format('Y-m-d H:i:s');
                logger(CRON_PULLS_LOG, $msg);
                echo date('c') . ' ' . $msg . "\n";
            }
        }
    }

    setServerFile('pull', $pullsFile);

    if ($notify) {
        //-- IF THEY USE THE SAME PLATFORM, COMBINE THEM
        if ($settingsFile['notifications']['triggers']['updated']['platform'] == $settingsFile['notifications']['triggers']['updates']['platform']) {
            $payload = ['event' => 'updates', 'available' => $notify['available'], 'updated' => $notify['updated']];
            logger(CRON_PULLS_LOG, 'Notification payload: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
            $notifications->notify($settingsFile['notifications']['triggers']['updated']['platform'], $payload);
        } else {
            if ($notify['available']) {
                $payload = ['event' => 'updates', 'available' => $notify['available']];
                logger(CRON_PULLS_LOG, 'Notification payload: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
                $notifications->notify($settingsFile['notifications']['triggers']['updated']['platform'], $payload);
            }

            if ($notify['usage']['mem']) {
                $payload = ['event' => 'updates', 'updated' => $notify['updated']];
                logger(CRON_PULLS_LOG, 'Notification payload: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
                $notifications->notify($settingsFile['notifications']['triggers']['updates']['platform'], $payload);
            }
        }
    }
}

logger(CRON_PULLS_LOG, 'run <-');