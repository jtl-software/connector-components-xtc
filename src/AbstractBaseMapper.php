<?php

namespace Jtl\Connector\XtcComponents;

use jtl\Connector\Core\Database\IDatabase;
use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Model\DataModel;
use jtl\Connector\Model\Identity;
use jtl\Connector\Core\Utilities\Language;

/**
 * Class AbstractBaseMapper
 * @package Jtl\Connector\XtcComponents
 */
abstract class AbstractBaseMapper
{
    /**
     * @var IDatabase
     */
    protected $db;

    /**
     * @var array
     */
    protected $mapperConfig;

    /**
     * @var object
     */
    protected $shopConfig;

    /**
     * @var object
     */
    protected $connectorConfig;

    /**
     * @var null
     */
    protected $type;

    /**
     * @var string
     */
    protected $model;

    /**
     * AbstractBaseMapper constructor.
     * @param IDatabase $db
     * @param $shopConfig
     * @param $connectorConfig
     */
    public function __construct(IDatabase $db, $shopConfig, $connectorConfig)
    {
        $this->db = $db;
        $this->shopConfig = $shopConfig;
        $this->connectorConfig = $connectorConfig;
        $this->model = sprintf("\\jtl\\Connector\\Model\\%s", (new \ReflectionClass($this))->getShortName());
        $this->type = null;
    }

    /**
     * @return string
     */
    abstract protected function getShopName(): string;

    /**
     * @return string
     */
    abstract protected function getMapperNamespace(): string;

    /**
     * @param array $data
     * @return DataModel
     * @throws \Exception
     */
    public function generateModel(array $data): DataModel
    {
        $model = new $this->model();

        if (!$this->type) {
            $this->type = $model->getModelType();
        }

        foreach ($this->mapperConfig['mapPull'] as $host => $endpoint) {
            $value = null;

            if (!$this->type->getProperty($host)) {
                throw new \Exception("Property " . $host . " not found");
            }

            if ($this->type->getProperty($host)->isNavigation()) {
                list($endpoint, $setMethod) = explode('|', $endpoint);

                $subMapperClass = sprintf("\\%s\\%s", $this->getMapperNamespace(), $endpoint);

                if (!class_exists($subMapperClass)) {
                    throw new \Exception("There is no mapper for " . $endpoint);
                } else {
                    if (!method_exists($model, $setMethod)) {
                        throw new \Exception("Set method " . $setMethod . " does not exists");
                    }

                    $subMapper = new $subMapperClass($this->db, $this->shopConfig, $this->connectorConfig);

                    $values = $subMapper->pull($data);

                    foreach ($values as $obj) {
                        $model->$setMethod($obj);
                    }
                }
            } else {
                if (isset($data[$endpoint])) {
                    $value = $data[$endpoint];
                } elseif (method_exists(get_class($this), $host)) {
                    $value = $this->$host($data);
                } else {
                    $value = '';
                }

                if ($this->type->getProperty($host)->isIdentity()) {
                    $value = new Identity($value);
                } else {
                    $type = $this->type->getProperty($host)->getType();

                    if ($type == "DateTime" && !is_null($value)) {
                        $value = new \DateTime($value);
                        if ((int)$value->format("Y") <= 0) {
                            $value = null;
                        }
                    } else {
                        settype($value, $type);
                    }
                }

                $setMethod = 'set' . ucfirst($host);
                $model->$setMethod($value);
            }
        }

        if (method_exists(get_class($this), 'addData')) {
            $this->addData($model, $data);
        }

        return $model;
    }

    /**
     * @param $model
     * @param $parentDbObj
     * @param null $parentModel
     * @param false $addToParent
     * @return array|mixed
     * @throws \Exception
     */
    public function generateDbObj(DataModel $model, $parentDbObj, DataModel $parentModel = null, $addToParent = false)
    {
        $subMapper = [];

        if (!$this->type) {
            $this->type = $model->getModelType();
        }

        $dbObj = new \stdClass();

        foreach ($this->mapperConfig['mapPush'] as $endpoint => $host) {
            if (is_null($host) && method_exists(get_class($this), $endpoint)) {
                $value = $this->$endpoint($model, $parentDbObj, $parentModel);
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format("Y-m-d H:i:s");
                }
                $dbObj->$endpoint = $value;
            } elseif ($this->type->getProperty($host)->isNavigation()) {
                list($preEndpoint, $preNavSetMethod, $preMapper) = array_pad(explode('|', $endpoint), 3, null);

                if ($preMapper) {
                    $preSubMapperClass = sprintf('\\%s\\%s', $this->getMapperNamespace(), $preEndpoint);

                    if (!class_exists($preSubMapperClass)) {
                        throw new \Exception("There is no mapper for " . $host);
                    } else {
                        $preSubMapper = new $preSubMapperClass($this->db, $this->shopConfig, $this->connectorConfig);

                        $values = $preSubMapper->push($model, $dbObj);

                        if (!is_null($values) && is_array($values)) {
                            foreach ($values as $setObj) {
                                $model->$preNavSetMethod($setObj);
                            }
                        }
                    }
                } else {
                    $subMapper[$endpoint] = $host;
                }
            } else {
                $value = null;

                $getMethod = 'get' . ucfirst($host);
                $setMethod = 'set' . ucfirst($host);

                if (isset($model) && method_exists($model, $getMethod)) {
                    $value = $model->$getMethod();
                } else {
                    throw new \Exception("Cannot call get method '" . $getMethod . "' in entity '" . $this->model . "'");
                }

                if (isset($value)) {
                    if ($this->type->getProperty($host)->isIdentity()) {
                        $model->$setMethod($value);

                        $idVal = $value->getEndpoint();

                        if (!empty($idVal)) {
                            $value = $idVal;
                        }
                    } else {
                        $type = $this->type->getProperty($host)->getType();
                        if ($type == "DateTime") {
                            $value = $value->format('Y-m-d H:i:s');
                        } elseif ($type == "boolean") {
                            settype($value, "integer");
                        }
                    }

                    $dbObj->$endpoint = $value;
                }
            }
        }

        if (!$addToParent) {
            $whereKey = null;
            $whereValue = null;

            if (isset($this->mapperConfig['where'])) {
                $whereKey = $this->mapperConfig['where'];

                if (is_array($whereKey)) {
                    $whereValue = [];
                    foreach ($whereKey as $key) {
                        $whereValue[] = $dbObj->{$key};
                    }
                } else {
                    $whereValue = $dbObj->{$whereKey};
                }
            }

            $checkEmpty = get_object_vars($dbObj);

            if (!empty($checkEmpty)) {
                if (isset($this->mapperConfig['identity'])) {
                    $currentId = $model->{$this->mapperConfig['identity']}()->getEndpoint();
                }

                if (!empty($currentId)) {
                    $insertResult = $this->db->updateRow($dbObj, $this->mapperConfig['table'], $whereKey, $whereValue);

                    $insertResult->setKey($currentId);
                } else {
                    if (isset($this->mapperConfig['where'])) {
                        unset($dbObj->{$this->mapperConfig['where']});
                    }

                    $insertResult = $this->db->deleteInsertRow($dbObj, $this->mapperConfig['table'], $whereKey, $whereValue);
                }

                if (isset($this->mapperConfig['identity'])) {
                    $model->{$this->mapperConfig['identity']}()->setEndpoint($insertResult->getKey());
                }
            }
        } else {
            foreach (get_class_vars($dbObj) as $key => $value) {
                $parentDbObj->$key = $value;
            }
        }

        foreach ($subMapper as $endpoint => $host) {
            list($endpoint, $navSetMethod) = explode('|', $endpoint);

            $subMapperClass = sprintf('\\%s\\%s', $this->getMapperNamespace(), $endpoint);

            if (!class_exists($subMapperClass)) {
                throw new \Exception("There is no mapper for " . $host);
            } else {
                $subMapper = new $subMapperClass($this->db, $this->shopConfig, $this->connectorConfig);

                $values = $subMapper->push($model);

                if (!is_null($values) && is_array($values)) {
                    foreach ($values as $setObj) {
                        $model->$navSetMethod($setObj);
                    }
                }
            }
        }

        if (method_exists(get_class($this), 'pushDone')) {
            $this->pushDone($model, $dbObj, $model);
        }

        return $model;
    }

    /**
     * @param null $parentData
     * @param null $limit
     * @return array
     * @throws \Exception
     */
    public function pull($parentData = null, $limit = null)
    {
        $dbResult = $this->executeQuery($parentData, $limit);

        $return = [];

        if (isset($dbResult)) {
            foreach ($dbResult as $data) {
                $return[] = $this->generateModel($data);
            }
        }

        return $return;
    }

    /**
     * @param null $parentData
     * @param null $limit
     * @return array|bool|\jtl\Connector\Core\Database\multitype|number|null
     */
    protected function executeQuery($parentData = null, $limit = null)
    {
        $limitQuery = isset($limit) ? ' LIMIT ' . $limit : '';

        if (isset($this->mapperConfig['query'])) {
            if (!is_null($parentData)) {
                $query = preg_replace_callback(
                    '/\[\[(\w+)\]\]/',
                    function ($match) use ($parentData) {
                        return $parentData[$match[1]];
                    },
                    $this->mapperConfig['query']
                );
            } else {
                $query = $this->mapperConfig['query'];
            }

            $query .= $limitQuery;
        } else {
            $query = 'SELECT * FROM ' . $this->mapperConfig['table'] . $limitQuery;
        }

        return $this->db->query($query);
    }

    /**
     * @param $model
     * @param null $dbObj
     * @return array|mixed
     * @throws \Exception
     */
    public function push($model, $dbObj = null)
    {
        if (isset($this->mapperConfig['getMethod'])) {
            $childrenGetter = $this->mapperConfig['getMethod'];
            return array_map(function (DataModel $childModel) use ($dbObj, $model) {
                return $this->generateDbObj($childModel, $dbObj, $model);
            }, $model->$childrenGetter());
        }

        return $this->generateDbObj($model, $dbObj);
    }

    /**
     * @param $data
     */
    public function delete($data)
    {
    }

    /**
     * @return int
     */
    public function statistic()
    {
        if (isset($this->mapperConfig['statisticsQuery'])) {
            $result = $this->db->query($this->mapperConfig['statisticsQuery']);
            return isset($result[0]['total']) ? (int)$result[0]['total'] : 0;
        } elseif (isset($this->mapperConfig['query'])) {
            $result = $this->db->query($this->mapperConfig['query']);
            return count($result);
        } else {
            $objs = $this->db->query("SELECT count(*) as count FROM {$this->mapperConfig['table']} LIMIT 1", ["return" => "object"]);
        }

        return $objs !== null ? intval($objs[0]->count) : 0;
    }

    /**
     * @param $country
     * @return false|int|string|null
     * @throws LanguageException
     */
    public function fullLocale($country)
    {
        if (isset($country)) {
            return Language::convert($country);
        }
    }

    /**
     * @param $locale
     * @return mixed
     * @throws LanguageException
     */
    public function locale2id($locale)
    {
        if (isset($locale)) {
            $iso2 = Language::convert(null, $locale);
            $dbResult = $this->db->query('SELECT languages_id FROM languages WHERE code="' . $iso2 . '"');

            return $dbResult[0]['languages_id'];
        }
    }

    /**
     * @param $id
     * @return false|int|string|null
     * @throws LanguageException
     */
    public function id2locale($id)
    {
        if (isset($id)) {
            $dbResult = $this->db->query('SELECT code FROM languages WHERE languages_id="' . $id . '"');

            return $this->fullLocale($dbResult[0]['code']);
        }
    }

    /**
     * @param $string
     * @return false|int|string|null
     * @throws LanguageException
     */
    public function string2locale($string)
    {
        if (isset($string)) {
            $dbResult = $this->db->query('SELECT code FROM languages WHERE directory="' . $string . '"');

            return $this->fullLocale($dbResult[0]['code']);
        }
    }

    /**
     * @param $data
     * @return mixed|string
     */
    public function replaceZero($data)
    {
        return ($data == 0) ? '' : $data;
    }

    /**
     * @return array|bool|\jtl\Connector\Core\Database\multitype|number|null
     */
    public function getCustomerGroups()
    {
        return $this->db->query("SELECT customers_status_id FROM customers_status GROUP BY customers_status_id ORDER BY customers_status_id");
    }

    /**
     * @param $id
     * @return Identity
     */
    public function identity($id)
    {
        return new Identity($id);
    }

    /**
     * @param $name
     * @param string $p_replace
     * @return string|string[]|null
     */
    public function cleanName($name, $p_replace = '-')
    {
        $search_array = ['ä', 'Ä', 'ö', 'Ö', 'ü', 'Ü', '&auml;', '&Auml;', '&ouml;', '&Ouml;', '&uuml;', '&Uuml;', 'ß', '&szlig;'];
        $replace_array = ['ae', 'Ae', 'oe', 'Oe', 'ue', 'Ue', 'ae', 'Ae', 'oe', 'Oe', 'ue', 'Ue', 'ss', 'ss'];
        $name = str_replace($search_array, $replace_array, $name);

        $replace_param = '/[^a-zA-Z0-9]/';
        $name = preg_replace($replace_param, $p_replace, $name);

        return $name;
    }

    /**
     * @param $endpointId
     * @param $table
     * @param $imageColumn
     * @param $whereColumn
     * @return mixed|string
     */
    protected function getDefaultColumnImageValue($endpointId, $table, $imageColumn, $whereColumn)
    {
        $image = '';
        if (!empty($endpointId)) {
            $dbImage = $this->db->query(
                sprintf('SELECT %s FROM %s WHERE %s = %s', $imageColumn, $table, $whereColumn, $endpointId)
            );

            if (isset($dbImage[0][$imageColumn])) {
                $image = $dbImage[0][$imageColumn];
            }
        }
        return $image;
    }
}
