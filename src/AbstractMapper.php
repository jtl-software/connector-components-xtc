<?php

namespace Jtl\Connector\XtcComponents;

use jtl\Connector\Core\Database\IDatabase;
use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Model\DataModel;
use jtl\Connector\Model\Identity;
use jtl\Connector\Core\Utilities\Language;

/**
 * Class AbstractMapper
 * @package Jtl\Connector\XtcComponents
 */
abstract class AbstractMapper extends AbstractBase
{
    /**
     * @var array
     */
    protected $mapperConfig;

    /**
     * @var string
     */
    protected $model;

    /**
     * @var null
     */
    protected $type;

    /**
     * AbstractMapper constructor.
     * @param IDatabase $db
     * @param array $shopConfig
     * @param \stdClass $connectorConfig
     */
    public function __construct(IDatabase $db, array $shopConfig, \stdClass $connectorConfig)
    {
        parent::__construct($db, $shopConfig, $connectorConfig);
        $this->model = sprintf("\\jtl\\Connector\\Model\\%s", (new \ReflectionClass($this))->getShortName());
        $this->type = null;
    }

    /**
     * @return string
     */
    abstract protected function getShopName(): string;

    /**
     * @param array $data
     * @return DataModel
     * @throws \Exception
     */
    public function generateModel(array $data): DataModel
    {
        $model = new $this->model();

        if (is_null($this->type)) {
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
     * @param DataModel $model
     * @param \stdClass $parentDbObj
     * @param DataModel|null $parentModel
     * @param false $addToParent
     * @return DataModel
     * @throws \Exception
     */
    public function generateDbObj(DataModel $model, \stdClass $parentDbObj = null, DataModel $parentModel = null, $addToParent = false)
    {
        $subMapper = [];

        if (is_null($this->type)) {
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

                if (method_exists($model, $getMethod)) {
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
            $this->pushDone($model, $dbObj);
        }

        return $model;
    }

    /**
     * @param null $parentData
     * @param null $limit
     * @return array
     * @throws \Exception
     */
    public function pull($parentData = null, $limit = null): array
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
     * @param int|null $limit
     * @return mixed
     */
    protected function executeQuery($parentData = null, ?int $limit = null)
    {
        $limitQuery = !is_null($limit) ? ' LIMIT ' . $limit : '';

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
     * @param DataModel $model
     * @param \stdClass|null $dbObj
     * @return array|array[]|DataModel|DataModel[]|mixed
     * @throws \Exception
     */
    public function push(DataModel $model, \stdClass $dbObj = null)
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
     * @param DataModel $data
     */
    public function delete(DataModel $data)
    {
    }

    /**
     * @return int
     */
    public function statistic(): int
    {
        if (isset($this->mapperConfig['statisticsQuery'])) {
            $result = $this->db->query($this->mapperConfig['statisticsQuery']);
            return isset($result[0]['total']) ? (int)$result[0]['total'] : 0;
        } elseif (isset($this->mapperConfig['query'])) {
            $result = $this->db->query($this->mapperConfig['query']);
            return count($result);
        } else {
            $objs = $this->db->query("SELECT count(*) as count FROM {$this->mapperConfig['table']} LIMIT 1");
        }

        return $objs !== null ? intval($objs[0]->count) : 0;
    }

    /**
     * @param string $country
     * @return false|int|string|null
     * @throws LanguageException
     */
    public function fullLocale(string $country)
    {
        return Language::convert($country);
    }

    /**
     * @param string $locale
     * @return mixed
     * @throws LanguageException
     */
    public function locale2id(string $locale)
    {
        $iso2 = Language::convert(null, $locale);
        $dbResult = $this->db->query('SELECT languages_id FROM languages WHERE code="' . $iso2 . '"');

        return $dbResult[0]['languages_id'];
    }

    /**
     * @param int $id
     * @return false|int|string|null
     * @throws LanguageException
     */
    public function id2locale(int $id)
    {
        $dbResult = $this->db->query('SELECT code FROM languages WHERE languages_id="' . $id . '"');
        return $this->fullLocale($dbResult[0]['code']);
    }

    /**
     * @param string $string
     * @return false|int|string|null
     * @throws LanguageException
     */
    public function string2locale(string $string)
    {
        $dbResult = $this->db->query('SELECT code FROM languages WHERE directory="' . $string . '"');
        return $this->fullLocale($dbResult[0]['code']);
    }

    /**
     * @param string $data
     * @return string
     */
    public function replaceZero(string $data): string
    {
        return ($data == 0) ? '' : $data;
    }

    /**
     * @return mixed
     */
    public function getCustomerGroups()
    {
        return $this->db->query("SELECT customers_status_id FROM customers_status GROUP BY customers_status_id ORDER BY customers_status_id");
    }

    /**
     * @param string $id
     * @return Identity
     */
    public function identity(string $id): Identity
    {
        return new Identity($id);
    }

    /**
     * @param string $name
     * @param string $p_replace
     * @return string|string[]|null
     */
    public function cleanName(string $name, string $p_replace = '-')
    {
        $search_array = ['ä', 'Ä', 'ö', 'Ö', 'ü', 'Ü', '&auml;', '&Auml;', '&ouml;', '&Ouml;', '&uuml;', '&Uuml;', 'ß', '&szlig;'];
        $replace_array = ['ae', 'Ae', 'oe', 'Oe', 'ue', 'Ue', 'ae', 'Ae', 'oe', 'Oe', 'ue', 'Ue', 'ss', 'ss'];
        $name = str_replace($search_array, $replace_array, $name);

        $replace_param = '/[^a-zA-Z0-9]/';
        $name = preg_replace($replace_param, $p_replace, $name);

        return $name;
    }

    /**
     * @param string $endpointId
     * @param string $table
     * @param string $imageColumn
     * @param string $whereColumn
     * @return string
     */
    protected function getDefaultColumnImageValue(string $endpointId, string $table, string $imageColumn, string $whereColumn): string
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
