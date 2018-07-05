# Blitz

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]

This is where your description should go. Take a look at [contributing.md](contributing.md) to see a to do list.

## Installation

Via Composer

``` bash
$ composer require lichris/blitz
```

## Usage

### 모델 (DB 에서 받아온 레코드를 관리)
```php
<?php

namespace Api\V1\Models;

use Lichris\Blitz\Models\Model;

class User extends Model
{
    // 모델 __construct(dbRecord) 시 존재하지 않는
    // 열 (column) 을 자동적으로 제외합니다
    // 예) new User(['asdf' => 1, 'id' => 1]) -> User(['id' => 1])
    protected $filter = [
        'id',
        'uuid',
        'email',
        'password',
        'name',
        'nickname',
        'phone_number',
    ];
    
    // toArray() __toString() toJson() 시 출력되지 않아야할 필드들
    protected $hidden = [
        'id',
        'email',
        'password',
    ];

    // 관계 모델
    // __construct() 시 $filter 와 array_merge 되어 삽입
    protected $relations = [
        'alert_disable',
    ];
}
```

### 블리츠 (DB 와 직접 통신)
```php
<?php 

namespace Api\V1\Blitz;

use Api\V1\Models\User;
use Lichris\Blitz\Blitz;

/**
 * Class UserRepository.
 */
class UserBlitz extends Blitz
{
    protected $modelClass = User::class;

    protected $table = 'users';

    protected $name = '사용자';

    protected $single = 'user';

    protected $softDelete = true;

    protected $relations = [
        'alert_disable',
    ];

    public function alertDisable()
    {
        // Query Builder 가 지원하는 함수 체이닝 해서 사용 가능합니다
        return $this->hasOne(AlertDisableRepository::class, 'alert_disables', 'user_id')
            ->orderBy('id', 'desc');
    }
}
```

### 컨트롤러 (실제 사용 예)
```php
<?php

namespace Api\V1\Controllers;

use Api\V1\Blitz\UserBlitz;

class UserController extends BaseController
{
    public function test()
    {
        $uBlitz = new UserBlitz();

        return view('test', [
            'dump' => [
                $uBlitz->whereIn('id', [1, 2, 3, 4])->first(),
                $uBlitz->whereIn('id', [1, 2, 3, 4])->get(),
                $uBlitz->find(1),
                $uBlitz->find(1)->with(null),
            ],
        ]);
    }
}
```

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

Testing is not yet supported (in progress)

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email 1541.hsl@gmail.com instead of using the issue tracker.

## Credits

- [Heeseung Lee][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/lichris/blitz.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/lichris/blitz.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/lichris/blitz/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/lichris/blitz
[link-downloads]: https://packagist.org/packages/lichris/blitz
[link-travis]: https://travis-ci.org/lichris/blitz
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/lichris
[link-contributors]: ../../contributors]