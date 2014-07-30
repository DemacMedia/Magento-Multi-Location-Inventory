<?php

/**
 * Class Demac_MultiLocationInventory_Model_Resource_Location
 */
class Demac_MultiLocationInventory_Model_Resource_Location extends Mage_Core_Model_Resource_Db_Abstract
{
    protected $_location = NULL;

    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/location', 'id');
    }

    /**
     * Assign page to store views
     *
     * @param Mage_Core_Model_Abstract $object
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location
     */
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $this->saveStores($object);
        $this->saveLocatorStores($object);

        return parent::_afterSave($object);
    }

    /**
     * Save stores
     *
     * @param $object
     */
    protected function saveStores($object)
    {
        $oldStores = $this->lookupStoreIds($object->getId());
        $newStores = (array)$object->getStores();
        if (empty($newStores)) {
            $newStores = (array)$object->getStoreId();
        }
        $table  = $this->getTable('demac_multilocationinventory/stores');
        $insert = array_diff($newStores, $oldStores);
        $delete = array_diff($oldStores, $newStores);

        if ($delete) {
            $where = array(
                'location_id = ?' => (int)$object->getId(),
                'store_id IN (?)' => $delete
            );

            $this->_getWriteAdapter()->delete($table, $where);
        }

        if ($insert) {
            $data = array();

            foreach ($insert as $storeId) {
                $data[] = array(
                    'location_id' => (int)$object->getId(),
                    'store_id'    => (int)$storeId
                );
            }

            $this->_getWriteAdapter()->insertMultiple($table, $data);
        }
    }

    /**
     * Save locator stores
     *
     * @param $object
     */
    protected function saveLocatorStores($object)
    {
        $oldStores = $this->lookupLocatorStoreIds($object->getId());
        $newStores = (array)$object->getLocatorStores();
        if (empty($newStores)) {
            $newStores = (array)$object->getStoreId();
        }
        $table  = $this->getTable('demac_multilocationinventory/locator_stores');
        $insert = array_diff($newStores, $oldStores);
        $delete = array_diff($oldStores, $newStores);

        if ($delete) {
            $where = array(
                'location_id = ?' => (int)$object->getId(),
                'store_id IN (?)' => $delete
            );

            $this->_getWriteAdapter()->delete($table, $where);
        }

        if ($insert) {
            $data = array();

            foreach ($insert as $storeId) {
                $data[] = array(
                    'location_id' => (int)$object->getId(),
                    'store_id'    => (int)$storeId
                );
            }

            $this->_getWriteAdapter()->insertMultiple($table, $data);
        }
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string                   $field
     * @param mixed                    $value
     * @param Mage_Core_Model_Abstract $object
     *
     * @return Zend_Db_Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if ($object->getStoreId()) {
            $storeIds = array(Mage_Core_Model_App::ADMIN_STORE_ID, (int)$object->getStoreId());
            $select->join(
                array('demac_multilocationinventory_stores' => $this->getTable('demac_multilocationinventory/stores')),
                $this->getMainTable() . '.id = demac_multilocationinventory_stores.location_id',
                array())
                ->where('demac_multilocationinventory_stores.store_id IN (?)', $storeIds)
                ->order('demac_multilocationinventory_stores.store_id DESC')
                ->limit(1);
        }

        if ($object->getLocatorStoreId()) {
            $storeIds = array(Mage_Core_Model_App::ADMIN_STORE_ID, (int)$object->getLocatorStoreId());
            $select->join(
                array('demac_multilocationinventory_locator_stores' => $this->getTable('demac_multilocationinventory/locator_stores')),
                $this->getMainTable() . '.id = demac_multilocationinventory_locator_stores.location_id',
                array())
                ->where('demac_multilocationinventory_locator_stores.store_id IN (?)', $storeIds)
                ->order('demac_multilocationinventory_locator_stores.store_id DESC')
                ->limit(1);
        }

        return $select;
    }

    /**
     * Get store ids to which specified item is assigned
     *
     * @param int $locationId
     *
     * @return array
     */
    public function lookupStoreIds($locationId)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select()
            ->from($this->getTable('demac_multilocationinventory/stores'), 'store_id')
            ->where('location_id = ?', (int)$locationId);

        return $adapter->fetchCol($select);
    }

    /**
     * Get locator store ids to which specified item is assigned
     *
     * @param int $locationId
     *
     * @return array
     */
    public function lookupLocatorStoreIds($locationId)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select()
            ->from($this->getTable('demac_multilocationinventory/locator_stores'), 'store_id')
            ->where('location_id = ?', (int)$locationId);

        return $adapter->fetchCol($select);
    }

    /**
     * After load init
     *
     * @param Mage_Core_Model_Abstract $object
     *
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());

            $object->setData('store_id', $stores);
            $stores = $this->lookupLocatorStoreIds($object->getId());

            $object->setData('locator_store_id', $stores);

        }

        return parent::_afterLoad($object);
    }

    /**
     * Add filter by store
     *
     * @param int|Mage_Core_Model_Store $store
     * @param bool                      $withAdmin
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location
     */
    public function addStoreFilter($store, $withAdmin = TRUE)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $store = array($store->getId());
        }

        if (!is_array($store)) {
            $store = array($store);
        }

        if ($withAdmin) {
            $store[] = Mage_Core_Model_App::ADMIN_STORE_ID;
        }
        $this->addFilter('store_id', array('in' => $store), 'public');

        return $this;
    }

    /**
     * Add filter by locator store
     *
     * @param int|Mage_Core_Model_Store $store
     * @param bool                      $withAdmin
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location
     */
    public function addLocatorStoreFilter($store, $withAdmin = TRUE)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $store = array($store->getId());
        }

        if (!is_array($store)) {
            $store = array($store);
        }

        if ($withAdmin) {
            $store[] = Mage_Core_Model_App::ADMIN_STORE_ID;
        }
        $this->addFilter('locator_store_id', array('in' => $store), 'public');

        return $this;
    }

    /**
     * Set store
     *
     * @param $store
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location
     */
    public function setStore($store)
    {
        $this->_store = $store;

        return $this;
    }

    /**
     * Retrieve store model
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        return Mage::app()->getStore($this->_store);
    }
}