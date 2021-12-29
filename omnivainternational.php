<?php

require_once __DIR__ . "/classes/OmnivaIntShipment.php";
require_once __DIR__ . "/classes/OmnivaIntCarrier.php";
require_once __DIR__ . "/classes/OmnivaIntCategory.php";
require_once __DIR__ . "/classes/OmnivaIntDb.php";
require_once __DIR__ . "/classes/OmnivaIntHelper.php";
require_once __DIR__ . "/classes/OmnivaIntManifest.php";
require_once __DIR__ . "/classes/OmnivaIntService.php";
require_once __DIR__ . "/classes/OmnivaIntShipment.php";
require_once __DIR__ . "/classes/OmnivaIntTerminal.php";
require_once __DIR__ . "/vendor/autoload.php";

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class OmnivaInternational extends CarrierModule
{
    const CONTROLLER_OMNIVA_MAIN = 'AdminOmnivaIntMain';

    const CONTROLLER_OMNIVA_SETTINGS = 'AdminOmnivaIntSettings';

    const CONTROLLER_OMNIVA_CARRIERS = 'AdminOmnivaIntCarriers';

    const CONTROLLER_CATEGORIES = 'AdminOmnivaIntCategories';

    const CONTROLLER_TERMINALS = 'AdminOmnivaIntTerminals';

    const CONTROLLER_OMNIVA_SERVICES = 'AdminOmnivaIntServices';

    /**
     * List of hooks
     */
    protected $_hooks = array(
        'header',
        'displayCarrierExtraContent',
        'updateCarrier',
        'displayAdminOrder',
        'actionValidateStepComplete',
        'actionValidateOrder',
        'actionAdminControllerSetMedia',
        'displayAdminListBefore',
        'actionCarrierProcess',
        'displayAdminOmnivaIntServicesListBefore',
        'displayAdminOmnivaIntTerminalsListBefore'
    );

    /**
     * List of fields keys in module configuration
     */
    public $_configKeys = array(
        'API' => array(
            'token' => 'OMNIVA_TOKEN',
            'test_mode' => 'OMNIVA_INT_TEST_MODE'
        ),
        'SHOP' => array(
            'sender_name' => 'MJVP_SENDER_NAME',
            'shop_contact' => 'MJVP_SHOP_CONTACT',
            'company_code' => 'MJVP_SHOP_COMPANY_CODE',
            'shop_country_code' => 'MJVP_SHOP_COUNTRY_CODE',
            'shop_city' => 'MJVP_SHOP_CITY',
            'shop_address' => 'MJVP_SHOP_ADDRESS',
            'shop_postcode' => 'MJVP_SHOP_POSTCODE',
            'shop_phone' => 'MJVP_SHOP_PHONE',
            'shop_email' => 'MJVP_SHOP_EMAIL',
            'sender_address' => 'MJVP_SENDER_ADDRESS',
        ),
        'COURIER' => array(
            'door_code' => 'MJVP_COURIER_DOOR_CODE',
            'warehouse_number' => 'MJVP_COURIER_WAREHOUSE_NUMBER',
            'cabinet_number' => 'MJVP_COURIER_CABINET_NUMBER',
            'delivery_time' => 'MJVP_COURIER_DELIVERY_TIME',
            'call_before_delivery' => 'MJVP_COURIER_CALL_BEFORE_DELIVERY',
            'return_service' => 'MJVP_RETURN_SERVICE',
            'return_days' => 'MJVP_RETURN_DAYS',
        ),
        'LABEL' => array(
            'label_size' => 'MJVP_LABEL_SIZE',
            'label_counter' => 'MJVP_COUNTER_PACKS',
        ),
        'ADVANCED' => array(
            'carrier_disable_passphrase' => 'MJVP_CARRIER_DISABLE_PASSPHRASE',
        ),
    );

    public $_configKeysOther = array(
        'counter_manifest' => array(
            'key' => 'MJVP_COUNTER_MANIFEST',
            'default_value' => array('counter' => 0, 'date' => ''),
        ),
        'counter_packs' => array(
            'key' => 'MJVP_COUNTER_PACKS',
            'default_value' => 0,
        ),
        'label_size' => array(
            'key' => 'MJVP_LABEL_SIZE',
            'default_value' => 'a6',
        ),
        'last_manifest_id' => array(
            'key' => 'MJVP_LAST_MANIFEST_ID',
            'default_value' => 0,
        ),
    );

    /**
     * Fields names and required
     */
    private function getConfigField($section_id, $config_key)
    {
        if ($section_id == 'SHOP') {
            if($config_key == 'sender_address')
                return array('name' => str_replace('_', ' ', $config_key), 'required' => false);
            return array('name' => str_replace('_', ' ', $config_key), 'required' => true);
        }

        return array('name' => 'ERROR_' . $config_key, 'required' => false);
    }

    public static $_order_states = array(
        'order_state_ready' => array(
            'key' => 'OMNIVA_INT_ORDER_STATE_READY',
            'color' => '#FCEAA8',
            'lang' => array(
                'en' => 'Omniva International shipment ready',
                'lt' => 'Omniva tarptautinė siunta paruošta',
            ),
        ),
        'order_state_error' => array(
            'key' => 'OMNIVA_INT_ORDER_STATE_ERROR',
            'color' => '#F24017',
            'lang' => array(
                'en' => 'Error on Omniva International shipment',
                'lt' => 'Klaida Omniva siuntoje',
            ),
        ),
    );

    public static $_classes = [
        'OmnivaIntShipment',
        'OmnivaIntTerminal',
        'OmnivaIntManifest',
        'OmnivaIntService',
        'OmnivaIntCarrier',
        'OmnivaIntCategory',
        'OmnivaIntRateCache'
    ];

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'omnivainternational';
        $this->tab = 'shipping_logistics';
        $this->version = '0.0.1';
        $this->author = 'mijora.lt';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6.0', 'max' => '1.7.8');
        $this->bootstrap = true;
        $this->helper = new OmnivaIntHelper();

        parent::__construct();

        $this->displayName = $this->l('Omniva International Shipping');
        $this->description = $this->l('Shipping module for Omniva international delivery method');
        $this->available_countries = array('LT', 'LV', 'EE', 'PL');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function getModuleService($service_name, $id = null)
    {
        $reflection = new \ReflectionClass($service_name);
        if(class_exists($service_name) && in_array($service_name, self::$_classes))
            return $id ? $reflection->newInstanceArgs([$id]) : $reflection->newInstance() ;
        elseif (!class_exists($service_name) && in_array($service_name, self::$_classes))
        {
            require_once __DIR__ . 'classes/' . $service_name . '.php';
            return $id ? $reflection->newInstanceArgs([$id]) : $reflection->newInstance() ;
        }
    }

    /**
     * Module installation function
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        foreach ($this->_hooks as $hook) {
            if (!$this->registerHook($hook)) {
                $this->_errors[] = $this->l('Failed to install hook') . ' ' . $hook . '.';
                return false;
            }
        }

        if (!$this->createDbTables()) {
            $this->_errors[] = $this->l('Failed to create tables.');
            return false;
        }

        if (!$this->addOrderStates()) {
            $this->_errors[] = $this->l('Failed to order states.');
            return false;
        }

        $this->registerTabs();
        $this->createCategoriesSettings();

        if(!Configuration::get('OMNIVA_CRON_TOKEN'))
        {
            Configuration::updateValue('OMNIVA_CRON_TOKEN', md5(time()));
        }

        return true;
    }

    /**
     * Provides list of Admin controllers info
     *
     * @return array BackOffice Admin controllers
     */
    private function getModuleTabs()
    {
        return array(
            self::CONTROLLER_OMNIVA_MAIN => array(
                'title' => $this->l('Omniva International'),
                'parent_tab' => 'AdminParentModulesSf',
            ),
            self::CONTROLLER_OMNIVA_SETTINGS => array(
                'title' => $this->l('Settings'),
                'parent_tab' => self::CONTROLLER_OMNIVA_MAIN,
            ),
            self::CONTROLLER_OMNIVA_CARRIERS => array(
                'title' => $this->l('Carriers'),
                'parent_tab' => self::CONTROLLER_OMNIVA_MAIN,
            ),
            self::CONTROLLER_OMNIVA_SERVICES => array(
                'title' => $this->l('Services'),
                'parent_tab' => self::CONTROLLER_OMNIVA_MAIN,
            ),
            self::CONTROLLER_CATEGORIES => array(
                'title' => $this->l('Category Settings'),
                'parent_tab' => self::CONTROLLER_OMNIVA_MAIN,
            ),
            self::CONTROLLER_TERMINALS => array(
                'title' => $this->l('Terminals'),
                'parent_tab' => self::CONTROLLER_OMNIVA_MAIN,
            ),
        );
    }

    /**
     * Registers module Admin tabs (controllers)
     */
    private function registerTabs()
    {
        $tabs = $this->getModuleTabs();

        if (empty($tabs)) {
            return true; // Nothing to register
        }

        foreach ($tabs as $controller => $tabData) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = $controller;
            $tab->name = array();
            $languages = Language::getLanguages(false);

            foreach ($languages as $language) {
                $tab->name[$language['id_lang']] = $tabData['title'];
            }

            $tab->id_parent = Tab::getIdFromClassName($tabData['parent_tab']);
            $tab->module = $this->name;
            if (!$tab->save()) {
                $this->displayError($this->l('Error while creating tab ') . $tabData['title']);
                return false;
            }
        }
        return true;
    }

    /**
     * Add Omniva order states
     */
    private function addOrderStates()
    {

        foreach (self::$_order_states as $os)
        {
            $order_state = (int)Configuration::get($os['key']);
            $order_status = new OrderState($order_state, (int)Context::getContext()->language->id);

            if (!$order_status->id || !$order_state) {
                $orderState = new OrderState();
                $orderState->name = array();
                foreach (Language::getLanguages() as $language) {
                    if (strtolower($language['iso_code']) == 'lt')
                        $orderState->name[$language['id_lang']] = $os['lang']['lt'];
                    else
                        $orderState->name[$language['id_lang']] = $os['lang']['en'];
                }
                $orderState->send_email = false;
                $orderState->color = $os['color'];
                $orderState->hidden = false;
                $orderState->delivery = false;
                $orderState->logable = true;
                $orderState->invoice = false;
                $orderState->unremovable = false;
                if ($orderState->add()) {
                    Configuration::updateValue($os['key'], $orderState->id);
                }
                else
                    return false;
            }
        }
        return true;
    }

    /**
     * Deletes module Admin controllers
     * Used for module uninstall
     *
     * @return bool Module Admin controllers deleted successfully
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function deleteTabs()
    {
        $tabs = $this->getModuleTabs();

        if (empty($tabs)) {
            return true; // Nothing to remove
        }

        foreach (array_keys($tabs) as $controller) {
            $idTab = (int) Tab::getIdFromClassName($controller);
            $tab = new Tab((int) $idTab);

            if (!Validate::isLoadedObject($tab)) {
                continue; // Nothing to remove
            }

            if (!$tab->delete()) {
                $this->displayError($this->l('Error while uninstalling tab') . ' ' . $tab->name);
                return false;
            }
        }

        return true;
    }

    /**
     * Module uninstall function
     */
    public function uninstall()
    {
        $cDb = new OmnivaIntDb();

        $cDb->deleteTables();
        $this->deleteTabs();

        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    /**
     * Creates a shipping method
     */
    public function createCarrier($key, $name, $image = '')
    {
        $carrier = new Carrier();
        $carrier->name = $name;
        $carrier->delay[Configuration::get('PS_LANG_DEFAULT')] = '1-2 business days';
        $carrier->is_module = true;
        $carrier->external_module_name = $this->name;
        $carrier->need_range = true;
        $carrier->range_behavior = 0;
        $carrier->shipping_external = true;
        $carrier->shipping_handling = false;
        $carrier->url = '';
        $carrier->active = true;
        $carrier->deleted = 0;

        if (!$carrier->add()) {
            return false;
        }

        $groups = Group::getGroups(true);
        foreach ($groups as $group) {
            Db::getInstance()->insert('carrier_group', array(
                'id_carrier' => (int) $carrier->id,
                'id_group' => (int) $group['id_group']
            ));
        }

        $rangePrice = new RangePrice();
        $rangePrice->id_carrier = (int) $carrier->id;
        $rangePrice->delimiter1 = '0';
        $rangePrice->delimiter2 = '1000';
        $rangePrice->add();

        $rangeWeight = new RangeWeight();
        $rangeWeight->id_carrier = (int) $carrier->id;
        $rangeWeight->delimiter1 = '0';
        $rangeWeight->delimiter2 = '1000';
        $rangeWeight->add();

        $zones = Zone::getZones(true);
        foreach ($zones as $zone) {
            Db::getInstance()->insert(
                'carrier_zone',
                array('id_carrier' => (int) $carrier->id, 'id_zone' => (int) $zone['id_zone'])
            );
            Db::getInstance()->insert(
                'delivery',
                array('id_carrier' => (int) $carrier->id, 'id_range_price' => (int) $rangePrice->id, 'id_range_weight' => NULL, 'id_zone' => (int) $zone['id_zone'], 'price' => '0')
            );
            Db::getInstance()->insert(
                'delivery',
                array('id_carrier' => (int) $carrier->id, 'id_range_price' => NULL, 'id_range_weight' => (int) $rangeWeight->id, 'id_zone' => (int) $zone['id_zone'], 'price' => '0')
            );
        }
        try {
            $image_path = self::$_moduleDir . 'views/images/' . $image;
            $image_path = (empty($image)) ? self::$_moduleDir . 'logo.png' : $image_path;

            copy($image_path, _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
        } catch (Exception $e) {
        }

        Configuration::updateValue($key, $carrier->id);
        Configuration::updateValue($key . '_REFERENCE', $carrier->id);

        return true;
    }

    /**
     * Deletes a shipping method
     */
    public function deleteCarrier($key)
    {
        $carrier = new Carrier((int) (Configuration::get($key)));
        if (!$carrier) {
            return true; // carrier doesnt exist, no further action needed
        }

        if (Configuration::get('PS_CARRIER_DEFAULT') == (int) $carrier->id) {
            $this->updateDefaultCarrier();
        }

        $carrier->active = 0;
        $carrier->deleted = 1;

        if (!$carrier->update()) {
            return false;
        }

        return true;
    }

    /**
     * Change default carrier when deleting carrier
     */
    private function updateDefaultCarrier()
    {
        $cHelper = new MjvpHelper();

        $carriers = $cHelper->getAllCarriers();
        foreach ($carriers as $carrier) {
            if ($carrier['external_module_name'] != $this->name && $carrier['active'] && !$carrier['deleted']) {
                Configuration::updateValue('PS_CARRIER_DEFAULT', $carrier['id_carrier']);
                break;
            }
        }
    }

    /**
     * Create other configuration values in database
     */
    private function createOtherDbRecords()
    {
        foreach ($this->_configKeysOther as $item => $data ) {
            $data_value = (is_array($data['default_value'])) ? json_encode($data['default_value']) : $data['default_value'];
            if (!Configuration::hasKey($data['key'])) {
                Configuration::updateValue($data['key'], $data_value);
            }
        }

        return true;
    }

    /**
     * Get terminals for all countries
     */
    private function updateTerminals()
    {
        $cFiles = new MjvpFiles();
        $cFiles->updateCountriesList();
        $cFiles->updateTerminalsList();
    }

    /**
     * Create module database tables
     */
    public function createDbTables()
    {
        try {
            $cDb = new OmnivaIntDb();

            $result = $cDb->createTables();
        } catch (Exception $e) {
            $result = false;
        }
        return $result;
    }

    public function getOrderShippingCost($params, $shipping_cost) {
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params) {
        return true;
    }

    public function createCategoriesSettings()
    {
        $categories = Category::getSimpleCategories($this->context->language->id);
        foreach($categories as $category)
        {
            $omnivaCategory = new OmnivaIntCategory();
            $omnivaCategory->id_category = $category['id_category'];
            $omnivaCategory->weight = 0;
            $omnivaCategory->length = 0;
            $omnivaCategory->width = 0;
            $omnivaCategory->height = 0;
            $omnivaCategory->active = 1;
            $omnivaCategory->add();
        }   
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::CONTROLLER_OMNIVA_SETTINGS));
    }

    /**
     * Display menu in module configuration
     */
    public function displayConfigMenu()
    {
        $menu = array(
            array(
                'label' => $this->l('Module settings'),
                'url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
                'active' => Tools::getValue('controller') == 'AdminModules'
            ),
        );

        $this->context->smarty->assign(array(
            'moduleMenu' => $menu
        ));

        return $this->context->smarty->fetch(self::$_moduleDir . 'views/templates/admin/configs_menu.tpl');
    }

    /**
     * Display API section in module configuration
     */
    public function displayConfigApi()
    {
        
        $section_id = 'API';

        $switcher_values = array(
            array(
                'id' => 'active_on',
                'value' => 1,
                'label' => $this->l('Yes')
            ),
            array(
                'id' => 'active_off',
                'value' => 0,
                'label' => $this->l('No')
            )
        );

        $form_fields = array(
            array(
                'type' => 'text',
                'label' => $this->l('API Token'),
                'name' => $this->getConfigKey('token', $section_id),
                'size' => 20,
                'required' => true
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Test Mode'),
                'name' => $this->getConfigKey('test_mode', $section_id),
                'desc' => $this->l('Use test mode if you have test token to test your integration.'),
                'values' => $switcher_values
            ),
        );

        return $this->displayConfig($section_id, $this->l('API Settings'), $form_fields, $this->l('Save API settings'));
    }

    /**
     * Build section display in module configuration
     */
    public function displayConfig($section_id, $section_title, $form_fields = array(), $submit_title = '')
    {
        $fieldsForm[0]['form'] = array(
            'legend' => array(
                'title' => $section_title,
            ),
            'input' => $form_fields,
            'submit' => array(
                'title' => (!empty($submit_title)) ? $submit_title : $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ),
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->bootstrap = true;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name . strtolower($section_id);
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load saved settings
        if (isset($this->_configKeys[strtoupper($section_id)])) {
            foreach ($this->_configKeys[strtoupper($section_id)] as $key) {
                $prefix = '';
                if(strpos($key, 'MJVP_COURIER_DELIVERY_TIME_') !== false)
                    $prefix = '_ON';

                $value = Configuration::get($key);
                $helper->fields_value[$key . $prefix] = $value;
            }
        }

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Save section values in module configuration
     */
    public function saveConfig($section_id, $success_message = '')
    {
        $output = null;
        foreach ($this->_configKeys[strtoupper($section_id)] as $key) {
            $value = Tools::getValue($key);

            if (is_array($value)) {
                $value = implode(';', $value);
            }
            Configuration::updateValue($key, strval($value));
        }
        $success_message = (!empty($success_message)) ? $success_message : $this->l('Settings updated');
        $output .= $this->displayConfirmation($success_message);

        return $output;
    }

    /**
     * Validate section values in module configuration
     */
    protected function validateConfig($section_id)
    {
        $section_id = strtoupper($section_id);

        $errors = array();
        $txt_required = $this->l('is required');

        if ($section_id == 'API') {
            if (empty(Tools::getValue($cModuleConfig->getConfigKey('username', 'API')))) {
                $errors[] = $this->l('API username') . ' ' . $txt_required;
            }
            if (empty(Tools::getValue($cModuleConfig->getConfigKey('password', 'API')))) {
                $errors[] = $this->l('API password') . ' ' . $txt_required;
            }
            if (empty(Tools::getValue($cModuleConfig->getConfigKey('id', 'API')))) {
                $errors[] = $this->l('API ID') . ' ' . $txt_required;
            }
        }
        if ($section_id == 'SHOP') {
            foreach ($this->_configKeys[$section_id] as $key => $key_value) {
                $configField = $this->getConfigField($section_id, $key);
                if (empty(Tools::getValue($cModuleConfig->getConfigKey($key, 'SHOP'))) && $configField['required']) {
                    $errors[] = $configField['name'] . ' ' . $txt_required;
                }
            }
        }
        if ($section_id == 'LABEL') {
            foreach ($this->_configKeys[$section_id] as $key => $key_value) {
                $configField = $this->getConfigField($section_id, $key_value);
                $field_value = Tools::getValue($cModuleConfig->getConfigKey($key, $section_id));
                if (isset($configField['type']) && $configField['type'] === 'number') {
                    $field_value = (float) $field_value;
                    if (isset($configField['min']) && $field_value < $configField['min']) {
                        $errors[] = sprintf($this->l('%s must be more then %d'), $configField['name'], $configField['min']);
                    }
                    if (isset($configField['max']) && $field_value > $configField['max']) {
                        $errors[] = sprintf($this->l('%s must be less then %d'), $configField['name'], $configField['max']);
                    }
                }
            }
        }

        if ($section_id == 'ADVANCED') {
            foreach ($this->_configKeys[$section_id] as $key => $key_value) {
                $configField = $this->getConfigField($section_id, $key_value);
                $field_value = Tools::getValue($cModuleConfig->getConfigKey($key, $section_id));
                if (isset($configField['validate'])) {
                    $validate = $configField['validate'];
                    if(!Validate::$validate($field_value))
                    {
                        $errors[] = sprintf($this->l('%s is not valid.'), $configField['name']);
                    }
                }
            }
        }

        if ($section_id == 'COURIER') {
            if(Tools::getValue('MJVP_RETURN_DAYS') && !Validate::isInt(Tools::getValue('MJVP_RETURN_DAYS')))
            {
                $errors[] = sprintf($this->l('Return days must be a positive number.'));
            }
        }

        return $errors;
    }

    /**
     * Hook for js/css files and other elements in header
     */
    public function hookHeader($params)
    {
        $address = new Address($params['cart']->id_address_delivery);
        $filtered_terminals = $this->getFilteredTerminals();

        $address_query = $address->address1 . ' ' . $address->postcode . ', ' . $address->city;
        Media::addJsDef(array(
                'mjvp_front_controller_url' => $this->context->link->getModuleLink($this->name, 'front'),
                'mjvp_carriers_controller_url' => $this->context->link->getModuleLink($this->name, 'carriers'),
                'address_query' => $address_query,
                'mjvp_translates' => array(
                    'loading' => $this->l('Loading'),
                ),
                'images_url' => $this->_path . 'views/images/',
                'mjvp_terminal_select_translates' => array(
                'modal_header' => $this->l('Pickup points map'),
                'terminal_list_header' => $this->l('Pickup points list'),
                'seach_header' => $this->l('Search around'),
                'search_btn' => $this->l('Find'),
                'modal_open_btn' => $this->l('Select a pickup point'),
                'geolocation_btn' => $this->l('Use my location'),
                'your_position' => $this->l('Distance calculated from this point'),
                'nothing_found' => $this->l('Nothing found'),
                'no_cities_found' => $this->l('There were no cities found for your search term'),
                'geolocation_not_supported' => $this->l('Geolocation is not supported'),
                'select_pickup_point' => $this->l('Select a pickup point'),
                'search_placeholder' => $this->l('Enter postcode/address'),
                'workhours_header' => $this->l('Workhours'),
                'contacts_header' => $this->l('Contacts'),
                'no_pickup_points' => $this->l('No points to select'),
                'select_btn' => $this->l('select'),
                'back_to_list_btn' => $this->l('reset search'),
                'no_information' => $this->l('No information'),
                ),
                'mjvp_terminals' => $filtered_terminals
            )
        );
    }

    public function getFilteredTerminals() 
    {
        return [];
    }


    /**
     * Hook to display block in Prestashop order edit
     */
    public function hookDisplayAdminOrder($params)
    {
    }

    public function hookActionValidateStepComplete($params)
    {
    }

    // Separate method, as methods of getting a checkout step on 1.7 are inconsistent among minor versions.
    public function check17PaymentStep($cart)
    {
        if(version_compare(_PS_VERSION_, '1.7', '>'))
        {
            $rawData = Db::getInstance()->getValue(
                'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int) $cart->id
            );
            $data = json_decode($rawData, true);
            if (!is_array($data)) {
                $data = [];
            }
            // Do not add this module extra content, if it is payment step to avoid conflicts with venipakcod.
            if((isset($data['checkout-delivery-step']) && $data['checkout-delivery-step']['step_is_complete']) &&
                (isset($data['checkout-payment-step']) && !$data['checkout-payment-step']['step_is_complete'])
            )
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Hook to display content on carrier in checkout page
     */
    public function hookDisplayCarrierExtraContent($params)
    {
    }

    /**
     * Mass label printing
     */
    public function bulkActionPrintLabels($orders_ids)
    {
    }

    private function showErrors($errors)
    {
       foreach ($errors as $key => $error) {
            $this->context->controller->errors[$key] = $error;
        } 
    }

    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        $cart = $params['cart'];
        $id_order = $order->id;
        $id_cart = $cart->id;

        $carrier = new Carrier($cart->id_carrier);
        $carrier_reference = $carrier->id_reference;
        if(!in_array($carrier_reference, Configuration::getMultiple([self::$_carriers['courier']['reference_name'], self::$_carriers['pickup']['reference_name']])))
            return;

        $cDb = new OmnivaIntDb();
        $check_order_id = $cDb->getOrderIdByCartId($id_cart);

        if (empty($check_order_id)) {

            $order_weight = $order->getTotalWeight();

            // Convert to kg, if weight is in grams.
            if(Configuration::get('PS_WEIGHT_UNIT') == 'g')
                $order_weight *= 0.001;

            $is_cod = 0;
            if(in_array($order->module, self::$_codModules))
                $is_cod = 1;

             $cDb->updateOrderInfo($id_cart, array(
                 'id_order' => $id_order,
                 'warehouse_id' => MjvpWarehouse::getDefaultWarehouse(),
                 'order_weight' => $order_weight,
                 'cod_amount' => $order->total_paid_tax_incl,
                 'is_cod' => $is_cod
             ));
        }
    }

    public function hookActionAdminControllerSetMedia()
    {
        if(Tools::getValue('configure') == $this->name)
        {
            $this->context->controller->addJs('modules/' . $this->name . '/views/js/mjvp-admin.js');
            $this->context->controller->addCSS($this->_path . 'views/css/mjvp-admin.css');
        }
    }

    public function getTerminalById($terminals, $terminal_id)
    {
        foreach ($terminals as $terminal)
        {
            if ($terminal->id == $terminal_id)
            {
                return $terminal;
            }
        }
        return false;
    }

    public function changeOrderStatus($id_order, $status)
    {
        $order = new Order((int)$id_order);
        if ($order->current_state != $status)
        {
            $history = new OrderHistory();
            $history->id_order = (int)$id_order;
            $history->id_employee = Context::getContext()->employee->id;
            $history->changeIdOrderState((int)$status, $order);
            $order->update();
            $history->add();
        }
    }

    /**
     * Use hook to validate carrier selection in Prestashop 1.6
     */
    public function hookActionCarrierProcess($params)
    {
        $data = [
            'step_name' => 'delivery',
            'cart' => $params['cart']
        ];
        $this->hookActionValidateStepComplete($data);
    }

        /**
     * Get config key from all keys list
     */
    public function getConfigKey($key_name, $section = '')
    {
        return $this->_configKeys[$section][$key_name] ?? '';
    }

    public function hookDisplayAdminOmnivaIntServicesListBefore($params)
    {
        $link = $this->context->link->getModuleLink($this->name, 'cron', ['type' => 'services', 'token' => Configuration::get('OMNIVA_CRON_TOKEN')]);
        $content = $this->l("To udate services periodically, add this CRON job to your cron table: ");

        // Something not-translatable, usually a link..
        $sugar = "<b><i>$link</i></b>";

        return $this->helper->displayAlert($content, $sugar);
    }

    public function hookDisplayAdminOmnivaIntTerminalsListBefore($params)
    {
        $link = $this->context->link->getModuleLink($this->name, 'cron', ['type' => 'terminals', 'token' => Configuration::get('OMNIVA_CRON_TOKEN')]);
        $content = $this->l("To udate terminals periodically, add this CRON job to your cron table: ");

        $sugar = "<b><i>$link</i></b>";

        return $this->helper->displayAlert($content, $sugar);
    }
}