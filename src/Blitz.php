<?php

namespace Lichris\Blitz;

use BadMethodCallException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lichris\Blitz\Models\Model;
use Lichris\Blitz\Relations\BelongsTo;
use Lichris\Blitz\Relations\BelongsToMany;
use Lichris\Blitz\Relations\HasMany;
use Lichris\Blitz\Relations\HasOne;

class Blitz
{
    /**
     * 레포지토리에 매칭되는 모델입니다.
     *
     * @var string
     */
    protected $modelClass;

    /**
     * DB 테이블 이름.
     *
     * @var string
     */
    protected $table;

    /**
     * 로그에 작성되는 DB 테이블의 한글 이름.
     *
     * @var string
     */
    protected $name;

    /**
     * DB 테이블의 단수형.
     *
     * @var string
     */
    protected $single;

    /**
     * Laravel 의 QueryBuilder 입니다.
     *
     * @var Builder
     */
    protected $query;

    /**
     * Redis 캐시 태그.
     *
     * @var array
     */
    protected $cacheTags;

    /**
     * DB 에서 받아온 모델.
     *
     * @var Model|Collection
     */
    protected $models;

    /**
     * 관계 모델 eager loading 여부
     *
     * @var bool
     */
    protected $isRelations;

    /**
     * eager load 할 관계 모델.
     *
     * @var array
     */
    protected $relations;

    /**
     * 관계 모델을 불러올 최종 쿼리 배열.
     *
     * @var array
     */
    protected $relationQueries;

    /**
     * eager load 된 모델이 있는지 여부
     *
     * @var bool
     */
    protected $isRelationLoaded = false;

    /**
     * Laravel Eloquent 의 soft delete 를 구현하였으며 deleted_at != null 일 경우 모델을 불러오지 않습니다.
     *
     * @var bool
     */
    protected $softDelete;

    /**
     * Repository constructor.
     *
     * @param bool $isRelations
     */
    public function __construct(bool $isRelations = true)
    {
        $this->isRelations = $isRelations;
    }

    /**
     * 모델을 인자로 받아서 DB 레코드를 추가합니다.
     *
     * @param Model $model
     *
     * @throws \Exception
     *
     * @return array
     */
    public function create(Model $model)
    {
        // fire creating event
        $this->fireBeforeCreateEvent($model);

        app('log')->info('');
        app('log')->info("========== [ {$this->name} ] 생성");
        app('log')->info(
            "\n"
            .__METHOD__."\n"
            ."생성 예정 데이터 :\n"
            .$model->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n"
        );
        app('log')->info('');

        app('db')->beginTransaction();

        try {
            $id = app('db')->table($this->table)->insertGetId($model->toDBArray());
            app('db')->commit();

            app('log')->info('');
            app('log')->info("[ {$this->name} ] 생성 완료 ==========");
            app('log')->info(
                "\n"
                ."[ {$this->name} ] 생성 완료 데이터 : id({$id})\n"
                ."\n"
            );
            app('log')->info('');

            // fire created event
            $this->fireCreatedEvent($id, $model);

            return [
                'is_success' => true,
                'code' => 201,
                'msg' => 'success',
                'data' => [
                    'id' => $id,
                    'uuid' => $model->get('uuid'),
                ],
            ];
        } catch (\Exception $e) {
            app('log')->error('');
            app('log')->error(
                "[ {$this->name} ] 생성 실패 ==========\n\n"
            );
            app('log')->error(
                $e->getMessage()."\n"
                .$e->getTraceAsString()."\n\n"
            );
            app('log')->error('');
            app('db')->rollBack();

            return [
                'is_success' => false,
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * id 에 맞는 DB 칼럼을 업데이트 합니다.
     *
     * @param int   $id
     * @param array $data
     *
     * @throws \Exception
     *
     * @return array
     */
    public function update(int $id, array $data)
    {
        $this->fireBeforeUpdateEvent($id, $data);

        app('log')->info('');
        app('log')->info("========== [ {$this->name} ] 수정");
        app('log')->info(
            "\n"
            .__METHOD__."\n"
            ."수정 예정 데이터 : id({$id})\n"
            .json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n"
        );
        app('log')->info('');

        app('db')->beginTransaction();

        try {
            app('db')->table($this->table)->where('id', $id)->update($data);
            app('db')->commit();

            app('log')->info('');
            app('log')->info("[ {$this->name} ] 수정 완료 ==========");
            app('log')->info('');

            $this->fireUpdatedEvent($id, $data);

            return [
                'is_success' => true,
                'code' => 200,
                'msg' => 'success',
                'data' => [
                    'id' => $id,
                ],
            ];
        } catch (\Exception $e) {
            app('log')->error('');
            app('log')->error(
                "[ {$this->name} ] 수정 실패 ==========\n\n"
            );
            app('log')->error(
                $e->getMessage()."\n"
                .$e->getTraceAsString()."\n\n"
            );
            app('log')->error('');
            app('db')->rollBack();

            return [
                'is_success' => false,
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * id 에 맞는 DB 칼럼의 int field 를 increment 합니다.
     *
     * @param int    $id
     * @param string $field
     *
     * @throws \Exception
     *
     * @return array
     */
    public function increment(int $id, string $field)
    {
        app('log')->info('');
        app('log')->info("========== [ {$this->name} ] 증가");
        app('log')->info(
            "\n"
            .__METHOD__."\n"
            ."증가 예정 데이터 : id({$id}) field({$field})\n"
            ."\n"
        );
        app('log')->info('');

        app('db')->beginTransaction();

        try {
            app('db')->table($this->table)->where('id', $id)->increment($field);
            app('db')->commit();

            app('log')->info('');
            app('log')->info("[ {$this->name} ] 증가 완료 ==========");
            app('log')->info('');

            $this->fireIncrementedEvent($id, $field);

            return [
                'is_success' => true,
                'code' => 200,
                'msg' => 'success',
                'data' => [
                    'id' => $id,
                    'field' => $field,
                ],
            ];
        } catch (\Exception $e) {
            app('log')->error('');
            app('log')->error(
                "[ {$this->name} ] 증가 실패 ==========\n\n"
            );
            app('log')->error(
                $e->getMessage()."\n"
                .$e->getTraceAsString()."\n\n"
            );
            app('log')->error('');
            app('db')->rollBack();

            return [
                'is_success' => false,
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * eager loading 하고 싶은 관계 모델들 지정.
     *
     * @param array $relations
     *
     * @return Blitz
     */
    public function with(array $relations)
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * 현재까지 저장된 query 를 리턴합니다.
     *
     * @return Builder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * 저장된 $models 를 리턴합니다.
     *
     * @return Model|Collection
     */
    public function getModels()
    {
        return $this->models;
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        return $this->query->toSql();
    }

    /**
     * QueryBuilder 인 $query 를 실행하여 DB 레코드를 Collection 형태로 받아온 후
     * 관계 모델들을 eager loading 하고 $models 에 저장한 후 Model, Collection 인
     * $model 을 반환합니다.
     *
     * @param array $columns
     *
     * @return Model|Collection
     */
    protected function get($columns = ['*'])
    {
        $this->models = $this->toModel(
            $this->query->get($columns)
        );

        if ($this->isRelations) {
            $this->loadRelations();
        }

        return $this->models;
    }

    /**
     * get() 과는 달리 find 는 단일 Model 을 리턴합니다.
     *
     * @param int   $id
     * @param array $columns
     *
     * @return Model
     */
    protected function find($id, $columns = ['*'])
    {
        $this->models = $this->toModel(
            $this->query->find($id, $columns)
        );

        if ($this->isRelations) {
            $this->loadRelations();
        }

        return $this->models;
    }

    /**
     * get() 과는 달리 first 는 $model 의 첫번째 원소를 Model 로 리턴합니다.
     *
     * @param array $columns
     *
     * @return Model|Collection
     */
    protected function first($columns = ['*'])
    {
        $this->models = $this->toModel(
            $this->query->first($columns)
        );

        if ($this->isRelations) {
            $this->loadRelations();
        }

        return $this->models;
    }

    // TODO : 패지네이트 구현 필요

    protected function paginate()
    {
    }

    /**
     * Laravel Eloquent 와 같이 DB 레코드를 삽입하기 전에 실행되는 이벤트입니다.
     *
     * @param Model $model
     */
    protected function fireBeforeCreateEvent(Model $model): void
    {
    }

    /**
     * Laravel Eloquent 와 같이 DB 레코드를 삽입한 후에 실행되는 이벤트입니다
     * 공통적으로 캐시를 enable 한 경우라면 이쪽에서 caching 해주는 것이 좋습니다.
     *
     * @param int   $id
     * @param Model $model
     */
    protected function fireCreatedEvent(int $id, Model $model): void
    {
    }

    /**
     * Laravel Eloquent 와 같이 DB 레코드를 수정하기 전에 실행되는 이벤트입니다.
     *
     * @param int   $id
     * @param array $data
     */
    protected function fireBeforeUpdateEvent(int $id, array $data): void
    {
    }

    /**
     * Laravel Eloquent 와 같이 DB 레코드를 수정한 후에 실행되는 이벤트입니다
     * enable 된 캐시가 있다면 이곳에서 만료된 cache 를 처리해주는 것이 좋습니다.
     *
     * @param int   $id
     * @param array $data
     */
    protected function fireUpdatedEvent(int $id, array $data): void
    {
    }

    /**
     * Laravel Eloquent 와 같이 DB 레코드를 수정한 후에 실행되는 이벤트입니다만
     * 필요에 의해서 구현한 method 이므로 사용하지 않으셔도 됩니다.
     *
     * @param int    $id
     * @param string $field
     */
    protected function fireIncrementedEvent(int $id, string $field): void
    {
    }

    /**
     * $query 가 null 이라면 새로운 QueryBuilder 객체를 생성하고
     * 만약 이미 존재한다면 기존의 QueryBuilder 에 계속 query 를 append 합니다.
     *
     * @return Builder
     */
    protected function newQuery()
    {
        if (! $this->query) {
            $query = app('db')->table($this->table)->select($this->table.'.*');

            if ($this->softDelete) {
                $query = $query->where('deleted_at', null);
            }

            return $query;
        }

        return $this->query;
    }

    /**
     * DB 에서 레코드를 반환받았다면 Repository 객체 재사용을 위하여 기존의 $query 를 비워줍니다.
     * 하지만 $models 에는 새로운 $query 가 실행되기 전까지는 기존 모델들이 저장되어 있습니다.
     */
    protected function clearQuery(): void
    {
        unset($this->query);
    }

    /**
     * DB 에서 받아오는 단순 Collection 혹은 \stdClass 객체를
     * Collection 혹은 Model 로 변환합니다.
     *
     * @param Collection|\stdClass $items
     *
     * @return Collection|Model
     */
    protected function toModel($items)
    {
        if ($items instanceof \stdClass) {
            $this->models = new $this->modelClass((array) $items);
        } elseif ($items instanceof Collection) {
            $result = [];
            foreach ($items as $item) {
                $result[] = new $this->modelClass((array) $item);
            }
            $this->models = collect($result);
//            dd($result, $this->models);
        }

        return $this->models;
    }

    /**
     * $query 가 get(), first(), find(), paginate() 에 의해서 실행되는 경우
     * 관계 모델을 자동적으로 삽입해줍니다.
     */
    protected function loadRelations(): void
    {
        foreach ($this->relations as $relation) {
            $this->models = call_user_func_array(
                [
                    $this,
                    Str::camel($relation),
                ],
                func_get_args()
            )->get($this->models);
        }

        $this->isRelationLoaded = true;
        $this->clearQuery();
    }

    /**
     * hasOne 관계 모델 로딩 및 $models 에 삽입.
     *
     * @param string $repo
     * @param string $table
     * @param string $fKey
     * @param string $pKey
     *
     * @return HasOne
     */
    protected function hasOne(string $repo, string $table, string $fKey, string $pKey = 'id')
    {
        return new HasOne($repo, $table, $fKey, $pKey, $this->models->pluck($pKey)->all());
    }

    /**
     * belongsTo 관계 모델 로딩 및 $models 에 삽입.
     *
     * @param string $repo
     * @param string $table
     * @param string $fKey
     * @param string $pKey
     *
     * @return BelongsTo
     */
    protected function belongsTo(string $repo, string $table, string $fKey, string $pKey = 'id')
    {
        return new BelongsTo($repo, $table, $fKey, $pKey, $this->models->pluck($fKey)->all());
    }

    /**
     * hasMany 관계 모델 로딩 및 $models 에 삽입.
     *
     * @param string $repo
     * @param string $table
     * @param string $fKey
     * @param string $pKey
     *
     * @return HasMany
     */
    protected function hasMany(string $repo, string $table, string $fKey, string $pKey = 'id')
    {
        return new HasMany($repo, $table, $fKey, $pKey, $this->models->pluck($pKey)->all());
    }

    /**
     * belongsToMany 관계 모델 로딩 및 $models 에 삽입.
     *
     * @param string $repo
     * @param string $pivot
     * @param string $pivotFKey
     * @param string $pivotPKey
     * @param string $table
     * @param string $fKey
     * @param string $pKey
     *
     * @return BelongsToMany
     */
    protected function belongsToMany(string $repo, string $pivot, string $pivotFKey, string $pivotPKey,
                                     string $table, string $fKey = 'id', string $pKey = 'id')
    {
        return new BelongsToMany($repo, $pivot, $pivotFKey, $pivotPKey,
            $table, $fKey, $pKey, $this->models->pluck($pKey)->all());
    }

    /**
     * 각종 QueryBuilder 에 속하는 함수들의 행동 양식을 새롭게 규정하였습니다
     * Repository 객체는 마치 Eloquent 처럼 사용하실 수 있도록 만들었습니다.
     *
     * @param $name
     * @param $parameters
     *
     * @return Blitz|mixed
     */
    public function __call($name, $parameters)
    {
        // where, take, limit 과 같은 QueryBuilder 에 append 되는 함수들을 처리하여
        // $query 에 저장
        if (method_exists(Builder::class, $name)
            && is_callable([Builder::class, $name])) {
            $result = call_user_func_array([$this->newQuery(), $name], $parameters);

            if (is_null($result)) {
                return null;
            } elseif ($result instanceof Builder) {
                $this->isRelationLoaded = false;
                $this->query = $result;

                return $this;
            }
        }

        if ('find' === $name) {
            if (! $this->query) {
                $this->query = $this->newQuery();
            }

            return call_user_func_array([$this, $name], $parameters);
        }

        // QueryBuilder 객체인 $query 를 실행하여 DB 레코드를 받아오는 로직
        if ('get' === $name || 'first' === $name || 'paginate' === $name) {
            if (! $this->query) {
                return null;
            }

            return call_user_func_array([$this, $name], $parameters);
        }

        // QueryBuilder 와 Repository 에서 정의하지 않은 모든 함수 처리
        throw new BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $name)
        );
    }
}
