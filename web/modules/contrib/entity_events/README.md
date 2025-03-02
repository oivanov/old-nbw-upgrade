CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Entity Events module provides abstract classes for event listeners for you
to extend in your own modules to 'do stuff' on entity operations. Can use as a
replacement for hook_entity_[update/insert/delete/presave] from within your
module file.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/entity_events

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/entity_events


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Entity Events module as you would normally install a contributed
   Drupal module. Visit https://www.drupal.org/node/1897420 for further
   information.


CONFIGURATION
-------------

Abstract classes:

```
EntityEventInsertSubscriber
EntityEventUpdateSubscriber
EntityEventDeleteSubscriber
EntityEventPresaveSubscriber
```
Extend these classes with your own module subscribers.

Example implementation:

```
namespace Drupal\mymodule\EventSubscriber;

use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EventSubscriber\EntityEventInsertSubscriber;

class MyModuleSubscriber extends EntityEventInsertSubscriber {

  public function onEntityInsert(EntityEvent $event) {
    drupal_set_message('Entity inserted');
  }

}
```

Remember to add event listeners to your services.yml.file:

```
services:
  mymodule.subscriber:
    class: Drupal\mymodule\EventSubscriber\MyModuleSubscriber
    tags:
      - {name: event_subscriber}
```


MAINTAINERS
-----------

 * Matt Gill (mattgill) - https://www.drupal.org/u/mattgill

Supporting organizations:

 * PA Consulting - https://www.drupal.org/pa-consulting
 * Care Quality Commission - https://www.drupal.org/care-quality-commission
