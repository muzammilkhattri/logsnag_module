<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Logsnag extends Module
{

    public function __construct()
    {
        $this->name = 'logsnag';
        $this->tab = 'front_office';
        $this->version = '1.0.0';
        $this->bootstrap = true;
        $this->author = 'Muzammil';
        parent::__construct();

        $this->default_language = Language::getLanguage(Configuration::get('PS_LANG_DEFAULT'));
        $this->id_shop = Context::getContext()->shop->id;
        $this->displayName = $this->l('LogSnag');
        $this->description = $this->l('Easily connect Logsnag with prestashop');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionValidateOrder');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }
    public function getContent()
    {
        $output = '';

        // this part is executed only when the form is submitted
        if (Tools::isSubmit('submit' . $this->name)) {
            // retrieve the value set by the user
            $apiKey = (string) Tools::getValue('LOGSNAG_API_KEY');
            $projectName = (string) Tools::getValue("LOGSNAG_PROJECT_NAME");
            $channelName = (string) Tools::getValue('LOGSNAG_CHANNEL_NAME');
            $newOrder = (boolean) Tools::getValue('LOGSNAG_NEWORDER');



            // check that the value is valid
            if (empty($apiKey) || empty($channelName) || empty($projectName)) {
                // invalid value, show an error
                $output = $this->displayError($this->l('Invalid Configuration value'));
            } else {
                // value is ok, update it and display a confirmation message
                Configuration::updateValue('LOGSNAG_API_KEY', $apiKey);
                Configuration::updateValue('LOGSNAG_PROJECT_NAME', $projectName);
                Configuration::updateValue('LOGSNAG_CHANNEL_NAME', $channelName);
                Configuration::updateValue('LOGSNAG_NEWORDER', $newOrder);

                
                $output = $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        // display any message, then the form
        return $output . $this->displayForm();
    }

    protected function displayForm()
    {
    // Init Fields form array
    $form = [
        'form' => [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Logsnag API Key'),
                    'name' => 'LOGSNAG_API_KEY',
                    'size' => 20,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Logsnag Project Name'),
                    'name' => 'LOGSNAG_PROJECT_NAME',
                    'size' => 20,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Logsnag Channel Name'),
                    'name' => 'LOGSNAG_CHANNEL_NAME',
                    'size' => 20,
                    'required' => true,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('New Order'),
                    'name' => 'LOGSNAG_NEWORDER',
                    'desc' => $this->l('Publish event on new order'),
                    'size' => 20,
                    'required' => true,
                    'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ],
    ];

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->table = $this->table;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
    $helper->submit_action = 'submit' . $this->name;

    // Default language
    $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

    // Load current value into the form
    $helper->fields_value['LOGSNAG_API_KEY'] = Tools::getValue('LOGSNAG_API_KEY', Configuration::get('LOGSNAG_API_KEY'));
    $helper->fields_value['LOGSNAG_PROJECT_NAME'] = Tools::getValue('LOGSNAG_PROJECT_NAME', Configuration::get('LOGSNAG_PROJECT_NAME'));
    $helper->fields_value['LOGSNAG_CHANNEL_NAME'] = Tools::getValue('LOGSNAG_CHANNEL_NAME', Configuration::get('LOGSNAG_CHANNEL_NAME'));
    $helper->fields_value['LOGSNAG_NEWUSER'] = Tools::getValue('LOGSNAG_NEWUSER', Configuration::get('LOGSNAG_NEWUSER'));
    $helper->fields_value['LOGSNAG_NEWORDER'] = Tools::getValue('LOGSNAG_NEWORDER', Configuration::get('LOGSNAG_NEWORDER'));




    return $helper->generateForm([$form]);

    }
    public function publishEvent($event,$desc,$icon){
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.logsnag.com/v1/log',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "project": "'.Configuration::get('LOGSNAG_PROJECT_NAME').'",
            "channel": "'.Configuration::get('LOGSNAG_CHANNEL_NAME').'",
            "event": "'.$event.'",
            "description": "'.$desc.'",
            "icon": "'.$icon.'",
            "notify": true
        }',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.Configuration::get('LOGSNAG_API_KEY'),
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($httpcode!=200){
            Logger::addLog("Logsnag API call failed.");
        }else{
            return true;
        }
    }
    public function hookactionValidateOrder($params){
        if(Configuration::get('LOGSNAG_NEWORDER')){
            $params = $params["order"];
            $orderTotal = $params->total_paid;
            $idCustomer = $params->id_customer;

            //get customer name using idCustomer;
            $customer = New Customer($idCustomer);
            $customerName = $customer->firstname." ".$customer->lastname; 

            $event = "New order for $".round($orderTotal,2);
            $desc = "Customer Name: ".$customerName;
            $icon = "💰";
            $this->publishEvent($event,$desc,$icon);
        }
        
    }

}
