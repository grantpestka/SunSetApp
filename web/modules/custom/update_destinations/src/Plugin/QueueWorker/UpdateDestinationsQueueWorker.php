<?php

namespace Drupal\update_destinations;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sunbursta_api\Client\SunburstClient;

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
  protected $sunburstApiClient;

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
   * loops through each active destination and updates the sunset details
   * NOTE--Grant's a ttempt to define what should happen
   */
  public function updateDestinations (){
    $destinations = findDestinations();
    foreach ($destinations as $destinations){
      $quality = getQuality($this->'field_latitude_longitude');
      updateDestination($this,$quality)
    }
  }



  // /**
  //  * {@inheritdoc}
  //  */
  // public function processItem($data) {
  //   // Make sure data exists.
  //   if (empty($data)) {
  //     \Drupal::logger('sunburst')->notice('Sunburst API passed no sunset data.');
  //     return FALSE;
  //   }

  //   $destinationData = Json::decode($data);
   
  //   // Make sure booking data exists.
  //   if (!isset($destinationData['booking'])) {
  //     \Drupal::logger('sunburst')->notice('Sunburst API passed no sunset data.');
  //     return FALSE;
  //   }

  //   $destinationNode = $this->findBooking($destinationData);
  //   // Update it.
  //   if ($destinationNode) {
  //     $this->updateBooking($destinationNode, $destinationData);
  //     return TRUE;
  //   }
  // }

  /**
   * Query for exisitng destinations node.
   * should grab all active destinations
   */
  public function findDestinations() {
    $storage = $this->entityTypeManager->getStorage('node');
    //NEEDS equilivant
    $bookingId = $data['booking']['@attributes']['booking_id'];
    // Grab existing destinationNode.
    $destinationNode = $storage->loadByProperties([
      'type' => 'booking',
      'field_destination_update_active' => 1,
    ]);

    if ($destinationNode) {
      return array_pop($destinationNode);
    }
    return FALSE;
  }

  /**
   * Query for exisitng booking node.
   * @JOEL--I think this is mostly right, ish
   */
  public function updateDestination($destinationNode, $data) {
    // TODO: Create a revision.
    $doNotUpdate = [
      'type',
      'field_destination_update_active',
    ];
    $values = $this->prepareDestinationNode($data);

    // Iterate over values and update the node.
    // ?? only active ??
    foreach ($values as $key => $value) {
      if ($key['field_destination_update_active'] === 1){
        $destinationNode->set($key, $value);
      }
    }
    $destinationNode->setNewRevision(TRUE);
    $destinationNode->revision_log = 'Sunburst API Update.';
    $destinationNode->save();
  }

  /**
   * Prepare destination node values.
   * NEEDS proper data refernces
   */
  public function prepareDestinationNode($quality) {
    // TODO: \Drupal::service('date.formatter')
    $values = [
      'type' => 'destination',
      'status' => 1,
      //TODO--not needed??
      'title' => $data['booking']['code'],
      //TODO
      'field_sunset_last_updated' => [
        'value' => format_date($data['booking']['start_date'], 'custom', 'Y-m-d'),
        'end_value' => format_date($data['booking']['end_date'], 'custom', 'Y-m-d'),
      ],
      //TODO
      'field_sunset_last_updated' =>  ,
      //TODO
      'field_sunset_quality_percent' => ,
      //TODO
      'field_sunset_quality_value' => ,
      //TODO
      'field_sunset_valid_at' => ,
    ];

    return $values;
  }


    


}

    


