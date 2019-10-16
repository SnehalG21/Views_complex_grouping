<?php
///**
// * @file
// * Theme for Complex Grouping views.
// */
///**
// * Prepares variables for views complex grouping templates.
// *
// * Default template: views-complex-grouping-leave.html.twig.
// *
// * @param array $vars
// *   An associative array containing:
// *   - view: A ViewExecutable object.
// *   - rows: The raw row data.
// */
function template_preprocess_views_complex_grouping_leave(&$vars) {
  $vars['content'] = $vars['view']->style_plugin->renderGroupingSets($vars['rows'], $vars['grouping_level']);
  $a = ":djhnbcfb";
}
