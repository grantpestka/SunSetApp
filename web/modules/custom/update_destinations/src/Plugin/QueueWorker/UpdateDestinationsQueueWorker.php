<?php

namespace Drupal\update_destinations\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sunburst_api\Client\SunburstClient;

/**
 * Process the JSON payload provided by SunsutWx.
 *
 * @QueueWorker(
 *   id = "update_destinations_queue_worker",
 *   title = @Translation("Update Destinations Queue Worker"),
 *   cron = {"time" = 5}
 * )
 */
class UpdateDestinationsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

    /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * TODO Cleanup
   * Drupal\sunburst_api\Client\PcoClient definition.
   *
   * @var \Drupal\sunburst_api\Client\PcoClient
   */
  protected $sunburstApi;

  /**
   * Constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_field_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager, SunburstClient $sunburst_api) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->sunburstApi = $sunburst_api;
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
      $container->get('sunburst_api.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($nid) {
    $storage = $this->entityTypeManager->getStorage('node');
    if (!$node = $storage->load($nid)) {
      \Drupal::logger('update_destinations')->error('The destination id @id could not be loaded', ['@id' => $nid]);
      return;
    }
    $latlon = $node->field_latitude_longitude->lat . ',' . $node->field_latitude_longitude->lng;
    if (!$quality = $this->sunburstApi->getQuality($latlon)) {
      \Drupal::logger('update_destinations')->error('Could not get quality for destination id @id', ['@id' => $nid]);
      return;
    }
    $this->saveQuality($node, $quality);
  }

  // /**
  //  * loops through each active destination and updates the sunset details
  //  * NOTE--Grant's a ttempt to define what should happen
  //  */
  public function saveQuality($node, $quality) {
    if (!isset($quality->features[0]->properties)) {
      return FALSE;
    }
    $props = $quality->features[0]->properties;
    $fields = [
      'field_sunset_quality_percent' => 'quality_percent',
      'field_sunset_quality_value' => 'quality_value',
      'field_sunset_valid_at' => 'valid_at',
    ];

    foreach ($fields as $drupalField => $apiField) {
      // $quality->features[0]->properties->quality_percent
      $value = $props->{$apiField};
      $node->set($drupalField, $value);
    }
    $node->setNewRevision(TRUE);
    $node->revision_log = 'Updated quality on ' . $props->last_updated . ".";
    $node->save();
  }

}