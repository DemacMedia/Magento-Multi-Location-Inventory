<?php
/**
 * Created by PhpStorm.
 * User: MichaelK
 * Date: 4/9/14
 * Time: 7:05 AM
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
/**
 * Create table 'demac_multilocationinventory/order_asynchronous_index'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('demac_multilocationinventory/order_asynchronous_index'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ), 'Index Id')
    ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        'unique'  => true,
    ), 'Product ID')
    ->setComment('Asynchronous indexation after order');

$installer->getConnection()->createTable($table);
$installer->endSetup();