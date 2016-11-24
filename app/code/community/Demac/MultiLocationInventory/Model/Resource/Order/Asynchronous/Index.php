<?php

/**
 * Class Demac_MultiLocationInventory_Model_Resource_Order_Asynchronous_Index
 */
class Demac_MultiLocationInventory_Model_Resource_Order_Asynchronous_Index extends Mage_Core_Model_Resource_Db_Abstract
{

    /**
     * Init Resource
     */
    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/order_asynchronous_index', 'id');
    }
}