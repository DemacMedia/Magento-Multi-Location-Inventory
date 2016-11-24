<?php
/**
 * Class Demac_MultiLocationInventory_Model_Resource_Order_Stock_Source_Collection
 */
class Demac_MultiLocationInventory_Model_Resource_Order_Asynchronous_Index_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init collection
     */
    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/order_asynchronous_index');
    }
}