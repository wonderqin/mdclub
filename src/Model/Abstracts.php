<?php

declare(strict_types=1);

namespace MDClub\Model;

use MDClub\Library\Db;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 数据库操作封装，支持链式调用操作数据库
 *
 * @property-read Db                      $db
 * @property-read ServerRequestInterface  $request
 *
 * @method int max()
 * @method int min()
 * @method int avg()
 * @method int sum()
 */
abstract class Abstracts
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * 自动维护的 create_time 字段名
     */
    protected const CREATE_TIME = 'create_time';

    /**
     * 自动维护的 update_time 字段名
     */
    protected const UPDATE_TIME = 'update_time';

    /**
     * 软删除字段名
     */
    protected const DELETE_TIME = 'delete_time';

    /**
     * 表名
     *
     * @var string
     */
    public $table;

    /**
     * 主键列名
     *
     * @var string
     */
    public $primaryKey;

    /**
     * 表的所有字段名
     *
     * @var array
     */
    public $columns;

    /**
     * 允许排序的字段名
     *
     * @var array
     */
    public $allowOrderFields;

    /**
     * 允许筛选的字段名
     *
     * @var array
     */
    public $allowFilterFields;

    /**
     * 是否启用软删除
     *
     * @var bool
     */
    protected $softDelete = false;

    /**
     * 是否自动维护 create_time 和 update_time 字段
     *
     * @var bool
     */
    protected $timestamps = false;

    /**
     * 观察者类名
     *
     * @var string
     */
    protected $observe;

    /**
     * 观察者实例
     *
     * @var \MDClub\Observer\Abstracts
     */
    protected $observer;

    private $isForce;         // 删除时是否强制删除
    private $isWithTrashed;   // 查询结果包含软删除值
    private $isOnlyTrashed;   // 查询结果仅含软删除值
    private $isColumnExclude; // 是否排除查询字段
    private $orderData;       // 排序
    private $limitData;       // limit
    private $whereData;       // where
    private $matchData;       // 全文检索
    private $groupData;       // group
    private $havingData;      // having
    private $joinData;        // join
    private $columnData;      // 查询字段
    private $updateData;      // 需要更新的数据

    /**
     * 恢复默认状态
     */
    private function reset(): void
    {
        $this->isForce = false;
        $this->isWithTrashed = false;
        $this->isOnlyTrashed = false;
        $this->isColumnExclude = false;
        $this->orderData = [];
        $this->limitData = null;
        $this->whereData = [];
        $this->matchData = null;
        $this->groupData = null;
        $this->havingData = null;
        $this->joinData = null;
        $this->columnData = $this->columns;
        $this->updateData = [];
    }

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        if ($this->observe) {
            $this->observer = new $this->observe($container);
        }

        $this->reset();
    }

    public function __get(string $name)
    {
        return $this->container->get($name);
    }

    /**
     * 忽略软删除
     *
     * @return $this
     */
    public function force(): self
    {
        $this->isForce = true;

        return $this;
    }

    /**
     * 在结果中包含软删除的结果
     *
     * @return $this
     */
    public function withTrashed(): self
    {
        $this->isWithTrashed = true;

        return $this;
    }

    /**
     * 只返回软删除的结果
     *
     * @return $this
     */
    public function onlyTrashed(): self
    {
        $this->isOnlyTrashed = true;

        return $this;
    }

    /**
     * columns
     *
     * @param  string|array  $columns  字段数组
     * @param  bool          $exclude  是否为排除字段
     * @return Abstracts
     */
    public function field($columns, bool $exclude = false): self
    {
        $this->columnData = $columns;
        $this->isColumnExclude = $exclude;

        return $this;
    }

    /**
     * limit
     *
     * @param  int|array      $limit
     * @return Abstracts
     */
    public function limit($limit = null): self
    {
        $this->limitData = $limit;

        return $this;
    }

    /**
     * order
     *
     * ->order(['field' => 'DESC']) // 传入数组
     * ->order('field')             // 传入一个字符串，默认按 ASC 排序
     * ->order('field', 'DESC')     // 传入两个字符串，指定排序方式
     *
     * @param  string|array    $order
     * @param  string          $sort
     * @return Abstracts
     */
    public function order($order = [], string $sort = null): self
    {
        if (!is_array($order)) {
            if ($sort === null) {
                $sort = 'ASC';
            }

            $order = [$order => $sort];
        }

        $this->orderData = array_merge($this->orderData, $order);

        return $this;
    }

    /**
     * match
     *
     * @param  array          $match
     * @return Abstracts
     */
    public function match(array $match = null): self
    {
        $this->matchData = $match;

        return $this;
    }

    /**
     * group
     *
     * @param  array         $group
     * @return Abstracts
     */
    public function group(array $group = null): self
    {
        $this->groupData = $group;

        return $this;
    }

    /**
     * having
     *
     * @param  array         $having
     * @return Abstracts
     */
    public function having(array $having = null): self
    {
        $this->havingData = $having;

        return $this;
    }

    /**
     * join
     *
     * @param  array         $join
     * @return Abstracts
     */
    public function join(array $join = null): self
    {
        $this->joinData = $join;

        return $this;
    }

    /**
     * where
     *
     * ->where(['id' => 'value']) // 传入数组
     * ->where('id', 'value')     // 传入两个参数
     *
     * @param  string|array    $where
     * @param  mixed           $value
     * @return Abstracts
     */
    public function where($where = [], $value = null): self
    {
        if (!is_array($where)) {
            $where = [$where => $value];
        }

        $this->whereData = array_merge($this->whereData, $where);

        return $this;
    }

    /**
     * 增加字段值
     *
     * @param  string         $field 字段名
     * @param  int            $step  步进值
     * @return Abstracts
     */
    public function inc(string $field, int $step = 1): self
    {
        unset($this->updateData["${field}[-]"]);

        $this->updateData = array_merge($this->updateData, ["${field}[+]" => $step]);

        return $this;
    }

    /**
     * 减少字段值
     *
     * @param  string         $field 字段名
     * @param  int            $step  步进值
     * @return Abstracts
     */
    public function dec(string $field, int $step = 1): self
    {
        unset($this->updateData["${field}[+]"]);

        $this->updateData = array_merge($this->updateData, ["${field}[-]" => $step]);

        return $this;
    }

    /**
     * 设置更新的数据
     *
     * ->set(['id' => 'value']) // 传入数组
     * ->set('id', 'value')     // 传入两个参数
     *
     * @param  string|array   $data
     * @param  mixed          $value
     * @return Abstracts
     */
    public function set($data, $value = null): self
    {
        if (!is_array($data)) {
            $data = [$data => $value];
        }

        $this->updateData = array_merge($this->updateData, $data);

        return $this;
    }

    /**
     * 获取供 medoo 使用的 where 参数
     *
     * @param  array  $where
     * @return array
     */
    private function getWhere(array $where = null): array
    {
        if (!$where) {
            $where = $this->whereData;
        }

        $map = [
            'ORDER' => $this->orderData,
            'MATCH' => $this->matchData,
            'GROUP' => $this->groupData,
            'HAVING' => $this->havingData,
            'LIMIT' => $this->limitData,
        ];

        foreach ($map as $name => $value) {
            if (!$value) {
                continue;
            }

            if (!$this->joinData) {
                $where[$name] = $value;
                continue;
            }

            // 含 join，且字段中不含表名时，自动添加表名
            if ($name === 'ORDER' || $name === 'MATCH' || $name === 'HAVING') {
                foreach ($value as $field => $val) {
                    if ($name === 'MATCH' && $field === 'mode') {
                        continue;
                    }

                    if (strpos($field, '.') === false) {
                        $value[$this->table . '.' . $field] = $val;
                        unset($value[$field]);
                    }
                }

                $where[$name] = $value;
            }

            if ($name === 'GROUP') {
                foreach ($value as $key => $field) {
                    if (strpos($field, '.') === false) {
                        $value[$key] = $this->table . '.' . $field;
                    }
                }

                $where[$name] = $value;
            }
        }

        // 添加软删除条件
        if ($this->softDelete && !$this->isForce) {
            $deleteTimeField = $this->joinData
                ? $this->table . '.' . static::DELETE_TIME
                : static::DELETE_TIME;

            $where[$deleteTimeField] = 0;

            if ($this->isWithTrashed || $this->isOnlyTrashed) {
                unset($where[$deleteTimeField]);
            }

            if ($this->isOnlyTrashed) {
                $where[$deleteTimeField . '[>]'] = 0;
            }
        }

        return $where;
    }

    /**
     * 获取要查询的列
     *
     * @return array
     */
    private function getColumns(): array
    {
        $columns = $this->columnData;
        $isColumnExclude = $this->isColumnExclude;

        if (is_string($columns)) {
            $columns = trim($columns);
        }

        if ($columns && is_string($columns)) {
            $columns = explode(',', $columns);
            $columns = array_map('trim', $columns);
        }

        if (!$columns) {
            $columns = $this->columns;

            if ($isColumnExclude) {
                $isColumnExclude = false;
            }
        }

        if ($isColumnExclude) {
            $columnExclude = $columns;
            $columns = $this->columns;

            foreach ($columns as $key => $item) {
                if (in_array($item, $columnExclude, true)) {
                    unset($columns[$key]);
                }
            }
        }

        // 含 join，且字段中不含表名时，自动添加表名
        if ($this->joinData) {
            foreach ($columns as $key => $item) {
                if (strpos($item, '.') === false) {
                    $columns[$key] = $this->table . '.' . $item;
                }
            }
        }

        return $columns;
    }

    /**
     * 插入数据前对数据进行处理
     *
     * @param  array $data 处理前的数据
     * @return array       处理后的数据
     */
    protected function beforeInsert(array $data): array
    {
        return $data;
    }

    /**
     * 更新数据前对数据进行处理
     *
     * @param  array $data 处理前的数据
     * @return array       处理后的数据
     */
    protected function beforeUpdate(array $data): array
    {
        return $data;
    }

    /**
     * 根据条件获取多条数据
     *
     * @param  array $primaryValues 若传入了该参数，则根据主键值数组获取，否则根据前面的 where 条件获取；可传入多个主键组成的数组
     * @return array
     */
    public function select($primaryValues = null): array
    {
        $join = $this->joinData;
        $columns = $this->getColumns();
        $where = $this->getWhere($primaryValues ? [$this->primaryKey => $primaryValues] : null);

        $this->reset();

        $args = $join ? [$join, $columns, $where] : [$columns, $where];

        return $this->db->select($this->table, ...$args);
    }

    /**
     * 获取指定列名对应的值的数组，第二个参数可指定键名
     *
     * @param  string $value 列名
     * @param  string $key
     * @return array
     */
    public function pluck(string $value, string $key = null): array
    {
        $fields = $key ? [$key, $value] : [$value];
        $result = $this->field($fields)->select();

        return array_column($result, $value, $key);
    }

    /**
     * 根据条件获取带分页的数据
     *
     * @param  bool|int $simple 是否使用简单分页（简单分页不含总数据量）；已知数据总量的情况下，可以传入数据总量
     * @return array
     */
    public function paginate($simple = false): array
    {
        if (is_int($simple)) {
            $total = $simple;
            $simple = false;
        }

        // page 参数
        $page = (int) ($this->request->getQueryParams()['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        // per_page 参数
        $perPage = (int) ($this->request->getQueryParams()['per_page'] ?? 15);
        if ($perPage > 100) {
            $perPage = 100;
        } elseif ($perPage < 1) {
            $perPage = 1;
        }

        $this->limit([$perPage * ($page - 1), $perPage]);

        if (!$simple && !isset($total)) {
            $total = (int) $this->count(false);
        }

        $result = [
            'data' => $this->select(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];

        if (isset($total)) {
            $result['pagination']['total']    = $total;
            $result['pagination']['pages']    = ceil($total / $perPage);
            $result['pagination']['previous'] = $page > 1 ? $page - 1 : null;
            $result['pagination']['next']     = $result['pagination']['pages'] > $page ? $page + 1 : null;
        }

        return $result;
    }

    /**
     * 插入数据
     *
     * NOTE: 仅插入单条数据会触发 Observer 方法，插入多条数据时不触发
     *
     * @param  array   $data_array 数据数组、或多个数据组成的二维数组
     * @return string              最后一条记录的ID
     */
    public function insert(array $data_array): string
    {
        $isNestedArray = isset($data_array[0]);

        if (!$isNestedArray) {
            $data_array = [$data_array];
        }

        foreach ($data_array as &$data) {
            $data = $this->beforeInsert($data);

            if ($this->timestamps && static::CREATE_TIME) {
                $data[static::CREATE_TIME] = $this->request->getServerParams()['REQUEST_TIME'];
            }

            if ($this->timestamps && static::UPDATE_TIME) {
                $data[static::UPDATE_TIME] = $this->request->getServerParams()['REQUEST_TIME'];
            }

            if ($this->softDelete && static::DELETE_TIME) {
                $data[static::DELETE_TIME] = 0;
            }
        }

        unset($data);

        $this->db->insert($this->table, $data_array);
        $id = $this->db->id();

        if ($this->observer && !$isNestedArray) {
            $data_array[0][$this->primaryKey] = $id;
            $this->observer->created($data_array[0]);
        }

        return $id;
    }

    /**
     * 更新数据
     *
     * ->update(['id' => 'value']) // 传入数组
     * ->update('id', 'value')     // 传入两个参数
     *
     * NOTE: 仅通过主键作为唯一查询条件更新时会触发 Observer 方法，通过其他条件更新时不触发 todo 可能存在软删除查询条件
     * 例如：
     *
     * @param  string|array $data  需要更新的数据
     * @param  mixed        $value
     * @return int
     */
    public function update($data = [], $value = null): int
    {
        if (!is_array($data)) {
            $data = [$data => $value];
        }

        $data = array_merge($this->updateData, $data);
        $data = $this->beforeUpdate($data);

        if ($this->timestamps && static::UPDATE_TIME) {
            $data[static::UPDATE_TIME] = $this->request->getServerParams()['REQUEST_TIME'];
        }

        $where = $this->getWhere();
        $this->reset();
        $query = $this->db->update($this->table, $data, $where);

        // 触发 Observer 方法
        if ($this->observer && isset($where[$this->primaryKey]) && count($where) === 1) {
            is_array($where[$this->primaryKey])
                ? $this->observer->updatedMultiple($where[$this->primaryKey])
                : $this->observer->updated($where[$this->primaryKey]);
        }

        return $query->rowCount();
    }

    /**
     * 根据条件删除数据
     *
     * NOTE: 仅通过主键作为唯一条件删除时会触发 Observer 方法，通过其他条件删除不触发 todo 未完成
     *
     * @param  int|string|array  $primaryValues 若传入该参数，则根据主键删除，否则根据前面的 where 条件删除；可传入多个主键组成的数组
     * @return int
     */
    public function delete($primaryValues = null): int
    {
        $force = $this->isForce;
        $join = $this->joinData;
        $where = $this->getWhere($primaryValues ? [$this->primaryKey => $primaryValues] : null);

        $this->reset();

        if ($this->softDelete && !$force) {
            $deleteTimeField = $join
                ? $this->table . '.' . static::DELETE_TIME
                : static::DELETE_TIME;

            $query = $this->db->update($this->table, [
                $deleteTimeField => $this->request->getServerParams()['REQUEST_TIME']
            ], $where);
        } else {
            $query = $this->db->delete($this->table, $where);
        }

        return $query->rowCount();
    }

    /**
     * 根据条件获取一条数据
     *
     * @param  int|string  $primaryValue 若传入了该参数，则根据主键获取，否则根据前面的 where 参数获取
     * @return null|array
     */
    public function get($primaryValue = null): ?array
    {
        $join = $this->joinData;
        $columns = $this->getColumns();
        $where = $this->getWhere($primaryValue ? [$this->primaryKey => $primaryValue] : null);

        $this->reset();

        $args = $join ? [$join, $columns, $where] : [$columns, $where];

        return $this->db->get($this->table, ...$args);
    }

    /**
     * 判断数据是否存在
     *
     * @param  int|string  $primaryValue  若传入了该参数，则根据主键获取，否则根据前面的 where 参数获取
     * @return bool
     */
    public function has($primaryValue = null): bool
    {
        $join = $this->joinData;
        $where = $this->getWhere($primaryValue ? [$this->primaryKey => $primaryValue] : null);

        $this->reset();

        $args = $join ? [$join, $where] : [$where];

        return $this->db->has($this->table, ...$args);
    }

    /**
     * 查询总数
     *
     * @param  bool $reset 查询完是否重置
     * @return int
     */
    public function count($reset = true): int
    {
        $join = $this->joinData;
        $where = $this->getWhere();

        unset($where['ORDER'], $where['LIMIT']);

        if ($reset) {
            $this->reset();
        }

        $args = $join ? [$join, '*', $where] : [$where];

        return $this->db->count($this->table, ...$args);
    }

    /**
     * @param  string $name
     * @param  array  $arguments
     * @return int
     */
    public function __call($name, $arguments)
    {
        $aggregation = ['avg', 'max', 'min', 'sum'];

        if (!in_array($name, $aggregation, true)) {
            throw new \BadMethodCallException('Call to undefined method ' . self::class . '::' . $name);
        }

        $join = $this->joinData;
        $columns = $this->getColumns();
        $where = $this->getWhere();

        $this->reset();

        $args = $join ? [$join, $columns, $where] : [$columns, $where];

        return $this->$name($this->table, $args);
    }

    /**
     * 获取查询列表时的排序
     *
     * order=field 表示 field ASC
     * order=-field 表示 field DESC
     *
     * @param  array $defaultOrder     默认排序；query 参数不存在时，该参数才生效
     * @param  array $allowOrderFields 允许排序的字段，为 null 时，通过 getAllowOrderFields 方法获取
     * @return array
     */
    public function getOrderFromRequest(array $defaultOrder = [], array $allowOrderFields = null): array
    {
        $result = [];
        $order = $this->request->getQueryParams()['order'] ?? '';

        if ($allowOrderFields === null) {
            $allowOrderFields = $this->allowOrderFields;
        }

        if ($order) {
            if (strpos($order, '-') === 0) {
                $result[substr($order, 1)] = 'DESC';
            } else {
                $result[$order] = 'ASC';
            }

            $result = collect($result)->only($allowOrderFields)->all();
        }

        if (!$result) {
            $result = $defaultOrder;
        }

        return $result;
    }

    /**
     * 查询列表时的条件
     *
     * @param  array $defaultFilter     默认条件。该条件将覆盖 query 中的同名参数
     * @param  array $allowFilterFields 允许作为条件的字段，为 null 时，通过 getAllowFilterFields 方法获取
     * @return array
     */
    public function getWhereFromRequest(array $defaultFilter = [], array $allowFilterFields = null): array
    {
        if ($allowFilterFields === null) {
            $allowFilterFields = $this->allowFilterFields;
        }

        $result = $this->request->getQueryParams();
        $result = collect($result)->only($allowFilterFields)->merge($defaultFilter)->all();

        return $result;
    }
}