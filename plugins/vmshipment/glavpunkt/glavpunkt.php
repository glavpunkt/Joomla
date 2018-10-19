<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}
if (!class_exists('CurrencyDisplay')) {
    require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
}

class PlgvmshipmentGlavpunkt extends vmPSPlugin
{
    /** @var array вся информация о доставке */
    private $data;

    /** @var array массив ошибок */
    private $error = [];

    /**
     * PlgvmshipmentGlavpunkt constructor.
     * @param $subject
     * @param $config
     * @throws Exception
     */
    public function __construct($subject, $config)
    {
        parent::__construct($subject, $config);

        // Определяем все переменные, которые необходимы для работы
        $this->_loggable = TRUE;
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = $this->getVarsToPush();
        $this->setConvertable([]);
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        // Получаем параметры, которые указал клиент в настройках
        $this->data['cityFrom'] = isset($this->params['cityFrom']) ? $this->params['cityFrom'] : 'Санкт-Петербург';
        $this->data['paymentType'] = isset($this->params['paymentType']) ? $this->params['paymentType'] : 'cash';

        // Получаем корзину
        $cart = VirtueMartCart::getCart();
        /**
         * Получаем массив адреса
         *
         * Array
         * [
         *  [email] => mr.chepikov@gmail.com
         *  [company] =>
         *  [title] => Mr
         *  [first_name] => Serge
         *  [middle_name] => Andrew
         *  [last_name] => Men
         *  [address_1] => Литовская 15
         *  [address_2] =>
         *  [zip] => 197000
         *  [city] => Санкт-Петербург
         *  [virtuemart_country_id] => 176
         *  [virtuemart_state_id] => 627
         *  [phone_1] =>
         *  [phone_2] =>
         *  [fax] =>
         * ]
         */
        $address = $cart->getST();

        // Получаем город и индекс из заполненного пользователем ранее
        $cityTo = $address['city'] !== ''
            ? $address['city']
            : 'Санкт-Петербург';

        $zip = $address['zip'] !== ''
            ? $address['zip']
            : '197000';

        // Получаем переменные, из POST запроса или же из сессии

        $this->getFromPOSTorSession('cityTo', $cityTo);
        $this->getFromPOSTorSession('selectedDate');
        $this->getFromPOSTorSession('selectedInterval', '10:00 - 18:00');
        $this->getFromPOSTorSession('method', 'courier');
        $this->getFromPOSTorSession('zip', $zip);
        $this->getFromPOSTorSession(
            'address',
            $zip . ' ' . $cityTo . ' ' . $address['address_1'] . ' ' . $address['address_2']
        );
        $this->getFromPOSTorSession('pvzCity', 'SPB');
        $this->getFromPOSTorSession('pvzId', 'Avtovo-S75');

    }

    /**
     * Мы получаем значение из POST и заполняем переменную объекта
     *
     * @param string $var название переменное, по которому мы будем его хранить и использовать
     * @param string $default значение переменной по умолчанию
     */
    private function getFromPOSTorSession($var, $default = '')
    {
        if (JFactory::getSession()->get($var, $default, __CLASS__) !== '') {
            $cachedData = JFactory::getSession()->get($var, $default, __CLASS__);
        } else {
            $cachedData = $default;
        }
        $this->data[$var] = vRequest::get(
            $var,
            $cachedData
        );
        JFactory::getSession()->set($var, $this->data[$var], __CLASS__);
    }

    /**
     * plgVmOnCheckAutomaticSelected
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    public function plgVmOnCheckAutomaticSelectedShipment(VirtueMartCart $cart, array $cart_prices, &$shipCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $shipCounter);
    }

    /**
     * Вывод описания пункта выдачи
     *
     * @param string $pvzId идентификатор пункта выдачи
     * @param string $cityId идентификатор города пункта выдачи
     * @return string
     */
    private function getPVZDescription($pvzId, $cityId)
    {
        $desc = '';
        if (in_array($cityId, ['SPB', 'MSK'])) {
            $pvzList = json_decode($this->curlRequest('https://glavpunkt.ru/api/punkts'), true);
        } else {
            $pvzList = json_decode($this->curlRequest('https://glavpunkt.ru/punkts-rf.json'), true);
        }

        foreach ($pvzList as $punkt) {
            if ($punkt['id'] === $pvzId) {
                $desc .= ' Пункт выдачи ' . (isset($punkt['brand']) ? $punkt['brand'] : "Главпункт") . '<br>';
                $desc .= ' Город: ' . $punkt['city'] . '<br>';
                $desc .= ' Адрес: ' . (isset($punkt['metro']) ? $punkt['metro'] . ", " : "") . $punkt['address'] . '<br>';
                $desc .= ' Телефон: ' . $punkt['phone'] . '<br>';
                $desc .= ' Время работы: ' . $punkt['work_time'] . '<br>';
            }
        }

        return $desc;
    }

    /**
     * При выборе посчитанной цены доставки
     *
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param                $cart_prices_name
     * @return bool|null
     */
    public function plgVmOnSelectedCalculatePriceShipment(
        VirtueMartCart $cart,
        array &$cart_prices,
        &$cart_prices_name
    ) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * Вывод цены
     *
     * @param VirtueMartCart $cart
     * @param                $method
     * @param                $cart_prices
     * @return int
     */
    public function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        switch ($this->data['method']) {
            case "courier":
                return $this->getCourierCost();
                break;
            case "post":
                return $this->getPostCost();
                break;
            case "punkts":
                return $this->getPunktsCost();
                break;
            default:
                return 0;
        }
    }

    /**
     * Вывод цены курьерской доставки
     *
     * @return int
     */
    private function getCourierCost()
    {
        if ($this->data['cityTo'] === 'Санкт-Петербург' && trim($this->params['spbDeliveryPrice']) !== '') {
            return $this->params['spbDeliveryPrice'];
        }
        if ($this->data['cityTo'] === 'Москва' && trim($this->params['mskDeliveryPrice']) !== '') {
            return $this->params['mskDeliveryPrice'];
        }

        return $this->calculateCourierDelivery();
    }

    /**
     * Вывод цены доставки до пунктов выдачи
     *
     * @return int
     */
    private function getPunktsCost()
    {
        if ($this->data['pvzCity'] === 'SPB' && trim($this->params['spbPunktsPrice']) !== '') {
            return $this->params['spbPunktsPrice'];
        }
        if ($this->data['pvzCity'] === 'MSK' && trim($this->params['mskPunktsPrice']) !== '') {
            return $this->params['mskPunktsPrice'];
        }

        return $this->calculatePunktsDelivery();
    }

    /**
     * Вывод цены доставки Почты РФ
     *
     * @return int
     */
    private function getPostCost()
    {
        return $this->calculatePostDelivery();
    }

    /**
     * plgVmDisplayListFE
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on success, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEShipment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * Проверка условий
     *
     * Если по одному из условий не подходят, доставка не выводится
     *
     * @param VirtueMartCart $cart
     * @param int $method
     * @param array $cart_prices
     * @return bool
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        // Если вес превышает 20 кг, то доставка в любом случае доступна не будет
        if ((int)$this->getOrderWeight($cart, 'KG') > 20) {
            return false;
        }

        return true;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallShipmentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @param VirtueMartCart $cart
     * @return null
     */
    public function plgVmOnSelectCheckShipment(VirtueMartCart &$cart)
    {
        return $this->OnSelectCheck($cart);
    }


    /**
     * Вывод кода плагина
     *
     * @param $plugin
     * @param $selectedPlugin
     * @param $pluginSalesPrice
     * @return string
     */
    protected function getPluginHtml($plugin, $selectedPlugin, $pluginSalesPrice)
    {
        $html = parent::getPluginHtml($plugin, $selectedPlugin, $pluginSalesPrice);

        if (!$this->isAjax()) {
            vmJsApi::jQuery();
            $doc = JFactory::getDocument();
            $reg = new \Joomla\Registry\Registry(JPluginHelper::getPlugin(
                'vmshipment',
                'glavpunkt'
            )->params);

            $doc->addScript('https://glavpunkt.ru/js/punkts-widget/glavpunkt.js');
            $doc->addScript(JUri::root(true) . '/plugins/vmshipment/glavpunkt/assets/js/script.js');
            $doc->addStyleSheet(JUri::root(true) . '/plugins/vmshipment/glavpunkt/assets/css/style.css');
        }

        $cart = VirtueMartCart::getCart();

        $answerGetCities = json_decode(
            $this->curlRequest('https://glavpunkt.ru/api/get_courier_cities'),
            true
        );

        $data = [
            'id' => '',
            'method' => $this->data['method'],
            'cityFrom' => $this->data['cityFrom'],
            'cityTo' => $this->data['cityTo'],
            'price' => $cart->cartPrices['salesPrice'],
            'weight' => (int)$this->getOrderWeight($cart, 'KG'),
            'paymentType' => $this->data['paymentType'],
            'basePath' => __DIR__,
            'courier' => [
                'minDate' => date('Y-m-d', time() + 86400),
                'maxDate' => date('Y-m-d', time() + 86400 * 30),
                'selectedDate' => $this->data['selectedDate'],
                'selectedInterval' => $this->data['selectedInterval'],
                'intervals' => [
                    '10:00 - 18:00',
                    '10:00 - 14:00',
                    '11:00 - 14:00',
                    '12:00 - 15:00',
                    '13:00 - 16:00',
                    '14:00 - 17:00',
                    '15:00 - 18:00',
                ],
                'cities' => $answerGetCities
            ],
            'post' => [
                'zip' => $this->data['zip'],
                'address' => $this->data['address'],
            ],
            'punkts' => [
                'selectedPVZ' => $this->getPVZDescription($this->data['pvzId'], $this->data['pvzCity']),
            ]
        ];
        $error = array_unique($this->error);

        $html .= JLayoutHelper::render(
            'cart.template',
            compact('data' , 'error'),
            __DIR__ . '/tmpl/'
        );

        return $html;
    }

    /**
     * CURL запрос
     *
     * @param $url
     * @return bool|string
     */
    private function curlRequest($url)
    {
        $timeout = 3;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $file_contents = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            return false;
        } else {
            return $file_contents;
        }
    }

    /**
     * Проверка, является ли модуль загруженным через Ajax запрос
     *
     * @return bool
     */
    private function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /**
     * Расчёт курьерской доставки
     *
     * @return int
     */
    private function calculateCourierDelivery()
    {
        $cart = VirtueMartCart::getCart();

        if (!isset($cart->cartPrices['salesPrice'])) {
            $this->error[] = 'Не передана сумма заказа';
            return 0;
        }

        $url = 'https://glavpunkt.ru/api/get_tarif' .
            '?serv=' . 'курьерская доставка' .
            '&cms=joomla' .
            '&cityFrom=' . $this->data['cityFrom'] .
            '&cityTo=' . $this->data['cityTo'] .
            '&weight=' . (int)$this->getOrderWeight($cart, 'KG') .
            '&paymentType=' . $this->data['paymentType'] .
            '&price=' . $cart->cartPrices['salesPrice'];
        $result = json_decode($this->curlRequest($url), true);
        if ($result['result'] !== 'ok') {
            $this->error[] = $result['message'];
            return 0;
        }

        return (int)$result['tarif'];
    }

    /**
     * Расчёт доставки Почты РФ
     *
     * @return int
     */
    private function calculatePostDelivery()
    {
        $cart = VirtueMartCart::getCart();

        if (!isset($cart->cartPrices['salesPrice'])) {
            $this->error[] = 'Не передана сумма заказа';
            return 0;
        }

        switch ($this->data['cityFrom']) {
            case 'Москва':
                $cityFrom = 'MSK';
                break;
            case 'Санкт-Петербург':
                $cityFrom = 'SPB';
                break;
            default:
                $this->error[] = 'Для города ' . $this->data['cityFrom'] . ' не предусмотрена отправка Почтой';
                return 0;
                break;
        }

        $data = [
            'cms' => 'joomla',
            'cityFrom' => $cityFrom,
            'index' => $this->data['zip'],
            'address' => $this->data['address'],
            'weight' => (int)$this->getOrderWeight($cart, 'KG'),
            'price' => $cart->cartPrices['salesPrice'],
            'paymentType' => $this->data['paymentType'],
        ];

        $url = 'https://glavpunkt.ru/api/get_pochta_tarif?' . http_build_query($data);
        $result = json_decode($this->curlRequest($url), true);
        if ($result['result'] !== 'ok') {
            $this->error[] = $result['message'];
            return 0;
        }

        return (int)$result['tarifTotal'];
    }

    /**
     * Расчёт цены доставки до пунктов выдачи
     *
     * @return int
     */
    private function calculatePunktsDelivery()
    {
        $cart = VirtueMartCart::getCart();

        if (!isset($cart->cartPrices['salesPrice'])) {
            $this->error[] = 'Не передана сумма заказа';
            return 0;
        }

        $url = 'https://glavpunkt.ru/api/get_tarif' .
            '?serv=' . 'выдача' .
            '&cms=joomla' .
            '&cityFrom=' . $this->data['cityFrom'] .
            '&cityTo=' . $this->data['pvzCity'] .
            '&weight=' . (int)$this->getOrderWeight($cart, 'KG') .
            '&paymentType=' . $this->data['paymentType'] .
            '&price=' . $cart->cartPrices['salesPrice'] .
            '&punktId=' . $this->data['pvzId'];
        $result = json_decode($this->curlRequest($url), true);
        if ($result['result'] !== 'ok') {
            $this->error[] = $result['message'];
            return 0;
        }

        return (int)$result['tarif'];
    }

    /**
     * Определение тела запроса для создания таблицы
     *
     * @return string
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Shipment with Glavpunkt');
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the shipment-specific data.
     *
     * @param integer $virtuemart_order_id The order ID
     * @param integer $virtuemart_shipmentmethod_id The selected shipment method id
     * @param string $shipment_name Shipment Name
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     * @author Valérie Isaksen
     * @author Max Milbers
     */
    public function plgVmOnShowOrderFEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
    }

    /**
     * Сущности таблицы плагина
     *
     * @return array
     */
    public function getTableSQLFields()
    {
        return [
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'order_number' => 'char(32)',
            'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED',
            'shipment_name' => 'varchar(5000)',
            'order_weight' => 'decimal(10,4)',
            'shipment_weight_unit' => 'char(3) DEFAULT \'KG\'',
            'shipment_cost' => 'decimal(10,2)',
            'shipment_package_fee' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)',
            'glavpunkt_method' => 'varchar(20)',
            'glavpunkt_delivery_date' => 'varchar(10)',
            'glavpunkt_delivery_interval' => 'varchar(13)',
            'glavpunkt_cityto' => 'varchar(200)',
            'glavpunkt_pvz_id' => 'varchar(200)',
            'glavpunkt_city_id' => 'varchar(200)',
            'glavpunkt_zip' => 'int(6)',
            'glavpunkt_address' => 'varchar(500)',
            'glavpunkt_pvz_description' => 'varchar(500)',
        ];
    }

    /**
     * Вывод информации о выбранном способе доставки
     *
     * @param $virtuemart_order_id
     * @param $virtuemart_shipmentmethod_id
     * @return null|string
     */
    public function plgVmOnShowOrderBEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id)
    {
        if (!($this->selectedThisByMethodId($virtuemart_shipmentmethod_id))) {
            return null;
        }

        $html = $this->getOrderShipmentHtml($virtuemart_order_id);

        return $html;
    }

    /**
     * Вывод информации о выбранном способе доставки
     *
     * @param $virtuemart_order_id
     * @return string
     */
    public function getOrderShipmentHtml($virtuemart_order_id)
    {
        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
            . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if (!($shipinfo = (array)$db->loadObject())) {
            return '';
        }

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();

        switch ($shipinfo['glavpunkt_method']) {
            case "courier":
                $html .= $this->getHtmlRowBE(
                    'Доставка службой',
                    'курьерская доставка в город' . $shipinfo['glavpunkt_cityto']
                );
                $html .= $this->getHtmlRowBE('Желаемая дата доставки', $shipinfo['glavpunkt_delivery_date']);
                $html .= $this->getHtmlRowBE('Желаемый интервал доставки', $shipinfo['glavpunkt_delivery_interval']);
                break;
            case "post":
                $html .= $this->getHtmlRowBE('Доставка службой', 'Доставка Главпункт Почтой РФ');
                $html .= $this->getHtmlRowBE('Индекс получателя', $shipinfo['glavpunkt_zip']);
                $html .= $this->getHtmlRowBE('Адрес получателя', $shipinfo['glavpunkt_address']);
                break;
            case "punkts":
                $html .= $this->getHtmlRowBE('Доставка службой', 'до пункта выдачи');
                $html .= $this->getHtmlRowBE(
                    'Пункт выдачи:',
                    $this->getPVZDescription($shipinfo['glavpunkt_pvz_id'], $shipinfo['glavpunkt_city_id'])
                );
                break;
            default:
                break;
        }
        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * Передача данных о доставке в таблицу в БД
     *
     * @param VirtueMartCart $cart
     * @param $order
     * @return bool|null
     */
    public function plgVmConfirmedOrder(VirtueMartCart $cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_shipmentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->shipment_element)) {
            return false;
        }
        $values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
        $values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
        $values['order_number'] = $order['details']['BT']->order_number;
        $values['order_weight'] = $this->getOrderWeight($cart, $method->weight_unit);
        $values['shipment_name'] = $this->renderPluginName($method);
        $values['shipment_weight_unit'] = "KG";
        $values['glavpunkt_delivery_date'] = $this->data['selectedDate'];
        $values['glavpunkt_delivery_interval'] = $this->data['selectedInterval'];
        $values['glavpunkt_cityto'] = $this->data['cityTo'];
        $values['glavpunkt_method'] = $this->data['method'];
        $values['glavpunkt_pvz_id'] = $this->data['pvzId'];
        $values['glavpunkt_city_id'] = $this->data['pvzCity'];
        $values['glavpunkt_zip'] = $this->data['zip'];
        $values['glavpunkt_address'] = $this->data['address'];

        $this->storePSPluginInternalData($values);

        return true;
    }
}