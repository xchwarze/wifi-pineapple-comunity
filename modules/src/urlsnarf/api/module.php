<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class urlsnarf extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'toggleurlsnarf', 'handleDependencies', 'handleDependenciesStatus', 'refreshHistory', 'viewHistory', 'deleteHistory', 'downloadHistory', 'toggleurlsnarfOnBoot', 'getInterfaces', 'saveAutostartSettings'];

    protected function checkDep($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("urlsnarf.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/urlsnarf/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDep("urlsnarf")) {
            $this->systemHelper->execBackground("/pineapple/modules/urlsnarf/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/urlsnarf/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/urlsnarf.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function toggleurlsnarfOnBoot()
    {
        if (exec("cat /etc/rc.local | grep urlsnarf/scripts/autostart_urlsnarf.sh") == "") {
            exec("sed -i '/exit 0/d' /etc/rc.local");
            exec("echo /pineapple/modules/urlsnarf/scripts/autostart_urlsnarf.sh >> /etc/rc.local");
            exec("echo exit 0 >> /etc/rc.local");
        } else {
            exec("sed -i '/urlsnarf\/scripts\/autostart_urlsnarf.sh/d' /etc/rc.local");
        }
    }

    public function toggleurlsnarf()
    {
        if (!$this->systemHelper->checkRunning("urlsnarf")) {
            $this->systemHelper->uciSet("urlsnarf.run.interface", $this->request['interface']);

            $this->systemHelper->execBackground("/pineapple/modules/urlsnarf/scripts/urlsnarf.sh start");
        } else {
            $this->systemHelper->uciSet("urlsnarf.run.interface", '');

            $this->systemHelper->execBackground("/pineapple/modules/urlsnarf/scripts/urlsnarf.sh stop");
        }
    }

    public function getInterfaces()
    {
        exec("cat /proc/net/dev | tail -n +3 | cut -f1 -d: | sed 's/ //g'", $interfaceArray);

        $this->responseHandler->setData(array("interfaces" => $interfaceArray, "selected" => $this->systemHelper->uciGet("urlsnarf.run.interface")));
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/urlsnarf.progress')) {
            if (!$this->checkDep("urlsnarf")) {
                $installed = false;
                $install = "Not installed";
                $installLabel = "danger";
                $processing = false;

                $status = "Start";
                $statusLabel = "success";

                $bootLabelON = "default";
                $bootLabelOFF = "danger";
            } else {
                $installed = true;
                $install = "Installed";
                $installLabel = "success";
                $processing = false;

                if ($this->systemHelper->checkRunning("urlsnarf")) {
                    $status = "Stop";
                    $statusLabel = "danger";
                } else {
                    $status = "Start";
                    $statusLabel = "success";
                }

                if (exec("cat /etc/rc.local | grep urlsnarf/scripts/autostart_urlsnarf.sh") == "") {
                    $bootLabelON = "default";
                    $bootLabelOFF = "danger";
                } else {
                    $bootLabelON = "success";
                    $bootLabelOFF = "default";
                }
            }
        } else {
            $installed = false;
            $install = "Installing...";
            $installLabel = "warning";
            $processing = true;

            $status = "Not running";
            $statusLabel = "danger";

            $bootLabelON = "default";
            $bootLabelOFF = "danger";
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "bootLabelON" => $bootLabelON, "bootLabelOFF" => $bootLabelOFF, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if ($this->checkDep("urlsnarf")) {
            if ($this->systemHelper->checkRunning("urlsnarf")) {
                $path = "/pineapple/modules/urlsnarf/log";

                $latest_ctime = 0;
                $latest_filename = '';

                $d = dir($path);
                while (false !== ($entry = $d->read())) {
                    $filepath = "{$path}/{$entry}";
                    if (is_file($filepath) && filectime($filepath) > $latest_ctime) {
                        $latest_ctime = filectime($filepath);
                        $latest_filename = $entry;
                    }
                }

                if ($latest_filename != "") {
                    $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/urlsnarf/log/".$latest_filename));

                    if ($this->request['filter'] != "") {
                        $filter = $this->request['filter'];

                        $cmd = "cat /pineapple/modules/urlsnarf/log/".$latest_filename." | ".$filter;
                    } else {
                        $cmd = "cat /pineapple/modules/urlsnarf/log/".$latest_filename;
                    }

                    exec($cmd, $output);
                    if (!empty($output)) {
                        $this->responseHandler->setData(implode("\n", array_reverse($output)));
                    } else {
                        $this->responseHandler->setData("Empty log...");
                    }
                }
            } else {
                $this->responseHandler->setData("urlsnarf is not running...");
            }
        } else {
            $this->responseHandler->setData("urlsnarf is not installed...");
        }
    }

    public function refreshHistory()
    {
        $this->streamFunction = function () {
            $log_list = array_reverse(glob("/pineapple/modules/urlsnarf/log/*"));

            echo '[';
            for ($i=0;$i<count($log_list);$i++) {
                $info = explode("_", basename($log_list[$i]));
                $entryDate = gmdate('Y-m-d H-i-s', $info[1]);
                $entryName = basename($log_list[$i]);

                echo json_encode(array($entryDate, $entryName));

                if ($i!=count($log_list)-1) {
                    echo ',';
                }
            }
            echo ']';
        };
    }

    public function viewHistory()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/urlsnarf/log/".$this->request['file']));
        exec("cat /pineapple/modules/urlsnarf/log/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty log...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        exec("rm -rf /pineapple/modules/urlsnarf/log/".$this->request['file']);
    }

    public function downloadHistory()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/urlsnarf/log/".$this->request['file'])));
    }

    public function saveAutostartSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("urlsnarf.autostart.interface", $settings->interface);
    }
}
