<?php

namespace Drupal\sunset_app\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class FrontController.
 */
class FrontController extends ControllerBase {

  /**
   * Index.
   *
   * @return string
   *   Return Hello string.
   */
  public function index() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: index')
    ];
  }

}
