<?php

/**
 * Class Demac_MultiLocationInventory_Model_Importexport_Observer
 */
class Demac_MultiLocationInventory_Model_Importexport_Observer
{

    const COL_SKU               = 'sku';
    const STOCK_SCOPE_NULL      = -1;
    const STOCK_SCOPE_LOCATION  = 1;

    const COL_STOCK_LOCATION = 'stock_location';

    protected $_locationIds = array();


    /**
     * Load all location IDs
     *
     * @return $this
     */
    protected function _initLocations() {

        /** @var Demac_MultiLocationInventory_Model_Resource_Location_Collection $collection */
        $collection = Mage::getModel('demac_multilocationinventory/location')->getCollection();
        $collection
            ->addFieldToSelect('id')
            ->addFieldToSelect('code');

        /** @var Demac_MultiLocationInventory_Model_Location $location */
        foreach($collection as $location) {
            $this->_locationIds[$location->getCode()] = $location->getId();
        }

        return $this;
    }

    /**
     * catalog_product_import_finish_before
     *
     * @param $observer
     * @return $this
     */
    public function catalogProductImportFinishBefore($observer) {
        /** @var Mage_ImportExport_Model_Import_Entity_Product $adapter */
        $adapter = $observer->getData('adapter');
        if(!$adapter) return;

        // Only process replace or append imports
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $adapter->getBehavior()) return;

        $defaultStockData = array(
            'manage_stock'                  => 1,
            'use_config_manage_stock'       => 1,
            'qty'                           => 0,
            'backorders'                    => 0,
            'use_config_backorders'         => 1,
            'is_in_stock'                   => 0
        );

        // Pre-load all the valid location codes
        $this->_initLocations();

        $stockTable = Mage::getResourceModel('demac_multilocationinventory/stock')->getMainTable();

        $newSku = $adapter->getNewSku();
        $sku = false;

        while ($bunch = $adapter->getNextBunch()) {
            $stockData = array();

            // Format bunch to stock data rows
            foreach ($bunch as $rowNum => $rowData) {

                $this->_filterRowData($rowData);
                if (!$adapter->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }

                if (Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT == $adapter->getRowScope($rowData)) {
                    $sku = $rowData[self::COL_SKU];
                }

                if(!$sku) continue;

                // only process items with a stock location scope specified
                if (self::STOCK_SCOPE_LOCATION != $this->getRowStockScope($rowData)) {
                    continue;
                }

                $locationCode = $rowData[self::COL_STOCK_LOCATION];
                if(!array_key_exists($locationCode, $this->_locationIds)) continue;

                $locationId = $this->_locationIds[$locationCode];

                $row = array();
                $row['location_id'] = $locationId;
                $row['product_id']  = $newSku[$sku]['entity_id'];
                $row['qty']         = $rowData['qty'];
                $row['backorders']  = $rowData['backorders'];

                /** @var $stockItem Demac_MultiLocationInventory_Model_Stock */
                $stockItem = Mage::getModel('demac_multilocationinventory/stock');
                $stockItem->loadByProduct($locationId, $row['product_id']);
                $existStockData = $stockItem->getData();

                $row = array_merge(
                    $defaultStockData,
                    array_intersect_key($existStockData, $defaultStockData),
                    array_intersect_key($rowData, $defaultStockData),
                    $row
                );

                $stockItem->setData($row);
                unset($row);

                $stockData[] = $stockItem->unsetOldData()->getData();
            }

            // Insert rows
            if ($stockData) {
                $adapter->getConnection()->insertOnDuplicate($stockTable, $stockData);
            }
        }

    }

    /**
     * @param array $rowData
     * @return mixed
     */
    public function getRowStockScope($rowData) {

        if(isset($rowData[self::COL_STOCK_LOCATION]) && strlen(trim($rowData[self::COL_STOCK_LOCATION]))) {
            return self::STOCK_SCOPE_LOCATION;
        } else {
            return self::STOCK_SCOPE_NULL;
        }
    }


    /**
     * Removes empty keys in case value is null or empty string
     *
     * @param array $rowData
     */
    protected function _filterRowData(&$rowData)
    {
        $rowData = array_filter($rowData, 'strlen');
        // Exceptions - for sku - put them back in
        if (!isset($rowData[self::COL_SKU])) {
            $rowData[self::COL_SKU] = null;
        }
    }

}