<?php

/*
----------------------------------
 ------  Created: 042124   ------
 ------  Austin Best	   ------
----------------------------------
*/

trait Container
{
    public function getContainerPort($container, $params = '')
    {
        $cmd = sprintf(DockerSock::CONTAINER_PORT, $container, $params);
        return shell_exec($cmd . ' 2>&1');
    }

    public function removeContainer($container)
    {
        $cmd = sprintf(DockerSock::REMOVE_CONTAINER, $container);
        return shell_exec($cmd . ' 2>&1');
    }

    public function startContainer($container)
    {
        $cmd = sprintf(DockerSock::START_CONTAINER, $container);    
        return shell_exec($cmd . ' 2>&1');
    }

    public function stopContainer($container)
    {
        $cmd = sprintf(DockerSock::STOP_CONTAINER, $container);    
        return shell_exec($cmd . ' 2>&1');
    }

    public function getOrphanContainers()
    {
        $cmd = DockerSock::ORPHAN_CONTAINERS;    
        return shell_exec($cmd . ' 2>&1');
    }

    public function findContainer($query = [])
    {
        if ($query['id']) {
            foreach ($query['data'] as $process) {
                if ($process['ID'] == $query['id']) {
                    return $process['Names'];
                }
            }
        }

        if ($query['hash']) {
            if (!$query['data']) {
                $stateFile = getServerFile('state');
                $query['data'] = $stateFile['file'];
            }
        
            foreach ($query['data'] as $container) {
                if (md5($container['Names']) == $query['hash']) {
                    return $container;
                }
            }
        }
    }

    public function getContainerNetworkDependencies($parentId, $processList)
    {
        $dependencies = [];

        foreach ($processList as $process) {
            $networkMode = $process['inspect'][0]['HostConfig']['NetworkMode'];
    
            if (str_contains($networkMode, ':')) {
                list($null, $networkContainer) = explode(':', $networkMode);
    
                if ($networkContainer == $parentId) {
                    $dependencies[] = $process['Names'];
                }
            }
        }
    
        return $dependencies;
    }

    public function getContainerLabelDependencies($containerName, $processList)
    {
        $dependencies = [];

        foreach ($processList as $process) {
            $labels = $process['inspect'][0]['Config']['Labels'] ? $process['inspect'][0]['Config']['Labels'] : [];
    
            foreach ($labels as $name => $key) {
                if (str_contains($name, 'depends_on')) {
                    list($container, $condition) = explode(':', $key);
    
                    if ($container == $containerName) {
                        $dependencies[] = $process['Names'];
                    }
                }
            }
        }
    
        return $dependencies;
    }

    public function setContainerDependencies($processList)
    {
        $dependencyList = [];
        foreach ($processList as $process) {
            $dependencies = $this->getContainerNetworkDependencies($process['ID'], $processList);
    
            if ($dependencies) {
                $dependencyList[$process['Names']] = ['id' => $process['ID'], 'containers' => $dependencies];
            }
        }
    
        setServerFile('dependencies', json_encode($dependencyList));
    
        return $dependencyList;
    }

}
