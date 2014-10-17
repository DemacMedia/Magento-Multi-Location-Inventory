<?php
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

/**
 * Add "code" column for use when importing/exporting stock data
 */
$table = $installer->getTable('demac_multilocationinventory/location');

$installer->getConnection()
    ->addColumn($table, 'code',
        array(
            'type'    => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'  => 255,
            'unique'  => true,
            'comment' => 'Location code for import',
            'after'   => 'name'
        )
    );
$installer->getConnection()
    ->addIndex($table, 'IDX_CODE', 'code', Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE);

$installer->endSetup();