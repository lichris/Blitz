<?php
/**
 * Created by PhpStorm.
 * User: heeseunglee
 * Date: 2018. 7. 5.
 * Time: PM 2:23
 */

namespace Lichris\Blitz\Relations;


class HasMany extends Relation
{
    protected $table;

    protected $fKey;

    protected $pKey;

    protected $search;

    /**
     * HasMany constructor.
     * @param $blitzClass
     * @param $table
     * @param $fKey
     * @param $pKey
     * @param $search
     */
    public function __construct($blitzClass, $table, $fKey, $pKey, $search)
    {
        $this->table = $table;
        $this->fKey = $fKey;
        $this->pKey = $pKey;
        $this->search = $search;

        // whereIn 으로 eager loading 하여 n+1 문제 해결
        $this->blitz = (new $blitzClass(false))->whereIn($table . '.' . $fKey, $search);
    }


    public function get($models)
    {
        $items = $this->blitz->get();

        return $models->each(function ($i, $k) use ($items) {
            $i->put(
                $this->table,
                $items->where($this->fKey, $i->get($this->pKey))
            );
        });
    }
}
