<?php

namespace Drupal\notify_destination_followers\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get Users that need notifications for each subscribed destination.
 *
 * @QueueWorker(
 *   id = "get_users_to_notify_queue_worker",
 *   title = @Translation("Get Users to Notify Queue Worker"),
 *   cron = {"time" = 15}
 * )
 */
class GetUsersToNotifyQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  protected $debug = FALSE;

  /**
   * Constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_field_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implementation of the container interface to allow dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      empty($configuration) ? [] : $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($destinationId) {
    if (!$flaggings = $this->getDestinationFlags($destinationId)) {
      return;
    }
    $queue_id = 'send_sms_queue_worker';
    $queue = \Drupal::queue($queue_id);
    $data = [];
    foreach ($flaggings as $flagging) {
      $data['uid'] = $flagging->uid->value;
      $data['destination_id'] = $flagging->entity_id->value;
      // TODO: Check the log before adding to queue.
      $queue->createItem($data);
    }
  }

  /**
   * Get a list of flags for a destination.
   * 
   * @param $destinationId
   *   A destination node id.
   * 
   * @return array
   *   Array of flag objects.
   */
  public function getDestinationFlags($destinationId) {
    $storage = $this->entityTypeManager->getStorage('flagging');
    $flaggings = $storage->getQuery()
      ->condition('flag_id', 'subscribe')
      ->condition('field_send_sms', 1)
      ->condition('entity_id', $destinationId)
      ->execute();
  
    if (empty($flaggings)) {
      return FALSE;
    }

    return $storage->loadMultiple($flaggings);
  }
}