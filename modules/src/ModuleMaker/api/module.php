<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
/**
* Foxtrot (C) 2016 <foxtrotnull@gmail.com>
**/
class ModuleMaker extends Controller
{
    protected $endpointRoutes = ['generateModule', 'getInstalledModules', 'removeModule'];

    public function generateModule()
    {
        /* Make life easier */
        $moduleTitle = $this->request['moduleTitle'];
        $moduleDescription = $this->request['moduleDesc'];
        $moduleVersion = $this->request['moduleVersion'];
        $moduleAuthor = str_replace(' ', '', $this->request['moduleAuthor']);
        $moduleName = str_replace(' ', '', $moduleTitle);
        $modulePath = "/pineapple/modules/{$moduleName}";
        $templates = "/pineapple/modules/ModuleMaker/Extra";

        /* Check whether the user has acceptable input and check if the module already exists */
        if(empty($moduleTitle)){
            $this->responseHandler->setError("The module name cannot be empty.");
        } elseif (file_exists($modulePath)) {
            $this->responseHandler->setError("A module with this name already exists.");
        } elseif(empty($moduleDescription)){
            $this->responseHandler->setError("You must specify a description.");
        } elseif(!is_numeric($moduleVersion)){
            $this->responseHandler->setError("Module version must be in the form of X.X");
        } elseif(substr_count($moduleVersion, '.') <1 || substr_count($moduleVersion, '.') >1){
            $this->responseHandler->setError("Module version must only contain one period.");
        } elseif(empty($moduleAuthor)){
            $this->responseHandler->setError("You must supply an Author.");
        }

        /* If an error is set, return early */
        if($this->error){
            return;
        }

        if(substr_count($moduleVersion, '.') <1){

        }

        /* If the user passes all the above checks, proceed to create the module */
        mkdir("{$modulePath}");
        mkdir("{$modulePath}/js/");
        mkdir("{$modulePath}/api/");
        copy("{$templates}/module.html", "{$modulePath}/module.html");
        copy("{$templates}/module.js", "{$modulePath}/js/module.js");
        exec("sed -i 's/_MODULE_NAME/{$moduleName}/g' {$modulePath}/js/module.js");
        copy("{$templates}/module.php", "{$modulePath}/api/module.php");
        exec("sed -i 's/_MODULE_NAME/{$moduleName}/g' {$modulePath}/api/module.php");

        /* Create a module.info with the data the user has input */
        file_put_contents("{$modulePath}/module.info", json_encode(array("title" => $moduleTitle, "description" => $moduleDescription, "version" => $moduleVersion, "author" => $moduleAuthor), JSON_PRETTY_PRINT));


        /* Once the module has been created, set the response to success */
        $this->responseHandler->setData(array("success" => true));

    }

    public function getInstalledModules()
    {
        $modules = array();
        $modulesDirectories = scandir('/pineapple/modules');
        foreach ($modulesDirectories as $moduleDirectory) {
            if ($moduleDirectory[0] === ".") {
                continue;
            }

            if (file_exists("/pineapple/modules/{$moduleDirectory}/module.info")) {
                $moduleData = json_decode(file_get_contents("/pineapple/modules/{$moduleDirectory}/module.info"));
                
                $module = array();
                $module['title'] = $moduleData->title;
                $module['author'] = $moduleData->author;
                $module['version'] = $moduleData->version;
                $module['description'] = $moduleData->description;
                if (isset($moduleData->system)) {
                    $module['type'] = "System";
                } else {
                    $module['type'] = "GUI";
                }

                $modules[$moduleDirectory] = $module;
            }
        }
        $this->responseHandler->setData(array("installedModules" => $modules));
    }
}




