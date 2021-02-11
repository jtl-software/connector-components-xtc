<?php


namespace Jtl\Connector\XtcComponents;


use jtl\Connector\Core\Database\IDatabase;

abstract class AbstractBase
{
    /**
     * @var IDatabase
     */
    protected $db;

    /**
     * @var DbService
     */
    protected $dbService;

    /**
     * @var object
     */
    protected $shopConfig;

    /**
     * @var object
     */
    protected $connectorConfig;

    /**
     * AbstractBase constructor.
     * @param IDatabase $db
     * @param $shopConfig
     * @param $connectorConfig
     */
    public function __construct(IDatabase $db, $shopConfig, $connectorConfig)
    {
        $this->db = $db;
        $this->dbService = new DbService(DbService::createPDO($shopConfig['db']["host"], $shopConfig['db']["name"], $shopConfig['db']["user"], $shopConfig['db']["pass"]));
        $this->shopConfig = $shopConfig;
        $this->connectorConfig = $connectorConfig;
    }
}