Core systems in WebAnt

Installation

### Step 1: Install WebAntCoreBundle

The preferred way to install this bundle is to rely on [Composer](http://getcomposer.org).
Just add in your  composer.json:

``` js
{
    "require": {
        // ...
        "web-ant/webant-corebundle": "dev-master"
    }
}
```

### Step 2: Enable the bundle

Finally, enable the bundle in the kernel:

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new WebAnt\CoreBundle\CoreBundle(),
    );
}
```

### Step 3: Our classes inherit from AbstractController

Now you must to connect AbstractController and inherit the classes from it, example:

``` php
<?php
// RootDit/src/DemoBundle/Controller/DemoController.php;

namespace /src/DemoBundle/Controller;

use WebAnt\CoreBundle\Controller\AbstractController;

class DemoController extends AbstractController
{
    ...
}
```




