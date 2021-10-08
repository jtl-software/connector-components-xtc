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
use jtl\Connector\Result\Action;

/**
 * Class AbstractController
 * @package Jtl\Connector\XtcComponents
 */
abstract class AbstractController extends AbstractBase implements IController
{
    /**
     * @var string
     */
    protected $controllerName;

    /**
     * @var object
     */
    protected $method;


    /**
     * AbstractController constructor.
     * @param IDatabase $db
     * @param array $shopConfig
     * @param \stdClass $connectorConfig
     * @throws \Exception
     */
    public function __construct(IDatabase $db, array $shopConfig, \stdClass $connectorConfig)
    {
        $this->controllerName = (new \ReflectionClass($this))->getShortName();
        parent::__construct($db, $shopConfig, $connectorConfig);
    }

    /**
     * @return string
     */
    public function getControllerName(): string
    {
        return $this->controllerName;
    }

    /**
     * @param string $controllerName
     * @return AbstractController
     */
    public function setControllerName(string $controllerName): AbstractController
    {
        $this->controllerName = $controllerName;
        return $this;
    }

    /**
     * @param string $controllerName
     * @return AbstractMapper
     * @throws \Exception
     */
    protected function createMapper(string $controllerName): AbstractMapper
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
     * @param QueryFilter $queryFilter
     * @return Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);
        $mapper = $this->createMapper($this->controllerName);

        try {
            $result = $mapper->pull(null, $queryFilter->getLimit());

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
    public function delete(DataModel $model): Action
    {
        $action = new Action();
        $action->setHandled(true);
        $mapper = $this->createMapper($this->controllerName);

        try {
            $result = $mapper->delete($model);

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
     * @param QueryFilter $queryFilter
     * @return Action
     * @throws \Exception
     */
    public function statistic(QueryFilter $queryFilter): Action
    {
        $action = new Action();
        $action->setHandled(true);
        $mapper = $this->createMapper($this->controllerName);

        try {
            $statModel = new Statistic();
            $statModel->setAvailable($mapper->statistic());
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
     * @throws \Exception
     */
    public function push(DataModel $model): Action
    {
        $action = new Action();
        $action->setHandled(true);
        $mapper = $this->createMapper($this->controllerName);

        try {
            $result = $mapper->push($model);
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
