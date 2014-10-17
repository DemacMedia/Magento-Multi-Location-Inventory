[![Code Climate](https://codeclimate.com/github/DemacMedia/Magento-Multi-Location-Inventory.png)](https://codeclimate.com/github/DemacMedia/Magento-Multi-Location-Inventory)

#Multi Location Inventory v1.2.4
##Description
Allows the creation of multiple inventory locations in Magento along with assigning those inventory locations to store views.


##Use Cases
Multi Location Inventory is likely a good fit if any of the following statements accurately describe the needs of the inventory and shipping management solution.

 - ships from multiple locations, and has restrictions on which warehouses ship to which store views (e.g. regional warehouses that coincide with regional store views)
 - uses Magento to manage their inventory levels across multiple warehouses (even if they aren’t restricted in where they ship).
 - SKUs overlap between store views and represent different products. This isn't advised.

Multi Location Inventory does not support the following situations out of the box, but it is likely a good starting point if you ship from multiple locations, have no restrictions on where each warehouse ships to, and have a reason to store inventory data separately in Magento (e.g. shipping quotes depend on inventory location)


##Installation
1. Create Locations
2. Run Multi Location Inventory Indexer
3. Create inventory for products.
4. Run all indexers.


##Bugs and Limitations
- Decimal quantities don't work.
- Bundled products are not supported yet.
- Warehouses can only be linked to store views and can’t be associated in other ways (without extensive custom development).
- Locations can’t be limited or attached to particular shipping methods.
- If live shipping rates are used, shipping isn’t calculated from the warehouse location, it is calculated from the store view’s address.
- Bundled and Gift Card products aren’t supported.
- Latitude and Longitude are required but not used. Locations created via the API will have their latitude and longitude pulled from Google Maps API. Stores created in the frontend need to have their latitude and longitude manually entered. If there is no additional functionality being created relating to latitude and longitude it is an option to enter 1 in both fields.
- The inventory setting use_config_backorders is unsupported.
- The inventory setting use_config_manage_stack is unsupported.


##Troubleshooting
Verify that you aren't running any other extensions that may conflict with Multi Location Inventory.

Some common extensions that conflict include:
- OrganicInternet_SimpleConfigurableProducts
- Unirgy uRapidFlow
- Any other stock import / export utilities that weren’t specifically built for MultiLocationInventory.

Please contribute other extensions you find that are not compatible either by sending a pull request updating this README or opening a GitHub issue.


##Finding Your Way Around (Customization)
Below is a list of several major components of this extension that should help you to get started with it.

###Batch importing
Bulk uploads of inventory data can be done using the core ImportExport module. Similar to specifying data per store view using the `_store` column, you can include a `stock_location` column in your spreadsheet. The corresponding values in each row should be the code of the location you wish to import data against. You can then include multiple rows per product containing data for `qty`, `is_in_stock`, `backorders`, etc.
Download a sample CSV import [here](https://github.com/DemacMedia/Magento-Multi-Location-Inventory/blob/master/sample_import.csv).

###Inventory Reduction On Checkout
It is recommended to disable this functionality and allow integrations to push inventory updates. The easiest way to disable this functionality is to set the following config setting: Configuration > Catalog > Inventory > Stock Options > Decrease stock when order is placed

If stock updates need to happen on the Magento side the recommended approach is to update the getPriorityForOrderLocationQuoteItem method in app/code/community/Demac/MultiLocationInventory/Helper/Location.php. This method is run once per location on every quote item. Inventory is reduced from the location which returns the highest number first and works its way down until all of the inventory requested is fulfilled. If there is only one warehouse per store view this function can be left as is.


###Indexers
Indexers take data from the demac_multilocationinventory_stock table that is attached to stores and summarizes it per store view in the demac_multilocationinventory_stock_status_index table.  This allows for the data to be added to product collections with a single join.


###Mocking Stock Item Objects
Various parts of the Magento core expect stock item objects.

To work around this we create a stock item and populate it with store view specific data.

For example...
```
$stockItem = Mage::getModel(‘cataloginventory/stock_item’)
$stockItem->setIsInStock(1)
$stockItem->setBackorders(1)
$stockItem->setQty(100)
```

By taking this approach we can leave the underlying tables untouched allowing multi location inventory to easily be disabled and minimize rewrites to stockItem.

Example: app/code/community/Demac/MultiLocationInventory/Model/CatalogInventory/Stock.php


###Inventory Reduction On Checkout
The getPriorityForOrderLocationQuoteItem method in app/code/community/Demac/MultiLocationInventory/Helper/Location.php is called once per location, per product in an order (during checkout, as the inventory is being assigned).

Currently we return a random number from this method. This is suitable when there is only 1 warehouse per storeview, or when inventory isn’t being reduced at the time of checkout and is instead being handled by integrations (highly recommended).

In other cases we can take one of these approaches:
Create a scoring algorithm that looks at products individually inside of a quote as this is called.
Create a singleton with the algorithm that looks at the data and ranks it in a single call, and have it called to return the appropriate results from getPriorityForOrderLocationQuoteItem.


###Custom Iterator
We have built our own iterator - Demac_MultiLocationInventory_Model_Resource_Iterator

This implements the exact same walk functionality as the built in iterator but allows you to break the iterator loop by returning false from the callback function. This was necessary as sometimes we return more stores than necessary to avoid having to do additional lookups when removing quantity.

Example: app/code/community/Demac/MultiLocationInventory/Model/CatalogInventory/Observer.php Line 309


##Bundled Products Indexer Query [INCOMPLETE]
```
-- check if all fields are optional
CREATE temporary TABLE IF NOT EXISTS
demac_multilocationinventory_bundled_indexer_tmp AS
  (SELECT catalog_product_entity.entity_id,
          Sum(catalog_product_bundle_option.required) AS required_count
   FROM   catalog_product_entity
          JOIN catalog_product_bundle_option
            ON catalog_product_bundle_option.parent_id =
               catalog_product_entity.entity_id
   WHERE  type_id = 'bundle'
   GROUP  BY catalog_product_entity.entity_id);

-- ALL OPTIONAL: get all child products, if sum of is_in_stock > 0 then set is_in_stock = 1 and qty = some really big number
-- GLOBAL:
UPDATE demac_multilocationinventory_stock_status_index dest,
       (SELECT catalog_product_bundle_selection.parent_product_id AS product_id,
               IF(Sum(is_in_stock) > 0, 99999, 0)                 AS qty,
               IF(Sum(is_in_stock) > 0, 1, 0)                     AS is_in_stock
        FROM   catalog_product_bundle_selection
               JOIN demac_multilocationinventory_bundled_indexer_tmp
                 ON demac_multilocationinventory_bundled_indexer_tmp.entity_id =
                    catalog_product_bundle_selection.parent_product_id
               JOIN demac_multilocationinventory_stock
                 ON demac_multilocationinventory_stock.product_id =
                    catalog_product_bundle_selection.product_id
        WHERE  required_count = 0
        GROUP  BY catalog_product_bundle_selection.parent_product_id) src
SET    dest.qty = src.qty,
       dest.is_in_stock = src.is_in_stock,
       dest.backorders = 0,
       dest.manage_stock = 1
WHERE  dest.store_id = 0
       AND dest.product_id = src.product_id;

-- STORE SPECIFIC:
UPDATE demac_multilocationinventory_stock_status_index dest,
       (SELECT demac_multilocationinventory_stores.store_id
               AS
               store_id,
               catalog_product_bundle_selection.parent_product_id
               AS
               product_id,
               IF(Sum(demac_multilocationinventory_stock.is_in_stock) > 0, 99999
               , 0) AS
               qty,
               IF(Sum(demac_multilocationinventory_stock.is_in_stock) > 0, 1, 0)
               AS
               is_in_stock
        FROM   catalog_product_bundle_selection
               JOIN demac_multilocationinventory_bundled_indexer_tmp
                 ON demac_multilocationinventory_bundled_indexer_tmp.entity_id =
                    catalog_product_bundle_selection.parent_product_id
               JOIN demac_multilocationinventory_stock
                 ON demac_multilocationinventory_stock.product_id =
                    catalog_product_bundle_selection.product_id
               JOIN demac_multilocationinventory_stores
                 ON demac_multilocationinventory_stock.location_id =
                    demac_multilocationinventory_stores.location_id
        WHERE  required_count = 0
        GROUP  BY Concat(catalog_product_bundle_selection.parent_product_id, '_'
       ,
                             demac_multilocationinventory_stores.store_id)) src
SET    dest.qty = src.qty,
       dest.is_in_stock = src.is_in_stock,
       dest.backorders = 0,
       dest.manage_stock = 1
WHERE  dest.store_id = src.store_id
       AND dest.product_id = src.product_id;

-- SOME REQUIRED: Verify that each required option has at least one child item in stock (sum is_in_stock grouped by option, if all are greater than 1)
-- GLOBAL:
UPDATE demac_multilocationinventory_stock_status_index dest,
       (SELECT src.product_id
               AS
               product_id,
               IF(Concat(',', Group_concat(src.is_in_stock), ',') LIKE ',0,', 0,
               1)
               AS
               is_in_stock,
               IF(Concat(',', Group_concat(src.is_in_stock), ',') LIKE ',0,', 0,
               99999)
               AS qty
        FROM   (SELECT catalog_product_bundle_selection.option_id
                       AS
                               option_id,
                       catalog_product_bundle_selection.parent_product_id
                       AS
                               product_id,
                       IF(Sum(demac_multilocationinventory_stock.is_in_stock) >
                          0, 1, 0
                       ) AS
                               is_in_stock
                FROM   catalog_product_bundle_selection
                       JOIN demac_multilocationinventory_bundled_indexer_tmp
                         ON
demac_multilocationinventory_bundled_indexer_tmp.entity_id
=
catalog_product_bundle_selection.parent_product_id
                       JOIN catalog_product_bundle_option
                         ON catalog_product_bundle_option.option_id =
                            catalog_product_bundle_selection.option_id
                       JOIN demac_multilocationinventory_stock
                         ON demac_multilocationinventory_stock.product_id =
                            catalog_product_bundle_selection.product_id
                WHERE  required_count > 0
                       AND catalog_product_bundle_option.required = 1
                GROUP  BY Concat(
                catalog_product_bundle_selection.parent_product_id,
                          '_',
                                     catalog_product_bundle_selection.option_id)
               ) src
        GROUP  BY Concat(src.product_id)) src2
SET    dest.qty = src2.qty,
       dest.is_in_stock = src2.is_in_stock,
       dest.backorders = 0,
       dest.manage_stock = 1
WHERE  dest.store_id = 0
       AND dest.product_id = src2.product_id;

-- STORE SPECIFIC:
UPDATE demac_multilocationinventory_stock_status_index dest,
       (SELECT src.store_id,
               src.product_id,
               IF(Concat(',', Group_concat(src.is_in_stock), ',') LIKE ',0,', 0,
               1)
               AS
               is_in_stock,
               IF(Concat(',', Group_concat(src.is_in_stock), ',') LIKE ',0,', 0,
               99999)
               AS qty
        FROM   (SELECT demac_multilocationinventory_stores.store_id
                       AS
                               store_id,
                       catalog_product_bundle_selection.option_id
                       AS
                               option_id,
                       catalog_product_bundle_selection.parent_product_id
                       AS
                               product_id,
                       IF(Sum(demac_multilocationinventory_stock.is_in_stock) >
                          0, 1, 0
                       ) AS
                               is_in_stock
                FROM   catalog_product_bundle_selection
                       JOIN demac_multilocationinventory_bundled_indexer_tmp
                         ON
demac_multilocationinventory_bundled_indexer_tmp.entity_id
=
catalog_product_bundle_selection.parent_product_id
                       JOIN catalog_product_bundle_option
                         ON catalog_product_bundle_option.option_id =
                            catalog_product_bundle_selection.option_id
                       JOIN demac_multilocationinventory_stock
                         ON demac_multilocationinventory_stock.product_id =
                            catalog_product_bundle_selection.product_id
                       JOIN demac_multilocationinventory_stores
                         ON demac_multilocationinventory_stock.location_id =
                            demac_multilocationinventory_stores.location_id
                WHERE  required_count > 0
                       AND catalog_product_bundle_option.required = 1
                GROUP  BY Concat(
                catalog_product_bundle_selection.parent_product_id,
                          '_',
demac_multilocationinventory_stores.store_id, '_',
catalog_product_bundle_selection.option_id)) src
GROUP  BY Concat(src.product_id, '_', src.store_id)) src2
SET    dest.qty = src2.qty,
       dest.is_in_stock = src2.is_in_stock,
       dest.backorders = 0,
       dest.manage_stock = 1
WHERE  dest.store_id = src2.store_id
       AND dest.product_id = src2.product_id;

-- CLEANUP:
DROP TABLE IF EXISTS demac_multilocationinventory_bundled_indexer_tmp;
```
