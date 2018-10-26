<?php

namespace Drupal\sunburst_api\Form;

use Drupal\Core\Form\FormStateInterface;

class ConfigForm extends ConfigFormBase {

  public function getFormId() {
    return 'sunburst_api_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();

    $form['username'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Sunburst Username'),
      '#default_value' => $config->get('username'),
      '#description' => $this->t('Your Sunburst username stored in a Key.'),
    ];

    $form['password'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Sunburst Password'),
      '#default_value' => $config->get('password'),
      '#description' => $this->t('Your Sunburst password stored in a Key.'),
    ];

    $form['base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sunburst API Base URL'),
      '#default_value' => $config->get('base_uri'),
      '#description' => $this->t('Exclude trailing slash.'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
