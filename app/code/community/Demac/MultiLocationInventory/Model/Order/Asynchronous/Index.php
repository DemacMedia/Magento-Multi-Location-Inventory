<?php
/**
 * Class Demac_MultiLocationInventory_Model_Order_Asynchronous_Index
 */
class Demac_MultiLocationInventory_Model_Order_Asynchronous_Index extends Mage_Core_Model_Abstract
{
    /**
     * Init Model
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('demac_multilocationinventory/order_asynchronous_index');
    }
}