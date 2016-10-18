<?php

/**
 * Class Demac_MultiLocationInventory_Model_Observer
 */
class Demac_MultiLocationInventory_Model_Observer
{

    /**
     * @var array of arrays with Quote Stock Id => Quantity Ordered
     */
    private $checkoutProducts = array();

    /**
     * @param Varien_Event_Observer $observer
     */
    public function catalogProductCollectionApplyLimitationsBefore(Varien_Event_Observer $observer)
    {
        if ($collection = $observer->getCollection()) {
            $filters = $collection->getLimitationFilters();
            if(isset($filters['visibility']) && !Mage::getStoreConfig('cataloginventory/options/show_out_of_stock')) {
                $storeId = Mage::app()->getStore()->getId();
                $selectFrom = $collection->getSelect()->getPart(Zend_Db_Select::FROM);
                if(!isset($selectFrom['stock_status_index'])) {
                    $collection
                        ->getSelect()
                        ->join(
                            array(
                                'stock_status_index' => Mage::getSingleton('core/resource')->getTableName('demac_multilocationinventory/stock_status_index')
                            ),
                            'e.entity_id = stock_status_index.product_id' .
                            ' AND stock_status_index.qty > 0' .
                            ' AND stock_status_index.is_in_stock = 1' .
                            ' AND stock_status_index.store_id = ' . $storeId,
                            array()
                        );
                }
            }
        }
    }

    /**
     * Deduct ordered products from inventory if the appropriate config setting is enabled.
     * Triggers after checkout submit.
     *
     * @param $observer
     *
     * @TODO pass off removeStockFromLocations and backorderRemainingStock to some sort of background worker.
     */
    public function checkoutAllSubmitAfter($observer)
    {
        if(Mage::getStoreConfig('cataloginventory/options/can_subtract')) {

            $order   = $observer->getEvent()->getOrder();
            $quote   = $observer->getEvent()->getQuote();
            $orderId = $order->getId();
            $storeId = $order->getStoreId();

            $this->checkoutProducts = array();
            $updatedProducts        = array();
            $quoteItems             = $observer->getEvent()->getQuote()->getAllItems();

            foreach ($quoteItems as $quoteItem) {
                $updatedProducts[] = $quoteItem->getProductId();

                $children = $quoteItem->getChildrenItems();
                if ($children) {
                    foreach ($children as $childItem) {
                        $this->checkoutProducts[$childItem->getId()] = $childItem->getTotalQty();
                    }
                } else {
                    $this->checkoutProducts[$quoteItem->getId()] = $quoteItem->getTotalQty();
                }
            }

            $this->removeStockFromLocations($order, $quote);

            Mage::getModel('demac_multilocationinventory/indexer')->reindex($updatedProducts);
        }
    }

    /**
     * Remove stock from locations.
     *
     * @param $order
     * @param $quote
     */
    protected function removeStockFromLocations($order, $quote)
    {
        $orderId = $order->getId();

        foreach ($this->checkoutProducts as $checkoutProductQuoteItemId => $checkoutProductQuantity) {
            $checkoutProductItem = $quote->getItemById($checkoutProductQuoteItemId);
            if($checkoutProductItem->getProduct()->getTypeId() == 'simple' || $checkoutProductItem->getProduct()->getTypeId() == 'giftcard') {
                $checkoutProductId = $checkoutProductItem->getProductId();
                $locationIds       = Mage::helper('demac_multilocationinventory/location')->getPrioritizedLocationsForOrderQuoteItem($orderId, $checkoutProductQuoteItemId);
                //loop through each location and distribute the inventory
                $stockCollection = Mage::getModel('demac_multilocationinventory/stock')
                    ->getCollection()
                    ->addFieldToSelect(array('location_id', 'qty', 'min_qty'))
                    ->addFieldToFilter(
                        'location_id',
                        array(
                            'in' => $locationIds
                        )
                    )
                    ->addFieldToFilter(
                        'qty',
                        array(
                            'gt' => '0'
                        )
                    )
                    ->addFieldToFilter('product_id', $checkoutProductId);
                $stockCollection
                    ->getSelect()
                    ->order('FIELD(location_id,' . implode(',', $locationIds) . ')');

                Mage::getModel('demac_multilocationinventory/resource_iterator')->walk(
                    $stockCollection->getSelect(),
                    array(
                        array($this, '_locationStockIterate')
                    ),
                    array(
                        'invoker'       => $this,
                        'quote_item_id' => $checkoutProductQuoteItemId,
                        'product_id'    => $checkoutProductId
                    )
                );

                //Get Backorder Location
                //Reduce backorder inventory if possible...
                foreach ($this->checkoutProducts as $checkoutProductQuoteItemId => $checkoutProductQuantity) {
                    if($checkoutProductQuantity > 0) {
                        $backorderLocationCollection = Mage::getModel('demac_multilocationinventory/stock')
                            ->getCollection()
                            ->addFieldToSelect(array('location_id', 'qty'))
                            ->addFieldToFilter(
                                'location_id',
                                array(
                                    'in' => $locationIds
                                )
                            )
                            ->addFieldToFilter(
                                'backorders',
                                array(
                                    'eq' => '1'
                                )
                            )
                            ->addFieldToFilter('product_id', $checkoutProductId);
                        $backorderLocationCollection
                            ->getSelect()
                            ->order('FIELD(location_id,' . implode(',', $locationIds) . ')')
                            ->limit(1);

                        if($backorderLocationCollection->getSize()) {
                            $firstBackorderLocation = $backorderLocationCollection->getFirstItem();
                            $stockId                = $firstBackorderLocation['stock_id'];
                            $locationId             = $firstBackorderLocation['location_id'];
                            $availableQty           = $firstBackorderLocation['qty'];
                            $orderStockSource       = Mage::getModel('demac_multilocationinventory/order_stock_source');
                            $orderStockSource->setSalesQuoteItemId($checkoutProductQuoteItemId);
                            $orderStockSource->setLocationId($locationId);
                            $remainingQty = $orderStockSource->getQty() - $checkoutProductQuantity;
                            $orderStockSource->setQty($remainingQty);
                            $orderStockSource->save();
                            $this->checkoutProducts[$checkoutProductQuoteItemId] = 0;
                            $stock                                               = Mage::getModel('demac_multilocationinventory/stock')->load($stockId);
                            $stock->setQty($remainingQty);
                            $stock->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * Iterate through locations / stock deducting until the order is processed..
     *
     * @param $args
     *
     * @return bool
     */
    public function _locationStockIterate($args)
    {
        $quoteItemId  = $args['quote_item_id'];
        $productId    = $args['product_id'];
        $requestedQty = $this->checkoutProducts[$quoteItemId];

        $row          = $args['row'];
        $stockId      = $row['stock_id'];
        $locationId   = $row['location_id'];
        $availableQty = $row['qty'];
        $minQty       = $row['min_qty'];
        $requestedQty = $this->checkoutProducts[$quoteItemId];

        $orderStockSource = Mage::getModel('demac_multilocationinventory/order_stock_source');
        $orderStockSource->setSalesQuoteItemId($quoteItemId);
        $orderStockSource->setLocationId($locationId);

        // If we have a min_qty set then this should be used as the available qty value
        if ($minQty > 0) {
            $availableQty = $availableQty - $minQty;
        }

        if($requestedQty > 0) {
            if($requestedQty >= $availableQty) {
                //deduct available qty
                $orderStockSource->setQty($availableQty);
                $orderStockSource->save();
                $this->checkoutProducts[$quoteItemId] -= $availableQty;
                $stock = Mage::getModel('demac_multilocationinventory/stock')->load($stockId);
                $stock->setQty($stock->getQty() - $availableQty);
                if(!$stock->getBackorders()) {
                    $stock->setIsInStock(0);
                }
                $stock->save();
            } else {
                //deduct full requested amount
                $orderStockSource->setQty($requestedQty);
                $orderStockSource->save();
                $this->checkoutProducts[$quoteItemId] = 0;
                $stock                                = Mage::getModel('demac_multilocationinventory/stock')->load($stockId);
                $stock->setQty($stock->getQty() - $requestedQty);
                $stock->save();

                //returning false causes our iterator to stop.
                return false;
            }
        }
    }

}
