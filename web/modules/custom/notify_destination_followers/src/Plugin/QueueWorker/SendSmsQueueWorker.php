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
 *   id = "send_sms_queue_worker",
 *   title = @Translation("Send SMS Queue Worker"),
 *   cron = {"time" = 15}
 * )
 */
class SendSmsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
  //@Joel  would better paramaters for this be $uid, $destination_id, $flagging_id
   public function processItem($user_destination_flagging) {
    $message = createMessage($user_destination_flagging);
    //need to load user??
    $user_phone = $user_destination_flagging->'uid'->field_mobile_phone;


   $this->$notificationUtility->sendSms($user_phone, $uid, $message, $log_parent);
    // die();
  }
  
  /**
   *  Create a message
   *
   * @param $data
   */
  public function createMessage($user_destination_pair) {
    $replacements = [
        //load destination ?? 
        '@destination_name' => $user_destination_pair->"destination_id"->name;
      ];
    $message = "The sunset at @destination_name is going to be great tonight! Don't miss it!";
    $message = $this->t($message,$replacements)
    return $message;
  }
}