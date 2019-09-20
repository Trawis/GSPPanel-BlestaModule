<?php

class GspPanel extends Module {

    /**
     * Initializes the module
     */
    public function __construct() {
        // Load components required by this module
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this module
        Language::loadLang('gsppanel', null, dirname(__FILE__) . DS . 'language' . DS);
        Language::loadLang('gsppanel_package', null, dirname(__FILE__) . DS . 'language' . DS);

        // Load module config
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load additional config settings
        Configure::load('gsppanel', dirname(__FILE__) . DS . 'config' . DS);

        Loader::loadModels($this, ['Clients']);
        Loader::loadModels($this, ['Session']);

        Loader::load(dirname(__FILE__) . DS . 'apis' . DS . 'gsppanel_api.php');

    }

    /**
     * Loads a library class
     *
     * @param string $command The filename of the class to load
     */
    private function loadLib($command) {
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . $command . '.php');
    }

    /**
     * Performs any necessary bootstraping actions. Sets Input errors on
     * failure, preventing the module from being added.
     *
     * @return array A numerically indexed array of meta data containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function install() {
//        // Perform installation checks
//        $this->loadLib('Pterodactyl_module');
//        $module = new PterodactylModule();
//        $meta = $module->install();
//
//        if (($errors = $module->errors())) {
//            $this->Input->setErrors($errors);
//        } else {
//            return $meta;
//        }
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version. Sets Input errors on failure, preventing
     * the module from being upgraded.
     *
     * @param string $current_version The current installed version of this module
     */
    public function upgrade($current_version) {
//        // Perform installation checks
//        $this->loadLib('Pterodactyl_module');
//        $module = new PterodactylModule();
//        $module->setConfig($this->config);
//        $module->upgrade($current_version);
//
//        if (($errors = $module->errors())) {
//            $this->Input->setErrors($errors);
//        }
    }

    /**
     * Returns the rendered view of the manage module page
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manage
     *  module page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the manager module page
     * @throws Exception
     */
    public function manageModule($module, array &$vars) {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('manage', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'gsppanel' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('module', $module);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page
     *
     * @param array $vars An array of post data submitted to or on the add
     *  module row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the add module row page
     * @throws Exception
     */
    public function manageAddRow(array &$vars) {
        Loader::loadHelpers($this, ['DataStructure']);
        $this->ArrayHelper = $this->DataStructure->create('Array');

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('add_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'gsppanel' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);


        // Format IP post data on submission error
        if (!empty($vars) && !empty($vars['ips']) && is_array($vars['ips'])) {
            $vars['ips'] = $this->ArrayHelper->keyToNumeric($vars['ips']);
        }

        $this->view->set('vars', (object)$vars);
        //$this->view->set('ips_in_use', $this->getIpsInUseFields());

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the
     *  edit module row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the edit module row page
     * @throws Exception
     */
    public function manageEditRow($module_row, array &$vars) {
        Loader::loadHelpers($this, ['DataStructure']);
        $this->ArrayHelper = $this->DataStructure->create('Array');

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('edit_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'gsppanel' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
        } else {
            // Format IP post data on submission error
            if (!empty($vars['ips']) && is_array($vars['ips'])) {
                $vars['ips'] = $this->ArrayHelper->keyToNumeric($vars['ips']);
            }
        }

        $this->view->set('vars', (object)$vars);
        return $this->view->fetch();
    }


    public function getPackageFields($vars = null) {
        // Fetch the package fields
        $this->loadLib('gsppanel_package');
        $package = new PterodactylPackage();
        return $package->getFields($vars);
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure,
     * preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being added (if the current service is an addon service
     *  service and parent service has already been provisioned)
     * @param string $status The status of the service being added. These include:
     *  - active
     *  - canceled
     *  - pending
     *  - suspended
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        // Fetch the module row
        $row = $this->getModuleRow();

        if (!$row) {
            $this->Input->setErrors(
                ['module_row' => ['missing' => Language::_('Pterodactyl.!error.module_row.missing', true)]]
            );
            return;
        }
        $server_config_options = (array)$package->meta;

        $client_selected_config_options = (array)(isset($vars['configoptions'])?$vars['configoptions']:null);
        $client_id = $vars['client_id'];

        $panel_details = (array)$row->meta;


        $client = $this->Clients->get($client_id);

        $server_config_options['serviceid'] = pterodactyl_GenerateUsername(6) . '_' . $client->id_code;

        if (isset($vars['service_id']) && $vars['service_id'] > 0) {
            $server_config_options['serviceid'] = $vars['service_id'];
        }

        $clientdetails['clientsdetails'] = $client;

        $params = array_merge($server_config_options, $client_selected_config_options, $clientdetails, $panel_details);


        $meta['server_identifier'] = '-';
        $meta['server_id'] = '-';
        $meta['server_uuid'] = '-';
        $meta['server_external_id'] = '-';
        $meta['server_user_id'] = '-';

        if ($vars['use_module'] == 'true') {
            // Add the service
            $serverCreated = pterodactyl_CreateAccount($params);

            if (isset($serverCreated['attributes']['identifier'])) {
                $meta['server_identifier'] = $serverCreated['attributes']['identifier'];
            } else {
                $this->Input->setErrors(['api' => ['internal' => 'Failed to create server. - '.serialize($serverCreated)]]);
                return;
            }

            if (isset($serverCreated['attributes']['id'])) {
                $meta['server_id'] = $serverCreated['attributes']['id'];
            }
            if (isset($serverCreated['attributes']['uuid'])) {
                $meta['server_uuid'] = $serverCreated['attributes']['uuid'];
            }
            if (isset($serverCreated['attributes']['external_id'])) {
                $meta['server_external_id'] = $serverCreated['attributes']['external_id'];
            }
            if (isset($serverCreated['attributes']['user'])) {
                $meta['server_user_id'] = $serverCreated['attributes']['user'];
            }

        }
        // Log the requests
//        $this->logResponses($service->getLogs());
//
//        if (($errors = $service->errors())) {
//            $this->Input->setErrors($errors);
//        }


        return [
            [
                'key' => 'server_identifier',
                'value' => $meta['server_identifier'],
                'encrypted' => 0
            ],
            [
                'key' => 'server_id',
                'value' => $meta['server_id'],
                'encrypted' => 0
            ],
            [
                'key' => 'server_uuid',
                'value' => $meta['server_uuid'],
                'encrypted' => 0
            ],
            [
                'key' => 'server_external_id',
                'value' => $meta['server_external_id'],
                'encrypted' => 0
            ],
            [
                'key' => 'server_user_id',
                'value' => $meta['server_user_id'],
                'encrypted' => 0
            ]
        ];
    }


    /**
     * Returns the client tabs
     * @param stdClass $package
     * @return array
     */
    public function getClientTabs($package) {
        return [
            'serverPanel' => ['name' => Language::_('Pterodactyl.tab_client_manage_server', true), 'icon' => 'fa fa-terminal'],
        ];
    }

    /**
     * Server Panel Action when viewing service in client area.
     * There is no much action we can do via the panel api because rate limits,
     * so we simple redirect the user to the panel to manage their server.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @return string The string representing the contents of this tab
     * @throws Exception
     */
    public function serverPanel($package, $service) {

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        // Get Panel Details
        $row = $this->getModuleRow($package->module_row);

        // Redirect to the panel location
        if (isset($row->meta->panel_url)) {
            if (isset($service_fields->server_identifier)) {
                header("Location: " . $row->meta->panel_url . 'server/' . $service_fields->server_identifier);
            } else {
                header("Location: " . $row->meta->panel_url);
            }
            die();
        }
        return true;
    }

    public function getAdminServiceInfo($service, $package) {
        $row = $this->getModuleRow();

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('admin_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'pterodactyl' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        return $this->view->fetch();
    }

    public function getClientServiceInfo($service, $package) {
        $row = $this->getModuleRow();

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'pterodactyl' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);


        $service_fields = $this->serviceFieldsToObject($service->fields);

        $this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $service_fields);

        return $this->view->fetch();
    }

}
