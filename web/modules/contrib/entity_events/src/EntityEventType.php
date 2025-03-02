<?php

namespace Drupal\entity_events;

/**
 * Enumeration of entity event types.
 */
class EntityEventType {
  const INSERT = 'event.insert';
  const UPDATE = 'event.update';
  const PRESAVE = 'event.presave';
  const DELETE = 'event.delete';

}
