<?php
namespace Drupal\views_complex_grouping\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin for the complex grouping.
 *
 * @ViewsStyle(
 *   id = "complex_grouping",
 *   title = @Translation("Complex Grouping"),
 *   help = @Translation("Limit the number of rows under each grouping field"),
 *   theme = "views_complex_grouping_level",
 *   display_types = {"normal"}
 * )
 */
class ViewsComplexGrouping extends StylePluginBase
{
  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions()
  {
    $options = parent::defineOptions();
    $options['complex_grouping'] = array('default' => '');
    return $options;
  }

  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state)
  {
    parent::buildOptionsForm($form, $form_state);

    $field_labels = $this->displayHandler->getFieldLabels(TRUE);

    foreach ($form['grouping'] as $index => $info) {
      $grouping_fields_default = (isset($this->options['grouping'][$index]['complex_grouping']['grouping-fields'])) ? $this->options['grouping'][$index]['complex_grouping']['grouping-fields'] : NULL;
      $grouping_limit_default = (isset($this->options['grouping'][$index]['complex_grouping']['grouping-limit'])) ? $this->options['grouping'][$index]['complex_grouping']['grouping-limit'] : 1;
      $grouping_offset_default = (isset($this->options['grouping'][$index]['complex_grouping']['grouping-offset'])) ? $this->options['grouping'][$index]['complex_grouping']['grouping-offset'] : 1;

      $form['grouping'][$index]['complex_grouping'] = [
          '#type' => 'fieldset',
          '#title' => t('Limit and extra fields for grouping field Nr.@num', ['@num' => $index + 1]),
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
          'grouping-fields' => [
              '#type' => 'select',
              '#multiple' => TRUE,
              '#title' => t('Selected'),
              '#options' => $field_labels,
              '#default_value' => $grouping_fields_default,
              '#description' => t('Select which fields will be displayed alongside the field Nr.!num', ['@num' => $index + 1]),
          ],
          'grouping-limit' => [
              '#type' => 'textfield',
              '#title' => t('Items to display:'),
              '#default_value' => $grouping_limit_default,
              '#size' => 6,
              '#element_validate' => ['views_complex_grouping_validate'],
              '#description' => t('The number of rows to show under the field Nr.!num. Leave 0 to show all of them.', ['@num' => $index + 1]),
          ],
          'grouping-offset' => [
              '#type' => 'textfield',
              '#title' => t('Offset:'),
              '#default_value' => $grouping_offset_default,
              '#size' => 6,
              '#element_validate' => ['views_complex_grouping_validate'],
              '#description' => t('The row to start on.'),
          ],
      ];
    }
  }

  function renderGrouping($records, $groupings = array(), $group_rendered = NULL) {

    // This is for backward compability, when $groupings was a string containing
    // the ID of a single field.
    if (is_string($groupings)) {
      $rendered = $group_rendered === NULL ? TRUE : $group_rendered;
      $groupings = array(array('field' => $groupings, 'rendered' => $rendered));
    }

    // Make sure fields are rendered
    $this->renderFields($this->view->result);

    $sets = array();
    if ($groupings) {

      foreach ($records as $index => $row) {
        // Iterate through configured grouping fields to determine the
        // hierarchically positioned set where the current row belongs to.
        // While iterating, parent groups, that do not exist yet, are added.

        $set = &$sets;

        foreach ($groupings as $level => $info) {

          $field = $info['field'];
          $rendered = isset($info['rendered']) ? $info['rendered'] : $group_rendered;
          $rendered_strip = isset($info['rendered_strip']) ? $info['rendered_strip'] : FALSE;
          $grouping = '';
          $group_content = '';
          // Group on the rendered version of the field, not the raw.  That way,
          // we can control any special formatting of the grouping field through
          // the admin or theme layer or anywhere else we'd like.
          if (isset($this->view->field[$field])) {
            $group_content = $this->getField($index, $field);
            //dpm($group_content);
            if ($this->view->field[$field]->options['label']) {
              $group_content = $this->view->field[$field]->options['label'] . ': ' . $group_content;
            }
            if ($rendered) {
              $grouping = (string) $group_content;
              if ($rendered_strip) {
                $group_content = $grouping = strip_tags(htmlspecialchars_decode($group_content));
              }
            }
            else {
              $grouping = $this->getFieldValue($index, $field);
              // Not all field handlers return a scalar value,
              // e.g. views_handler_field_field.
              if (!is_scalar($grouping)) {
                $grouping = hash('sha256', serialize($grouping));
              }
            }
          }

          // Create the group if it does not exist yet.
          if (empty($set[$grouping])) {

            $set[$grouping]['group'] = $group_content;
            $set[$grouping]['rows'] =[];
            $set[$grouping]['level'] = $level;
            $set[$grouping]['fields'] = [];

            // Add selected fields for this level.
            foreach ($this->options['grouping'][$level]['complex_grouping']['grouping-fields'] as $field) {
              $set[$grouping]['fields'][$field] = $this->rendered_fields[$index][$field];
            }

          }

          // Move the set reference into the row set of the group we just determined.
          $set = &$set[$grouping]['rows'];

        }
        // Add the row to the hierarchically positioned row set we just determined.
        $set[$index] = $row;

      }
    }
    else {
      // Create a single group with an empty grouping field.
      $sets[''] = array(
          'group' => '',
          'rows' => $records,
      );
    }

    // If this parameter isn't explicitely set modify the output to be fully
    // backward compatible to code before Views 7.x-3.0-rc2.
    // @TODO Remove this as soon as possible e.g. October 2020
    if ($group_rendered === NULL) {
      $old_style_sets = array();
      foreach ($sets as $group) {
        $old_style_sets[$group['group']] = $group['rows'];
      }
      $sets = $old_style_sets;
    }

    // Apply the offset and limit.
    array_walk($sets, [$this, 'views_complex_grouping_limit_recursive']);

    return $sets;
  }


  function renderGroupingSets($sets, $level = 0) {
    $output = [];
    $branch = 0;
    $theme_functions = $this->view->buildThemeFunctions($this->groupingTheme);

    foreach ($sets as $set) {
      $branch ++;
      $row = reset($set['rows']);
      // Render as a grouping set.

      if (is_array($row) && isset($row['group'])) {
        $single_output = [
            'theme' => $this->view->buildThemeFunctions('views_complex_grouping_level'),
            'view' => $this->view,
            'grouping' => $this->options['grouping'][$level],
            'grouping_level' => $level+1,
            'grouping_branch' => $branch,
            'rows' => $set['rows'],
            'fields' => $set['fields'],
            'title' => $set['group']
        ];
      }
      // Render as a record set.
      else {
        if ($this->usesRowPlugin()) {
          foreach ($set['rows'] as $index => $row) {
            $this->view->row_index = $index;
            $set['rows'][$index] = $this->view->rowPlugin->render($row);
          }
        }
        $single_output = ['theme' => $theme_functions,
            'view' => $this->view,
            'grouping' => $this->options['grouping'][$level],
            'grouping_level' => $level+1,
            'grouping_branch' => $branch,
            'rows' => $set['rows'],
            'fields' => $set['fields'],
            'title' => $set['group']
        ];
      }

      $output[] = $single_output;
    }
    unset($this->view->row_index);
    return $output;
  }

  /**
   * Recursively limits the number of rows in nested groups.
   *
   * @param array $group_data
   *   A single level of grouped records.
   *
   * @param mixed $key
   *   The key of the array being passed in. Used for when this function is
   *   called from array_walk() and the like. Do not set directly.
   *
   * @params int $level
   *   The current level we are gathering results for. Used for recursive
   *   operations; do not set directly.
   *
   * @return array
   *   An array with a "rows" property that is recursively grouped by the
   *   grouping fields.
   */
  function views_complex_grouping_limit_recursive(&$group_data, $key = NULL, $level = 1) {
    $settings = $this->views_complex_grouping_settings($level - 1);

    $settings['grouping-limit'] = ($settings['grouping-limit'] != 0) ? $settings['grouping-limit'] : NULL;
    $settings['grouping-offset'] = (isset($settings['grouping-offset'])) ? $settings['grouping-offset'] : 0;
    // Slice up the rows according to the offset and limit.
    $group_data['rows'] = array_slice($group_data['rows'], $settings['grouping-offset'], $settings['grouping-limit'], TRUE);

    // For each row, if it appears to be another grouping, recurse again.
    foreach ($group_data['rows'] as &$data) {
      if (is_array($data) && isset($data['group']) && isset($data['rows'])) {
        $this->views_complex_grouping_limit_recursive($data, NULL, $level + 1);
      }
    }
  }

  /**
   * Helper function to retrieve settings for grouping limit.
   *
   * @param int $index
   *   The grouping level to fetch settings for.
   *
   * @return array
   *   Settings for this grouping level.
   */
  function views_complex_grouping_settings($index) {
    if ($this->options['grouping'][$index] && $this->options['grouping'][$index]['complex_grouping']) {
      return $this->options['grouping'][$index]['complex_grouping'];
    }
    else {
      return array('grouping-limit' => 0, 'grouping-offset' => 0);
    }
  }

}