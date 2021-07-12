# doctrine-attribute-codemod

This is a "worked for me" code mod to go from doctrine annotations to attributes for configuring the ORM.

## Usage:

`vendor/bin/codeshift --mod=mod.php --src=/SOMEWHERE/src/Entity`

## before:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="alert")
 * @ORM\Entity(repositoryClass=AlertRepository::class)
 */
class Alert implements AlertInterface
{
    /**
     * @ORM\Column(name="alert_id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\Column(name="table_row_id", type="integer")
     */
    protected $tableRowId;
```

## after:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'alert')]
#[ORM\Entity(repositoryClass: AlertRepository::class)]
class Alert implements AlertInterface
{

    #[ORM\Column(name: 'alert_id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected $id;

    #[ORM\Column(name: 'table_row_id', type: 'integer')]
    protected $tableRowId;
```


Thanks especailly to [PHP Codeshift](https://github.com/Atanamo/PHP-Codeshift) for making this easy to run and to [PHP-Parser](https://github.com/nikic/PHP-Parser).