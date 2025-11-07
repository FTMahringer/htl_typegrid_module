<?php

namespace Drupal\htl_typegrid\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

final class FieldPickerForm extends FormBase {
  public function getFormId(): string { return 'htl_grid_field_picker'; }

  public function buildForm(array $form, FormStateInterface $form_state, string $bundle = NULL): array {
    $request = \Drupal::requestStack()->getCurrentRequest();

    $bundle   = $bundle ?? (string) $request->query->get('bundle', '');
    $selected = $request->query->all('selected');
    if (!is_array($selected)) {
      $selected = $request->query->get('selected', []);
      $selected = is_array($selected) ? $selected : [];
    }

    $form['bundle'] = ['#type' => 'value', '#value' => $bundle];

    // Optionen aufbauen
    $options = [];
    if ($bundle !== '') {
      $defs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
      foreach ($defs as $name => $def) {
        if ($def->getFieldStorageDefinition()->isBaseField()) continue;
        $label = (string) ($def->getLabel() ?? $name);
        $options[$name] = $label . " ($name)";
      }
      asort($options);
    }

    // DEFAULTS robust setzen: nur erlaubte Keys & als key=>key
    $selected = array_values(array_intersect(array_keys($options), $selected));
    $default  = $selected ? array_combine($selected, $selected) : [];

    $form['options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Felder auswählen (max. 3)'),
      '#options' => $options,
      '#default_value' => $default,
      '#required' => FALSE,
      '#description' => $this->t('Wähle bis zu drei Felder, die unter dem Titel angezeigt werden.'),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Speichern'),
      '#ajax'  => ['callback' => '::ajaxSubmit'],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Abbrechen'),
      '#limit_validation_errors' => [],
      '#submit' => ['::noopSubmit'],
      '#ajax'  => ['callback' => '::ajaxCancel'],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Auf KEYS gehen; ungecheckt = 0, gecheckt = key
    $raw    = (array) $form_state->getValue('options');
    $picked = array_keys(array_filter($raw));
    if (count($picked) > 3) {
      $form_state->setErrorByName('options', $this->t('Bitte maximal 3 Felder auswählen.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $resp   = new AjaxResponse();
    $bundle = (string) $form_state->getValue('bundle');

    // WICHTIG: KEYS nehmen; auch leere Auswahl ist ok
    $raw    = (array) $form_state->getValue('options');
    $picked = array_values(array_keys(array_filter($raw)));

    $store = \Drupal::service('tempstore.private')->get('htl_grid');
    $store->set('picker.' . $bundle, $picked);

    $resp->addCommand(new CloseModalDialogCommand());
    $referer = \Drupal::request()->headers->get('referer') ?: '/admin/structure/block';
    $resp->addCommand(new RedirectCommand($referer));
    return $resp;
  }

  public function ajaxCancel(array &$form, FormStateInterface $form_state): AjaxResponse {
    $resp = new AjaxResponse();
    $resp->addCommand(new CloseModalDialogCommand());
    return $resp;
  }

  public function noopSubmit(array &$form, FormStateInterface $form_state): void {}
}
