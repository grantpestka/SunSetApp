<?php

namespace Drupal\sunburst_api\Form;

use Drupal\Core\Form\FormStateInterface;

class ConfigForm extends ConfigFormBase {

  public function getFormId() {
    return 'sunburst_api_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();

    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sunburst API Token'),
      '#default_value' => $config->get('token'),
      '#description' => $this->t('Your Sunburst API token.'),
    ];

    $form['base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sunburst API Base URL'),
      '#default_value' => $config->get('base_uri'),
      '#description' => $this->t('Include trailing slash.'),
    ];

    $form['secret'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Sunburst API Secret'),
      '#default_value' => $config->get('secret'),
      '#description' => $this->t('Your Sunburst API secret.')
    ];

    return parent::buildForm($form, $form_state);
  }

}
