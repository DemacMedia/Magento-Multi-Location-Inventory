<?php

/**
 * Class Demac_MultiLocationInventory_Helper_Location
 */
class Demac_MultiLocationInventory_Helper_Location extends Mage_Core_Helper_Abstract
{
    /**
     *
     *
     * @param $orderId
     * @param $quoteItemId
     *
     * @return array
     */
    public function getPrioritizedLocationsForOrderQuoteItem($orderId, $quoteItemId)
    {
        //get storeview id from order id.
        $storeId = Mage::getModel('sales/order')->load($orderId)->getStoreId();

        $locationsCollection = Mage::getModel('demac_multilocationinventory/location')
            ->getCollection()
            ->joinOnStoreView($storeId);

        $locationIds = $locationsCollection->getAllIds();

        //Init a blank array of prioritized locations for now.
        $prioritizedLocationsProcessing = array();
        //for each location available to this store view...
        foreach ($locationIds as $locationId) {
            //each priority score will have an array of locations with that score
            $priority = $this->getPriorityForOrderLocationQuoteItem($orderId, $locationId, $quoteItemId);
            if(isset($prioritizedLocationsProcessing[$priority])) {
                $prioritizedLocationsProcessing[$priority][] = $locationId;
            } else {
                $prioritizedLocationsProcessing[$priority] = array($locationId);
            }
        }

        //Sort array by keys (which holds priority)
        ksort($prioritizedLocationsProcessing);

        //reverse the order of the array so that it now goes from highest to lowest and gets rekeyed
        $prioritizedLocationsProcessing = array_reverse($prioritizedLocationsProcessing);

        $prioritizedLocations = array();

        //Get the prioritized list into a single dimensional array.
        //If multiple locations exist with the same priority they are ordered based on the order they were pulled from
        //the database in.
        foreach ($prioritizedLocationsProcessing as $prioritizedLocationsSet) {
            foreach ($prioritizedLocationsSet as $location) {
                $prioritizedLocations[] = $location;
            }
        }

        return $prioritizedLocations;
    }

    /**
     * Get a priority score based on Order ID, Location ID and Quote Item ID.
     *
     * @param int $orderId
     * @param int $locationId
     * @param int $quoteItemId
     *
     * @return int
     */
    public function getPriorityForOrderLocationQuoteItem($orderId, $locationId, $quoteItemId)
    {
        //We can look into other items in the order in the future, but for now we're just going to return a random
        //priority for testing.
        return rand(1, 100);
    }
}