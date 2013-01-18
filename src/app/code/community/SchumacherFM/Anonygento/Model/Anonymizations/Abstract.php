<?php
/**
 * @category    SchumacherFM_Anonygento
 * @package     Model
 * @author      Cyrill at Schumacher dot fm
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @bugs        https://github.com/SchumacherFM/Anonygento/issues
 */
abstract class SchumacherFM_Anonygento_Model_Anonymizations_Abstract extends Varien_Object
{
    /**
     * sql column name
     */
    const COLUMN_ANONYMIZED = 'anonymized';

    /**
     * @var Zend_ProgressBar
     */
    protected $_progressBar = null;

    /**
     * @var array
     */
    protected $_instances = array();

    protected $_options = array();

    /**
     * loads among other things the xml config: options
     * <global><anonygento><anonymizations><[element]><options>
     */
    protected function _construct()
    {
        parent::_construct();
        /** getConfigName() is initialized in SchumacherFM_Anonygento_Model_Console_Console::getModel */
        $this->_options = Mage::helper('schumacherfm_anonygento')->getAnonymizationsConfig($this->getConfigName())->options->asArray();
    }

    /**
     * to configure an option please use the xml config:
     * <global><anonygento><anonymizations><[element]><options>
     *
     * @param string $value
     * @param string $type
     *
     * @return mixed
     */
    protected function _getOption($value, $type = 'bool')
    {
        switch ($type) {
            case 'bool':
                return (isset($this->_options[$value]) && (int)$this->_options[$value] === 1);
            case 'int':
                return isset($this->_options[$value]) ? (int)$this->_options[$value] : NULL;
            case 'str':
                return isset($this->_options[$value]) ? (string)$this->_options[$value] : NULL;
            default:
                return FALSE;
        }
    }

    /**
     * @return SchumacherFM_Anonygento_Model_Random_Customer
     */
    protected function _getRandomCustomer()
    {
        return Mage::getSingleton('schumacherfm_anonygento/random_customer');
    }

    /**
     * @param string $type
     *
     * @return SchumacherFM_Anonygento_Model_Random_Mappings
     */
    protected function _getMappings($type)
    {
        // do not run as getSingleton
        $mapping = Mage::getModel('schumacherfm_anonygento/random_mappings');
        /* @var $mapping SchumacherFM_Anonygento_Model_Random_Mappings */
        $mapped = $mapping->getMapping($type);

        $mapped->{'set' . self::COLUMN_ANONYMIZED}(self::COLUMN_ANONYMIZED);

        return $mapped;
    }

    /**
     * runs the anonymization process
     *
     * @param Varien_Data_Collection_Db $collection
     * @param string                    $anonymizationMethod
     */
    public function run($collection, $anonymizationMethod)
    {
        $i = 0;
        foreach ($collection as $model) {
            $this->$anonymizationMethod($model);
            $this->getProgressBar()->update($i);
            $i++;
        }
        $collection = null;
        $this->getProgressBar()->finish();

    }

    /**
     * @param Zend_ProgressBar $bar
     */
    public function setProgressBar(Zend_ProgressBar $bar)
    {
        $this->_progressBar = $bar;
    }

    /**
     * @return null|Zend_ProgressBar
     */
    public function getProgressBar()
    {
        return $this->_progressBar;
    }

    /**
     * copies the data from obj to another using a mapping array
     *
     * @param object                  $fromObject
     * @param object                  $toObject
     * @param Varien_Object           $mappings
     *
     * @return bool
     */
    protected function _copyObjectData($fromObject, $toObject, Varien_Object $mappings)
    {
        // @todo refactor $mappings, just use a string instead of passing an object
//        if(!is_string($mappings)){
//            throw new Exception('$mappings must be a string!');
//        }

        $fill = $mappings->getFill();
        $mappings->unsFill();
        $mappings->unsSystem();
        $mapped = $mappings->getData();

        if (count($mapped) === 0) {
            return FALSE;
        }

        $fromObject->{'set' . self::COLUMN_ANONYMIZED}(1);
        $getDataFromObject = $fromObject->getData();

        foreach ($mapped as $key => $newKey) {

            // throw an error if there is no key in fromObject
            if (!array_key_exists($key, $getDataFromObject)) { // oh php ... why are the args switched?

                Zend_Debug::dump($fromObject->getData());
                echo PHP_EOL;
                Zend_Debug::dump($toObject->getData());

                $msg = 'Check your config.xml!' . PHP_EOL . $key . ' not Found in fromObj: ' . get_class($fromObject) . ' copied toObj: ' .
                    get_class($toObject) . PHP_EOL;

                $e = new Exception($msg);
                Mage::logException($e);
                throw $e;
            }

            $data = $fromObject->getData($key);
            if ($data !== null) {
                $toObject->setData($newKey, $data);
            }
        }

        if ($fill && is_array($fill)) {
            $fillModel = Mage::getSingleton('schumacherfm_anonygento/random_fill');
            $fillModel->setToObj($toObject);
            $fillModel->setMappings($mappings);
            $fillModel->fill();
        }

        Mage::dispatchEvent('anonygento_anonymizations_copy_after', array(
            'to_object' => $toObject,
        ));

    }

    /**
     * merge an additional object into the toObject
     *
     * @param Varien_Object $fromObject
     * @param Varien_Object $toObject
     * @param string        $mappings
     *
     * @return bool
     * @throws Exception
     */
    protected function _mergeMissingAttributes(Varien_Object $fromObject, Varien_Object $toObject, $mappings)
    {
        if (!is_string($mappings)) {
            throw new Exception('$mappings must be a string!');
        }
        $mappings = $this->_getMappings($mappings);
        $mappings->unsFill();
        $mappings->unsSystem();
        $mapped = $mappings->getData();

        if (count($mapped) === 0) {
            return FALSE;
        }
        foreach ($mapped as $key => $value) {
            $data = $fromObject->getData($key);
            if (!$toObject->hasData($key)) {
                $toObject->setData($key, $data);
            }
        }
        return TRUE;
    }

    /**
     * @param object  $collection
     * @param integer $isAnonymized
     */
    protected function _collectionAddStaticAnonymized($collection, $isAnonymized = 0)
    {
        $isAnonymized = (int)$isAnonymized;

        if ($collection instanceof Mage_Eav_Model_Entity_Collection_Abstract) {
            $collection->addStaticField(self::COLUMN_ANONYMIZED);
            $collection->addAttributeToFilter(self::COLUMN_ANONYMIZED, $isAnonymized);
        } else {
            $collection->addFieldToSelect(self::COLUMN_ANONYMIZED);
            $select = $collection->getSelect();
            $select->where(self::COLUMN_ANONYMIZED . '=' . $isAnonymized);
        }

    }

    /**
     * @param object                                               $collection
     * @param array|SchumacherFM_Anonygento_Model_Random_Mappings  $fields from the mapping table the values
     */
    protected function _collectionAddAttributeToSelect($collection, $fields)
    {
        if ($fields instanceof SchumacherFM_Anonygento_Model_Random_Mappings) {
            $fields = $fields->getData();
        }

        foreach ($fields as $key => $field) {

            if ($key === 'fill' || is_array($field)) {
                continue;
            }

            $attributeOrField = ($collection instanceof Mage_Eav_Model_Entity_Collection_Abstract)
                ? 'addAttributeToSelect'
                : 'addFieldToSelect';
            $collection->$attributeOrField($field);
        }

    }

    /**
     * @param string $modelName
     * @param string $mappingName
     *
     * @return Varien_Data_Collection_Db
     */
    protected function _getCollection($modelName, $mappingName = NULL)
    {

        $collection = stristr($modelName, '_collection') !== FALSE
            ? Mage::getResourceModel($modelName)
            : Mage::getModel($modelName)->getCollection();

        if ($mappingName !== NULL) {
            $this->_collectionAddAttributeToSelect($collection,
                $this->_getMappings($mappingName)->getEntityAttributes()
            );
        }

        /* getOptions() please see shell class */
        if ($this->getOptions() && $this->getOptions()->getCollectionLimit()) {
            $offset = $this->getOptions()->getCollectionLimit() * $this->getOptions()->getCurrentRun();
            $collection->getSelect()->limit($this->getOptions()->getCollectionLimit(), $offset);
        }

        $this->_collectionAddStaticAnonymized($collection, 0);

        return $collection;
    }

}