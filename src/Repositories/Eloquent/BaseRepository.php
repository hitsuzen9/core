<?php

namespace Redmix0901\Core\Repositories\Eloquent;

use Redmix0901\Core\Criteria\Contracts\CriteriaContract;
use Redmix0901\Core\Repositories\Interfaces\BaseRepositoryInterface;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;

abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * @var Eloquent | Model
     */
    protected $model;

    /**
     * @var Eloquent | Model
     */
    protected $originalModel;

    /**
     * @var array
     */
    protected $criteria = [];

    /**
     * @var bool
     */
    protected $skipCriteria = false;

    /**
     * @var string
     */
    protected $screen = '';

    /**
     * RepositoriesAbstract constructor.
     * @param Model|Eloquent $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->originalModel = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function getScreen(): string
    {
        return $this->screen;
    }

    /**
     * {@inheritdoc}
     */
    public function applyBeforeExecuteQuery($data, $screen, $is_single = false)
    {
        // if (is_in_admin()) {
        //     if (!$is_single) {
        //         $data = apply_filters(BASE_FILTER_BEFORE_GET_ADMIN_LIST_ITEM, $data, $this->originalModel, $screen);
        //     }
        // } elseif (!$is_single) {
        //     $data = apply_filters(BASE_FILTER_BEFORE_GET_FRONT_PAGE_ITEM, $data, $this->originalModel, $screen);
        // } else {
        //     $data = apply_filters(BASE_FILTER_BEFORE_GET_SINGLE, $data, $this->originalModel, $screen);
        // }

        $this->resetModel();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * {@inheritdoc}
     */
    public function getTable()
    {
        return $this->model->getTable();
    }

    /**
     * {@inheritdoc}
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * {@inheritdoc}
     */
    public function pushCriteria(CriteriaContract $criteria)
    {
        $this->criteria[get_class($criteria)] = $criteria;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dropCriteria($criteria)
    {
        $className = $criteria;
        if (is_object($className)) {
            $className = get_class($criteria);
        }

        if (isset($this->criteria[$className])) {
            unset($this->criteria[$className]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function skipCriteria($bool = true)
    {
        $this->skipCriteria = $bool;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function applyCriteria()
    {
        if ($this->skipCriteria === true) {
            return $this;
        }
        $criteria = $this->getCriteria();
        if ($criteria) {
            foreach ($criteria as $cr) {
                $this->model = $cr->apply($this->model, $this);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getByCriteria(CriteriaContract $criteria)
    {
        return $criteria->apply($this->originalModel, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstBy(array $condition = [], array $select = ['*'], array $with = [])
    {
        $this->applyCriteria();

        $this->make($with);

        if (!empty($select)) {
            $data = $this->model->where($condition)->select($select);
        } else {
            $data = $this->model->where($condition);
        }

        return $this->applyBeforeExecuteQuery($data, $this->screen, true)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function make(array $with = [])
    {
        if (!empty($with)) {
            $this->model = $this->model->with($with);
        }

        return $this->model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById($id, array $with = [])
    {
        $this->applyCriteria();

        $data = $this->make($with)->where('id', $id);
        $data = $this->applyBeforeExecuteQuery($data, $this->screen, true);
        $data = $data->first();

        $this->resetModel();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail($id, array $with = [])
    {
        $this->applyCriteria();

        $data = $this->make($with)->where('id', $id);
        $data = $this->applyBeforeExecuteQuery($data, $this->screen, true);
        $result = $data->first();
        $this->resetModel();

        if (!empty($result)) {
            return $result;
        }

        throw (new ModelNotFoundException)->setModel(
            get_class($this->originalModel), $id
        );
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $with = [])
    {
        $this->applyCriteria();

        $data = $this->make($with);

        return $this->applyBeforeExecuteQuery($data, $this->screen)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function pluck($column, $key = null)
    {
        $this->applyCriteria();

        $select = [$column];
        if (!empty($key)) {
            $select = [$column, $key];
        }

        $data = $this->model->select($select);

        return $this->applyBeforeExecuteQuery($data, $this->screen)->pluck($column, $key)->all();
    }

    /**
     * {@inheritdoc}
     */
    public function allBy(array $condition, array $with = [], array $select = ['*'])
    {
        $this->applyCriteria();

        if (!empty($condition)) {
            $this->applyConditions($condition);
        }

        $data = $this->make($with)->select($select);

        return $this->applyBeforeExecuteQuery($data, $this->screen)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data)
    {
        $data = $this->model->create($data);

        $this->resetModel();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function createOrUpdate($data, $condition = [])
    {
        /**
         * @var Model $item
         */
        if (is_array($data)) {
            if (empty($condition)) {
                $item = new $this->model;
            } else {
                $item = $this->getFirstBy($condition);
            }
            if (empty($item)) {
                $item = new $this->model;
            }

            $item = $item->fill($data);
        } elseif ($data instanceof Model) {
            $item = $data;
        } else {
            return false;
        }

        if ($item->save()) {
            $this->resetModel();
            return $item;
        }

        $this->resetModel();

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Model $model)
    {
        return $model->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function firstOrCreate(array $data, array $with = [])
    {
        $this->applyCriteria();

        $data = $this->model->firstOrCreate($data, $with);

        $this->resetModel();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $condition, array $data)
    {
        $data = $this->model->where($condition)->update($data);

        $this->resetModel();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function select(array $select = ['*'], array $condition = [])
    {
        $data = $this->model->where($condition)->select($select);

        return $this->applyBeforeExecuteQuery($data, $this->screen);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteBy(array $condition = [])
    {
        $this->applyConditions($condition);

        $data = $this->model->get();

        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $item->delete();
        }

        $this->resetModel();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $condition = [])
    {
        $this->applyConditions($condition);
        $this->applyCriteria();
        $data = $this->model->count();

        $this->resetModel();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getByWhereIn($column, array $value = [], array $args = [])
    {
        $data = $this->model->whereIn($column, $value);

        if (!empty(Arr::get($args, 'where'))) {
            $this->applyConditions($args['where']);
        }

        if (!empty(Arr::get($args, 'paginate'))) {
            $data = $this->applyBeforeExecuteQuery($data, $this->screen)->paginate($args['paginate']);
        } elseif (!empty(Arr::get($args, 'limit'))) {
            $data = $this->applyBeforeExecuteQuery($data, $this->screen)->limit($args['limit']);
        } else {
            $data = $this->applyBeforeExecuteQuery($data, $this->screen)->get();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function resetModel()
    {
        $this->model = new $this->originalModel;
        $this->skipCriteria = false;
        $this->criteria = [];

        return $this;
    }

    /**
     * @param array $where
     * @param null|Eloquent|Builder $model
     */
    protected function applyConditions(array $where, &$model = null)
    {
        if (!$model) {
            $newModel = $this->model;
        } else {
            $newModel = $model;
        }
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($field, $condition, $val) = $value;
                switch (strtoupper($condition)) {
                    case 'IN':
                        $newModel = $newModel->whereIn($field, $val);
                        break;
                    case 'NOT_IN':
                        $newModel = $newModel->whereNotIn($field, $val);
                        break;
                    default:
                        $newModel = $newModel->where($field, $condition, $val);
                        break;
                }
            } else {
                $newModel = $newModel->where($field, '=', $value);
            }
        }
        if (!$model) {
            $this->model = $newModel;
        } else {
            $model = $newModel;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function advancedGet(array $params = [])
    {
        $this->applyCriteria();

        $params = array_merge([
            'condition' => [],
            'order_by'  => [],
            'take'      => null,
            'paginate'  => [
                'per_page'      => null,
                'current_paged' => 1,
            ],
            'select'    => ['*'],
            'with'      => [],
        ], $params);

        $this->applyConditions($params['condition']);

        $data = $this->model;

        if ($params['select']) {
            $data = $data->select($params['select']);
        }

        foreach ($params['order_by'] as $column => $direction) {
            if ($direction !== null) {
                $data = $data->orderBy($column, $direction);
            }
        }

        foreach ($params['with'] as $with) {
            $data = $data->with($with);
        }

        if ($params['take'] == 1) {
            $result = $this->applyBeforeExecuteQuery($data, $this->screen, true)->first();
        } elseif ($params['take']) {
            $result = $this->applyBeforeExecuteQuery($data, $this->screen)->take($params['take'])->get();
        } elseif ($params['paginate']['per_page']) {
            $paginate_type = 'paginate';
            if (Arr::get($params, 'paginate.type') && method_exists($data, Arr::get($params, 'paginate.type'))) {
                $paginate_type = Arr::get($params, 'paginate.type');
            }
            $result = $this->applyBeforeExecuteQuery($data, $this->screen)
                ->$paginate_type(
                    Arr::get($params, 'paginate.per_page', 10),
                    [$this->originalModel->getTable() . '.' . $this->originalModel->getKeyName()],
                    'page',
                    Arr::get($params, 'paginate.current_paged', 1)
                );
        } else {
            $result = $this->applyBeforeExecuteQuery($data, $this->screen)->get();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(array $condition = [])
    {
        $item = $this->model->where($condition)->withTrashed()->first();
        if (!empty($item)) {
            $item->forceDelete();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restoreBy(array $condition = [])
    {
        $item = $this->model->where($condition)->withTrashed()->first();
        if (!empty($item)) {
            $item->restore();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstByWithTrash(array $condition = [], array $select = [])
    {
        $query = $this->model->where($condition)->withTrashed();

        if (!empty($select)) {
            return $query->select($select)->first();
        }

        return $this->applyBeforeExecuteQuery($query, $this->screen, true)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $data)
    {
        return $this->model->insert($data);
    }

    /**
     * {@inheritdoc}
     */
    public function firstOrNew(array $condition)
    {
        $this->applyConditions($condition);

        $result = $this->model->first() ?: new $this->originalModel;

        $this->resetModel();

        return $result;
    }
}
