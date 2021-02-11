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
abstract class AbstractBaseController extends AbstractBase implements IController
{
    /**
     * @var object
     */
    protected $method;

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
