<?php
/**
 * Created by PhpStorm.
 * User: heeseunglee
 * Date: 2018. 7. 5.
 * Time: PM 2:24
 */

namespace Lichris\Blitz\Relations;


class BelongsToMany extends Relation
{
    protected $pivot;

    protected $pivotFKey;

    protected $pivotPKey;

    protected $table;

    protected $fKey;

    protected $pKey;

    protected $search;

    protected $pivotItems;

    public function __construct(string $blitzClass, string $pivot, string $pivotFKey, string $pivotPKey,
                                string $table, string $fKey, string $pKey, array $search)
    {
        $this->pivot = $pivot;
        $this->pivotFKey = $pivotFKey;
        $this->pivotPKey = $pivotPKey;
        $this->table = $table;
        $this->fKey = $fKey;
        $this->pKey = $pKey;
        $this->search = $search;

        // TODO : pivot 과 모델을 한번에 불러 처리하는 방법을 고안해야 합니다 (2번의 쿼리 실행 -> 1번으로 개선)
        $this->pivotItems = app('db')->table($pivot)
            ->whereIn($pivot.'.'.$pivotPKey, $search)->get();

        $this->blitz = (new $blitzClass(false))
            ->whereIn($table.'.'.$fKey, $this->pivotItems->pluck($pivotFKey)->all());
    }

    public function get($models)
    {
        $items = $this->blitz->get();
        return $models->each(
            function (&$i, $k) use ($items) {
                $i->put(
                    $this->table,
                    $items->whereIn(
                        $this->pKey,
                        $this->pivotItems
                            ->groupBy($this->pivotPKey)
                            ->get($i->get($this->pKey))
                            ->pluck($this->pivotFKey)
                    )
                );
            });
    }
}
