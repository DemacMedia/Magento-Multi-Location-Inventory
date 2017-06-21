<?php

/**
 * Class Demac_MultiLocationInventory_Model_Admin_Observer
 */
class Demac_MultiLocationInventory_Model_Admin_Observer
{

    /**
     * Used to store input data from the 'multilocationinventory' field so that we can manipulate it without
     * manipulating $_POST.
     * @var array
     */
    private $inputData = array();

    /**
     * Fires when a product is saved, used to the multi location inventory tab data.
     */
    public function multiLocationInventoryProductSave($observer)
    {
        $productId       = $observer->getEvent()->getProduct()->getId();
        $this->inputData = Mage::app()->getRequest()->getPost('multilocationinventory');
        if($productId) {
            if($this->inputData) {
                $input_multiLocationInventoryDataIds = array_keys($this->inputData);

                //Get select collection to find all existing inventory data to update...
                $multilocationinventoryCollection = Mage::getModel('demac_multilocationinventory/stock')
                    ->getCollection()
                    ->addFieldToSelect(array('stock_id', 'location_id'))
                    ->addFieldToFilter(
                        'location_id',
                        array(
                            'in' => $input_multiLocationInventoryDataIds
                        )
                    )
                    ->addFieldToFilter(
                        'product_id',
                        array(
                            'eq' => $productId
                        )
                    );

                //Iterate through the collection of inventory data to update...
                if($multilocationinventoryCollection->getSize() > 0) {
                    Mage::getSingleton('core/resource_iterator')->walk(
                        $multilocationinventoryCollection->getSelect(),
                        array(
                            array($this, '_updateInventoryDataIterate')
                        ),
                        array(
                            'invoker' => $this
                        )
                    );
                }

                //Create remaining stock data
                foreach ($this->inputData as $locationId => $locationData) {
                    $_stock = array(
                        'location_id' => $locationId,
                        'product_id'  => $productId,
                    );
                    $this->_updateInventoryData($_stock);
                }
            }
            Mage::getModel('demac_multilocationinventory/indexer')->reindex($productId);
        }
    }

    /**
     * @param $observer
     * @return $this
     */
    public function persistMLIIndexProductSaveOnImport($observer)
    {
        /** @var string $_node */
        $_node = 'global/events/'.$observer->getEvent()->getName().'/observers/multi_location_inventory_product_save_on_import/import';

        /** @var Mage_Core_Model_Config_Element $_import_argument */
        $_import_argument = Mage::getConfig()->getNode($_node);

        // If calling from Admin interface then walk away
        if (!$_import_argument) {
            return $this;
        }
        /** @var Mage_Core_Controller_Request_Http $_request */
        $_request = Mage::app()->getRequest();

        /** @var int $_product_id */
        $_product_id = $observer->getEvent()->getProduct()->getId();
        /** @var array $_stock */
        $_stock = $_request->getPost("stock");
        if ($_product_id && $_stock) {
            /** @var int $_location_id */
            $_location_id = $_stock["location_id"];
            // Hydrate input-data private variable
            $this->inputData[$_location_id] = $_stock;
            //$input_multiLocationInventoryDataIds = [$_location_id];

            //Get select collection to find all existing inventory data to update...
            /** @var Demac_MultiLocationInventory_Model_Resource_Stock_Collection $_collection */
            $_collection = Mage::getModel('demac_multilocationinventory/stock')
                ->getCollection()
                ->addFieldToSelect(array('stock_id', 'location_id'))
                ->addFieldToFilter('location_id', $_location_id)
                ->addFieldToFilter('product_id', array('eq' => $_product_id)
                );

            //Iterate through the collection of inventory data to update...
            if ($_collection->getSize() > 0) {
                Mage::getSingleton('core/resource_iterator')->walk(
                    $_collection->getSelect(),
                    array(
                        array($this, '_updateInventoryDataIterate')
                    ),
                    array(
                        'invoker' => $this
                    )
                );
            }

            //Create remaining stock data
            /** @var array $_stock */
            $_stock = array(
                'location_id' => $_location_id,
                'product_id' => $_product_id,
            );
            $this->_updateInventoryData($_stock);
        }
        Mage::getModel('demac_multilocationinventory/indexer')->reindex($_product_id);
    }

    /**
     * Wrapper for the update stock iterator to push data into other functions in a generic format.
     *
     * @param $args
     */
    public function _updateInventoryDataIterate($args)
    {
        $this->_updateInventoryData($args['row']);
    }

    /**
     * Load a stock object based on the passed in data, update it based on input data then save.
     *
     * @param $_stockData
     */
    public function _updateInventoryData($_stockData)
    {
        $_stock     = Mage::getModel('demac_multilocationinventory/stock')->setData($_stockData);
        $locationId = $_stock->getLocationId();
        if(isset($this->inputData[$locationId])) {
            $inputStock = $this->inputData[$locationId];
            $_stock->setQty($inputStock['quantity']);
            $_stock->setMinQty($inputStock['min_qty']);
            $_stock->setBackorders($inputStock['backorders']);
            $_stock->setIsInStock($inputStock['is_in_stock']);
            $_stock->setManageStock($inputStock['manage_stock']);
            $_stock->save();
            unset($this->inputData[$locationId]);
        }
    }
}
