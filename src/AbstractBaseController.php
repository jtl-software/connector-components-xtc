<?php

namespace Jtl\Connector\XtcComponents;

use jtl\Connector\Core\Controller\IController;
use jtl\Connector\Core\Database\IDatabase;
use jtl\Connector\Core\Exception\NotImplementedException;
use jtl\Connector\Core\Model\DataModel;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Result\Action;

/**
 * Class AbstractBaseController
 * @package Jtl\Connector\XtcComponents
 */
abstract class AbstractBaseController implements IController
{
    /**
     * @var IDatabase
     */
    protected $db;

    /**
     * @var object
     */
    protected $shopConfig;

    /**
     * @var object
     */
    protected $connectorConfig;

    /**
     * @var object
     */
    protected $method;

    /**
     * AbstractBaseController constructor.
     * @param IDatabase $db
     * @param object $shopConfig
     * @param object $connectorConfig
     */
    public function __construct(IDatabase $db, $shopConfig, $connectorConfig)
    {
        $this->db = $db;
        $this->shopConfig = $shopConfig;
        $this->connectorConfig = $connectorConfig;
    }

    /**
     * @param DataModel $model
     * @return Action|void
     * @throws NotImplementedException
     */
    public function push(DataModel $model)
    {
        throw new NotImplementedException();
    }

    /**
     * @param QueryFilter $queryFilter
     * @return Action|void
     * @throws NotImplementedException
     */
    public function pull(QueryFilter $queryFilter)
    {
        throw new NotImplementedException();
    }

    /**
     * @param DataModel $model
     * @return Action|void
     * @throws NotImplementedException
     */
    public function delete(DataModel $model)
    {
        throw new NotImplementedException();
    }

    /**
     * @param QueryFilter $queryFilter
     * @return Action|void
     * @throws NotImplementedException
     */
    public function statistic(QueryFilter $queryFilter)
    {
        throw new NotImplementedException();
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }
}
