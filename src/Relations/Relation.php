<?php
/**
 * Created by PhpStorm.
 * User: heeseunglee
 * Date: 2018. 7. 5.
 * Time: PM 2:22
 */

namespace Lichris\Blitz\Relations;

use BadMethodCallException;
use Illuminate\Support\Str;
use Lichris\Blitz\Blitz;

abstract class Relation
{
    /**
     * @var Blitz
     */
    public $blitz;

    /**
     *
     * @param $name
     * @param $parameters
     *
     * @return Relation
     */
    public function __call($name, $parameters)
    {
        if (
            Str::startsWith($name, ['where', 'order', 'take', 'select', 'group', 'offset', 'union'])
            || Str::contains(Str::lower($name), ['join'])
        ) {
            $this->blitz = call_user_func_array([$this->blitz, $name], $parameters);

            return $this;
        }

        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.', static::class, $name
        ));
    }
}
