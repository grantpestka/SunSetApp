<?php

namespace Drupal\notify_destination_followers\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\notification\Utility\NotificationUtility;

/**
 * Send a SMS to each user for every destination they are subscribed to.
 *
 * @QueueWorker(
 *   id = "send_sms_quality_queue_worker",
 *   title = @Translation("Send SMS Quality Queue Worker"),
 *   cron = {"time" = 15}
 * )
 */
class SendSmsQualityQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\notify\Utility\NotificationUtility definition.
   *
   * @var \Drupal\notify\Utility\NotificationUtility
   */
  protected $notificationUtility;  

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
   public function processItem($flagging) {
    $uid = $flagging->getOwnerId();
    $destinationId = $flagging->entity_id->value;
    //TODO: log  (drupal logging) message if destination ID does not load
    $message = $this->createMessage($destinationId);

   $this->smsUtility->sendSms($uid , $message, 'node', $logParent);
    // die();
  }
  
  /**
   *  Create a message
   *
   * @param $data
   */
  public function createMessage($destinationId) {
    $storage = $this->entityTypeManager->getStorage('node');
    if (!$destination = $storage->load($destinationId)) {
      return FALSE;
    }
    $replacements = [
        '@destination_name' => $destination->getTitle(),
      ];
    $message = "The sunset at @destination_name is going to be great tonight! Don't miss it!";
    $message = $this->t($message, $replacements);
    return $message;
  }
}