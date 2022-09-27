<?php

require_once "AdminOmnivaIntBaseController.php";

use OmnivaApi\API;
use OmnivaApi\Exception\OmnivaApiException;

class AdminOmnivaIntServicesController extends AdminOmnivaIntBaseController
{
    /**
     * AdminOmnivaIntCategories class constructor
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->title_icon = 'icon-server';
        $this->list_no_link = true;
        $this->_orderBy = 'id';
        $this->className = 'OmnivaIntService';
        $this->table = 'omniva_int_service';
        $this->identifier = 'id';
        $this->tpl_folder = 'override/';

        $this->_error = [
            1 => $this->module->l('You cannot assign categories to this service. Please enable the category mangment first.'),
            2 => $this->module->l('This service cannot be assigned own logins.'),
        ];
    }

    public function init()
    {
        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
            $this->errors[] = $this->module->l('Select shop');
        } else {
            if($this->api)
                $this->checkNewServices();
            $this->serviceList();
        }
        parent::init();
    }

    protected function serviceList()
    {
        $this->fields_list = [
            'name' => [
                'title' => $this->module->l('Name'),
                'align' => 'text-center',
                'filter_key' => 'name'
            ],
            'service_code' => [
                'type' => 'text',
                'title' => $this->module->l('Service Code'),
                'align' => 'center',
            ],
            'image' => [
                'title' => $this->module->l('Image'),
                'callback' => 'formatImage',
                'align' => 'center',
                'search' => false
            ],
            'cod' => [
                'type' => 'bool',
                'title' => $this->module->l('COD'),
                'align' => 'center',
            ],
            'insurance' => [
                'type' => 'bool',
                'title' => $this->module->l('Insurance'),
                'align' => 'center',
            ],
            'carry_service' => [
                'type' => 'bool',
                'title' => $this->module->l('Carry Service'),
                'align' => 'center',
            ],
            'doc_return' => [
                'type' => 'bool',
                'title' => $this->module->l('Document Return'),
                'align' => 'center',
            ],
            'own_login' => [
                'type' => 'bool',
                'title' => $this->module->l('Own Login'),
                'align' => 'center',
            ],
            'fragile' => [
                'type' => 'bool',
                'title' => $this->module->l('Fragile'),
                'align' => 'center',
            ],
            'parcel_terminal_type' => [
                'type' => 'text',
                'title' => $this->module->l('Parcel Terminal Type'),
                'align' => 'center',
            ],
            'manage_categories' => [
                'type' => 'bool',
                'title' => $this->module->l('Manage Categories'),
                'active' => 'status',
                'align' => 'center',
            ],
            'active' => [
                'type' => 'bool',
                'title' => $this->module->l('Active'),
                'active' => 'active',
                'align' => 'center',
            ],
        ];

        $this->actions = ['manageCategories', 'manageLogins'];
    }

        /**
     * Display edit action link.
     */
    public function displayManageCategoriesLink($token, $id, $name = null)
    {
        $omnivaService = new OmnivaIntService($id);
        if(!$omnivaService->manage_categories)
            return false;
        if (!array_key_exists('Manage Categories', self::$cache_lang)) {
            self::$cache_lang['Manage Categories'] = $this->module->l('Manage Categories');
        }
        $this->context->smarty->assign([
            'href' => self::$currentIndex . '&action=categories&token=' . $this->token . '&id=' . $id,
            'action' => $this->module->l('Manage Categories'),
            'id' => $id,
            'icon' => 'sun'
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/list_action.tpl');
    }

    public function displayManageLoginsLink($token, $id, $name = null)
    {
        $omnivaService = new OmnivaIntService($id);
        if(!$omnivaService->own_login)
            return false;
        if (!array_key_exists('Manage Logins', self::$cache_lang)) {
            self::$cache_lang['Manage Logins'] = $this->module->l('Manage Categories');
        }
        $this->context->smarty->assign([
            'href' => self::$currentIndex . '&action=logins&token=' . $this->token . '&id=' . $id,
            'action' => $this->module->l('Manage Logins'),
            'id' => $id,
            'icon' => 'cube'
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/list_action.tpl');
    }

    public function initToolbar()
    {
        $this->toolbar_btn['bogus'] = [
            'href' => '#',
            'desc' => $this->module->l('Back to list'),
        ];
    }

    public function formatImage($image)
    {
        return "<img src='$image'></img>";
    }

    public function initPageHeaderToolbar()
    {
        if(Configuration::get('OMNIVA_TOKEN'))
        {
            $this->page_header_toolbar_btn['sync_services'] = [
                'href' => self::$currentIndex . '&sync_services=1&token=' . $this->token . '&cron_token=' . Configuration::get('OMNIVA_CRON_TOKEN'),
                'desc' => $this->module->l('Update Services'),
                'imgclass' => 'refresh',
            ];
        }
        parent::initPageHeaderToolbar();
    }

    public function postProcess()
    {
        parent::postProcess();
        if(Tools::getValue('sync_services'))
        {
            $this->updateServices();
        }
        if(Tools::isSubmit('active' . $this->table))
        {
            $this->loadObject()->toggleActive();
        }
    }

    public function updateServices()
    {
        $updater = new OmnivaIntUpdater('services');
        try {
            if($updater->run())
                $this->confirmations[] = $this->module->l('Successfully updated services');
            else
                $this->errors[] = $this->module->l("Failed updating services");
        }
        catch (OmnivaApiException $e)
        {
            $this->errors[] = $e->getMessage();
        }
    }

    public function checkNewServices()
    {
        if(!Tools::isSubmit('sync_services'))
        {
            $updater = new OmnivaIntUpdater('check_services');
            try {
                if($updater->run())
                    $this->warnings[] = $this->module->l('There are new services in carrier service provider API. Please press "Update Services" button to download new services.');  
            }
            catch (OmnivaApiException $e)
            {
                $this->errors[] = $e->getMessage();
            }
        }
    }

    public function processCategories()
    {
        $this->display = 'edit';
        $this->loadObject();

        // nope...
        if(!$this->object->manage_categories)
        {
            Tools::redirectAdmin(self::$currentIndex . '&error=1&token=' . $this->token);
        }

        $this->fields_form = [
            'legend' => [
                'title' => $this->module->l('Edit Categories for Service ') . $this->object->name,
                'icon' => 'icon-glass',
            ],
            'input' => [
                [
                    'type' => 'categories',
                    'label' => $this->module->l('Carrier Name'),
                    'name' => 'service_categories',
                    'tree' => [
                        'id' => 'categories-tree',
                        'selected_categories' => OmnivaIntServiceCategory::getServiceCategories($this->object->id),
                        'root_category' => 2,
                        'use_search' => true,
                        'use_checkbox' => true,
                    ],
                ],
                [
                    'type' => 'hidden',
                    'name' => 'action',
                    'value' => 'categories'
                ],
            ],
        ];

        $this->fields_form['submit'] = [
            'title' => $this->module->l('Save'),
        ];

        if(Tools::getValue('submitAddomniva_int_service'))
        {
            $this->mapServiceToCategories();
        }
    }

    public function mapServiceToCategories()
    {
        $service_categories = Tools::getValue('service_categories');

        // Add whatever is submited (if user didn't change anything, old categories should persist)

        if(!$service_categories)
            $service_categories = [];

        if($this->object)
        {
            $existing_categories = OmnivaIntServiceCategory::getServiceCategories($this->object->id);

            // These categories will be mapped to the service in this process (exists in new set, but not in the old set)
            $selected_categories = array_diff($service_categories, $existing_categories);

            // These categories were un-selected and will be unlinked form the service (does not exist in the new set, but exists in the old one)
            $unselected_categories = array_diff($existing_categories, $service_categories);
            // dump($unselected_categories); die();
            foreach($selected_categories as $service_category)
            {
                $omnivaServiceCategory = new OmnivaIntServiceCategory();
                $omnivaServiceCategory->id_service = $this->object->id;
                $omnivaServiceCategory->id_category = $service_category;
                $omnivaServiceCategory->add();
            }
            foreach($unselected_categories as $unselected_category)
            {
                $omnivaServiceCategoryId = OmnivaIntServiceCategory::getServiceCategoryId($this->object->id, $unselected_category);
                if((int)$omnivaServiceCategoryId > 0)
                {
                    $omnivaServiceCategory = new OmnivaIntServiceCategory($omnivaServiceCategoryId);
                    if(Validate::isLoadedObject($omnivaServiceCategory))
                    {
                        $omnivaServiceCategory->delete();
                    }
                }
            }
            $this->redirect_after = self::$currentIndex . '&conf=4&action=categories&token=' . $this->token . '&id=' . $this->object->id;
        }
    }

    public function processLogins()
    {
        $this->display = 'edit';
        $this->submit_action = 'submitLogins';
        $this->loadObject();

        if(!$this->object->own_login)
        {
            Tools::redirectAdmin(self::$currentIndex . '&error=2&token=' . $this->token);
        }

        $this->fields_form = [
            'legend' => [
                'title' => $this->module->l('Edit login credentials for service ') . $this->object->name,
                'icon' => 'icon-berry',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->module->l('User'),
                    'name' => 'user',
                ],
                [
                    'type' => 'text',
                    'label' => $this->module->l('Password'),
                    'name' => 'password',
                ],
                [
                    'type' => 'hidden',
                    'name' => 'action',
                    'value' => 'logins'
                ],
            ],
        ];

        $this->fields_form['submit'] = [
            'name' => 'serviceLogin',
            'title' => $this->module->l('Save'),
        ];

        if(Tools::getValue('submitLogins'))
        {
            parent::processUpdate();
        }
    }

    public function initProcess()
    {
        parent::initProcess();
        if(Tools::getValue('submitAddomniva_int_service'))
        {
            $this->action = 'categories';
            $this->display = 'edit';
        }

    }
}