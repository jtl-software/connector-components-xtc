<?php

namespace Jtl\Connector\XtcComponents;

use jtl\Connector\Core\Controller\IController;
use jtl\Connector\Core\Database\IDatabase;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\DataModel;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Modified\Installer\Config;
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
     * @var AbstractBaseMapper
     */
    protected $mapper;

    /**
     * AbstractBaseController constructor.
     * @param IDatabase $db
     * @param array $shopConfig
     * @param \stdClass $connectorConfig
     * @throws \Exception
     */
    public function __construct(IDatabase $db, array $shopConfig, \stdClass $connectorConfig, string $controllerName)
    {
        parent::__construct($db, $shopConfig, $connectorConfig);
        $this->controllerName = $controllerName;
        $this->mapper = $this->createMapper($this->controllerName);
    }

    /**
     * @param string $controllerName
     * @return AbstractBaseMapper
     * @throws \Exception
     */
    protected function createMapper(string $controllerName): AbstractBaseMapper
    {
        $class = sprintf('%s\\%s', $this->getMapperNamespace(), $controllerName);

        if (!class_exists($class)) {
            throw new \Exception("Class " . $class . " not available");
        }

        return new $class($this->db, $this->shopConfig, $this->connectorConfig);
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

    /**
     * @param QueryFilter $queryfilter
     * @return Action
     */
    public function pull(QueryFilter $queryfilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = $this->mapper->pull(null, $queryfilter->getLimit());

            $action->setResult($result);
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getFile() . ' (' . $exc->getLine() . '):' . $exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    /**
     * @param DataModel $model
     * @return Action
     */
    public function delete(DataModel $model)
    {
        $action = new Action();

        $action->setHandled(true);

        try {
            $result = $this->mapper->delete($model);

            $action->setResult($result);
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getFile() . ' (' . $exc->getLine() . '):' . $exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    /**
     * @param QueryFilter $filter
     * @return Action
     */
    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $statModel = new Statistic();
            $statModel->setAvailable($this->mapper->statistic());
            $statModel->setControllerName($this->controllerName);

            $action->setResult($statModel);
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    /**
     * @param DataModel $model
     * @return Action
     */
    public function push(DataModel $model)
    {
        $action = new Action();

        $action->setHandled(true);

        try {
            $result = $this->mapper->push($model);

            $action->setResult($result);
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getFile() . ' (' . $exc->getLine() . '):' . $exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }
}
