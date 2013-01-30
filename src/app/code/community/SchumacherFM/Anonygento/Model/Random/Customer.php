<?php
/**
 * @category    SchumacherFM_Anonygento
 * @package     Model
 * @author      Cyrill at Schumacher dot fm
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @bugs        https://github.com/SchumacherFM/Anonygento/issues
 */
class SchumacherFM_Anonygento_Model_Random_Customer extends SchumacherFM_Anonygento_Model_Random_AbstractWeird
{

    /**
     * @var array
     */
    protected $_street = array();

    /**
     * @var Varien_Object
     */
    protected $_currentCustomer = NULL;

    public function _construct()
    {
        parent::_construct();
        $this->_street = $this->_loadFile('Street');
        $this->_init();
    }

    /**
     * inits the random customer
     */
    protected function  _init()
    {
        mt_srand(microtime(TRUE) * 0xFFFF);
        $this->setCustomerPrefix(mt_rand() % 2);
        $this->_currentCustomer = new Varien_Object();
    }

    /**
     * @return Varien_Object
     */
    public function getCustomer()
    {
        $this->_init();
        $data = array(
            'prefix'     => $this->_getCustomerPrefixString(),
            'suffix'     => '',
            'firstname'  => $this->_getCustomerFirstName(),
            'middlename' => $this->_getCustomerFirstName(),
            'lastname'   => $this->_getCustomerLastName(),
            'company'    => '',
            'taxvat'     => '',
            'dob'        => $this->_getCustomerDob(),
            'street'     => $this->_getCustomerStreet(),
            'telephone'  => $this->_getCustomerTelephone(),
            'fax'        => $this->_getCustomerTelephone(),
            'remote_ip'  => $this->_getCustomerIp(),
        );

        $this->_currentCustomer->addData($data);

        $this->_currentCustomer->setName(
            $this->_currentCustomer->getFirstname() . ' ' . $this->_currentCustomer->getLastname()
        );
        $this->_currentCustomer->setName2(
            $this->_currentCustomer->getFirstname() . ' ' . $this->_currentCustomer->getMiddlename() . ' ' . $this->_currentCustomer->getLastname()
        );

        $this->_getRandEmail();

        return $this->_currentCustomer;
    }

    /**
     * @return string
     */
    protected function _getCustomerIp()
    {
        $ip = array(
            mt_rand(1, 255),
            mt_rand(1, 255),
            mt_rand(1, 255),
            mt_rand(1, 255),
        );
        return implode('.', $ip);
    }

    /**
     * @return string
     */
    protected function _getCustomerStreet()
    {
        return $this->_street[mt_rand() % count($this->_street)] . ' ' . mt_rand(1, 200);
    }

    /**
     * @return string
     */
    protected function _getCustomerTelephone()
    {
        return '0' . mt_rand(1000, 9999) . '-' . mt_rand(100000, 999999);
    }

    /**
     * @return string
     */
    protected function _getCustomerDob()
    {
        $date = array(
            mt_rand(1940, date('Y') - 21),
            str_pad(mt_rand(1, 12), 2, '0', STR_PAD_LEFT),
            str_pad(mt_rand(1, 30), 2, '0', STR_PAD_LEFT),
        );
        return implode('-', $date);
    }

}
