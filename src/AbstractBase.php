<?php


namespace Jtl\Connector\XtcComponents;

use jtl\Connector\Core\Database\IDatabase;
use jtl\Connector\Modified\Installer\Config;

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
     * @var array
     */
    protected $shopConfig;

    /**
     * @var object
     */
    protected $connectorConfig;

    /**
     * AbstractBase constructor.
     * @param IDatabase $db
     * @param array $shopConfig
     * @param \stdClass $connectorConfig
     */
    public function __construct(IDatabase $db, array $shopConfig, \stdClass $connectorConfig)
    {
        $this->db = $db;
        $this->dbService = new DbService(DbService::createPDO($shopConfig['db']["host"], $shopConfig['db']["name"], $shopConfig['db']["user"], $shopConfig['db']["pass"]));
        $this->shopConfig = $shopConfig;
        $this->connectorConfig = $connectorConfig;
    }

    /**
     * @return string
     */
    abstract protected function getMainNamespace(): string;

    /**
     * @return string
     */
    protected function getMapperNamespace(): string
    {
        return sprintf('%s\\Mapper', $this->getMainNamespace());
    }

    /**
     * @return string
     */
    protected function getControllerNamespace(): string
    {
        return sprintf('%s\\Controller', $this->getMainNamespace());
    }


}
