<?php

/**
 * Class Demac_MultiLocationInventory_Model_Resource_Location_Collection
 */
class Demac_MultiLocationInventory_Model_Resource_Location_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init collection
     */
    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/location');
        $this->_map['fields']['id']                  = 'main_table.id';
        $this->_map['fields']['location_id']         = 'demac_multilocationinventory_stores.location_id';
    }

    /**
     * Perform operations after collection load
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location_Collection
     *
     */
    protected function _afterLoad()
    {
        $items      = $this->getColumnValues('id');
        $connection = $this->getConnection();
        if(count($items)) {
            $select = $connection->select()
                ->from(array('demac_multilocationinventory_stores' => $this->getTable('demac_multilocationinventory/stores')))
                ->where('demac_multilocationinventory_stores.location_id IN (?)', $items);

            if($result = $connection->fetchPairs($select)) {
                foreach ($this as $item) {
                    $stores = $this->lookupStoreIds($item->getId());
                    $item->setData('store_id', $stores);
                }
            }
        }
        return parent::_afterLoad();
    }

    /**
     * Look up store ids that a location shares its inventory with.
     *
     * @param $locationId
     *
     * @return array
     */
    public function lookupStoreIds($locationId)
    {
        $connection = $this->getConnection();
        $select     = $connection->select()
            ->from($this->getTable('demac_multilocationinventory/stores'), 'store_id')
            ->where('location_id = ?', (int) $locationId);

        return $connection->fetchCol($select);
    }

    /**
     * Join store relation table if there is store filter
     *
     * @return NULL
     */
    protected function _renderFiltersBefore()
    {
        if($this->getFilter('store_id')) {
            $this->getSelect()->join(
                array('demac_multilocationinventory_stores' => $this->getTable('demac_multilocationinventory/stores')),
                'main_table.id = demac_multilocationinventory_stores.location_id',
                array()
            )->group('main_table.id');

            /*
             * Allow analytic functions usage because of one field grouping
             */
            $this->_useAnalyticFunction = true;
        }

        return parent::_renderFiltersBefore();
    }

    /**
     * Add filter by store
     *
     * @param int|Mage_Core_Model_Store $store
     * @param bool                      $withAdmin
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location_Collection
     *
     */
    public function addStoreFilter($store, $withAdmin = true)
    {
        if(!$this->getFlag('store_filter_added')) {
            if($store instanceof Mage_Core_Model_Store) {
                $store = array($store->getId());
            }
            if(!is_array($store)) {
                $store = array($store);
            }
            if($withAdmin) {
                $store[] = Mage_Core_Model_App::ADMIN_STORE_ID;
            }
            $this->addFilter('store_id', array('in' => $store), 'public');
        }

        return $this;
    }

    /**
     * Join stock data to a location collection based on product id and store view id.
     *
     * @param      $productId
     * @param bool $storeViewId
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location_Collection
     */
    public function joinStockDataOnProductAndStoreView($productId = false, $storeViewId = false)
    {
        $this
            ->getSelect()
            ->join(
                array(
                    'stock' => Mage::getSingleton('core/resource')->getTableName('demac_multilocationinventory/stock')
                ),
                'main_table.id = stock.location_id',
                array(
                    'stock.qty',
                    'stock.min_qty',
                    'stock.backorders',
                    'stock.is_in_stock',
                    'stock.manage_stock'
                )
            );

        $this
            ->addFieldToFilter('main_table.status', 1);


        if($productId) {
            $this
                ->addFieldToFilter(
                    'stock.product_id',
                    array(
                        array('eq' => $productId),
                        array('null' => true)
                    )
                );
        }

        $this
            ->getSelect()
            ->join(
                array(
                    'stores' => Mage::getSingleton('core/resource')->getTableName('demac_multilocationinventory/stores')
                ),
                'main_table.id = stores.location_id',
                array()
            );

        if($storeViewId) {

            $this->addFieldToFilter('stores.store_id', $storeViewId);
        }

        $this->getSelect()->group('main_table.id');


        return $this;
    }


    /**
     * Join location collection based on store view id .
     *
     * @param bool $storeViewId
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location_Collection
     */
    public function joinOnStoreView($storeViewId = false)
    {
        $this
            ->getSelect()
            ->join(
                array(
                    'stores' => Mage::getSingleton('core/resource')->getTableName('demac_multilocationinventory/stores')
                ),
                'main_table.id = stores.location_id',
                array()
            );

        if($storeViewId) {

            $this->addFieldToFilter('stores.store_id', $storeViewId);
        }

        $this->getSelect()->group('main_table.id');


        return $this;
    }

    /**
     * @return Varien_Data_Collection_Db
     */
    public function reset()
    {
        return $this->_reset();
    }
}
