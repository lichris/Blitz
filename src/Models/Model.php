<?php
/**
 * Created by PhpStorm.
 * User: heeseunglee
 * Date: 2018. 7. 5.
 * Time: PM 2:31
 */

namespace Lichris\Blitz\Models;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonSerializable;

abstract class Model extends Collection
{
    /**
     * @var array
     */
    protected $filter;

    /**
     * @var array
     */
    protected $hidden;

    /**
     * @var array
     */
    protected $relations;

    /**
     * 베이스 모델을 생성합니다
     * 모델은 자체적으로 $filter 배열을 가지고 있으며
     * $filter 에 명시되지 않은 key 값들을 제거하며
     * 이를 통해 Repository 가 Create 혹은 Update 시
     * 자체적인 검증을 하지 않도록 합니다.
     *
     * @param array $items
     */
    public function __construct(array $items)
    {
        parent::__construct($this->applyFilter($items));
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array_map(function ($value) {
            return $value instanceof Arrayable ? $value->toArray() : $value;
        }, $this->applyHidden());
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {
                return $value->toArray();
            }

            return $value;
        }, $this->applyHidden());
    }

    /**
     * @return array
     */
    public function toDBArray()
    {
        if (! is_array($this->relations)) {
            $this->relations = [];
        }

        return Arr::except($this->dumpArray(), array_merge(['id'], $this->relations));
    }

    /**
     * @return array
     */
    public function dumpArray()
    {
        return parent::toArray();
    }

    /**
     * @return string
     */
    public function dumpJson()
    {
        return json_encode($this->dumpArray());
    }

    /**
     * $hidden 배열에 명시된 key 값을 제거합니다.
     *
     * @return array
     */
    protected function applyHidden()
    {
        return Arr::except($this->items, $this->hidden);
    }

    /**
     * $filter 배열에 명시된 key 값만 반환합니다.
     *
     * @param array|null $items
     *
     * @return array
     */
    protected function applyFilter(array $items = null)
    {
        if (empty($this->filter)) {
            return $items;
        }

        $items = ! is_null($items) ? $items : $this->items;

        return Arr::only($items, array_merge($this->filter, $this->relations));
    }

    /**
     * @return mixed
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @return mixed
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * @return mixed
     */
    public function getRelations()
    {
        return $this->relations;
    }
}
