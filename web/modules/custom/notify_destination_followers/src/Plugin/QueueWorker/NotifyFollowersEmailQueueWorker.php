<?php

namespace Drupal\notify\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\notify\Utility\NotifyUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notify via email.
 *
 * @QueueWorker(
 *   id = "notify_email_queue_worker",
 *   title = @Translation("Email Notification Queue Worker"),
 *   cron = {"time" = 15}
 * )
 */
class NotifyEmailQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\notify\Utility\NotifyUtility definition.
   *
   * @var \Drupal\notify\Utility\NotifyUtility
   */
  protected $notifyUtility;

  protected $debug = FALSE;

  /**
   * Constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_field_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager, NotifyUtility $notify_utility) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->notifyUtility = $notify_utility;
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
      $container->get('entity_type.manager'),
      $container->get('notify.utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    // Double check notification has not been sent already.
    if ($this->notifyUtility->hasNotifications($data->bookingId, $data->type)) {
      \Drupal::logger('notify')->notice('A @type notification has already been sent for @booking.', ['@type' => $data->type, '@booking' => $data->bookingId]);
      return FALSE;
    }

    if (!$booking = $this->notifyUtility->loadBooking($data->bookingId)) {
      \Drupal::logger('notify')->notice('Booking: @booking_id could not be loaded!', ['@booking_id' => $data->bookingId]);
      return FALSE;
    }

    $this->notifyUtility->sendEmail($booking);