# Dandomain Stock Bundle
Symfony bundle to handle stock i Dandomain, especially stock movements

## Installation

### Step 1: Install dependencies

This bundle depends on the [Dandomain Foundation Bundle](https://github.com/loevgaard/dandomain-foundation-bundle) and the [Doctrine2 Behaviors Bundle by KNP Labs](https://github.com/KnpLabs/DoctrineBehaviors).

Install those bundles first, and then return to this page.

### Step 2: Download the bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require loevgaard/dandomain-stock-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 3: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Loevgaard\DandomainStockBundle\LoevgaardDandomainStockBundle(),
        );

        // ...
    }

    // ...
}
```

### Step 4: Create Doctrine ORM Entities

```php
<?php
// src/AppBundle/Entity/StockMovement.php

namespace AppBundle\Entity;

use Loevgaard\DandomainStockBundle\Entity\StockMovement as BaseStockMovement;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="stock_movements", indexes={@ORM\Index(columns={"type"})})
 * @ORM\HasLifecycleCallbacks()
 */
class StockMovement extends BaseStockMovement
{        
    public function __construct()
    {
        parent::__construct();
        // your own logic
    }
}
```

### Step 5: Configure the bundle
```yaml
# app/config/config.yml
loevgaard_dandomain_stock:
    stock_movement_class: AppBundle\Entity\StockMovement
    dandomain_order_state_ids: [3]
```

### Step 6: Update your database schema
```bash
$ php bin/console doctrine:schema:update --force
```