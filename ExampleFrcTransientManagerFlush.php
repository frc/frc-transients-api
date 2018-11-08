<?php
/**
 * Created by PhpStorm.
 * User: janneaalto
 * Date: 08/11/2018
 * Time: 12.15
 */

function frcThemePermalinksChanged($oldvalue, $_newvalue) {
}

add_action('permalink_structure_changed', 'frcThemePermalinksChanged', 10, 2);

/**
 * @param $menu_id
 * @param bool $test
 */
function frcThemeFlushNavigation($menu_id, $test = false) {

    if (empty($test)) {
        $values = wp_get_nav_menu_items($menu_id, [
            'orderby'     => 'ID',
            'output'      => ARRAY_A,
            'output_key'  => 'ID',
            'post_status' => 'draft,publish',
        ]);

        $values = array_map(function ($value) {
            return $value->object_id;
        }, array_filter($values, function ($value) {
            return (isset($value->object_id)) ? true : false;
        }));

        $locations = get_nav_menu_locations();

        $locations = array_keys(array_filter($locations, function ($location) use ($menu_id) {
            return ($location === $menu_id) ? true : false;
        }));

        foreach ($locations as $menu_name) {
            $original_name = $menu_name;

            if (function_exists('pll_languages_list')) {
                //polylang doesn't have language on admin
                if (strpos($menu_name, '___') !== false) {
                    $menu_name = explode('___', $menu_name);
                    $lang      = $menu_name[1];
                    $menu_name = $menu_name[0];

                    $locales = pll_languages_list([
                        'fields' => 'locale',
                    ]);

                    $languages = pll_languages_list([
                        'fields' => 'slug',
                    ]);

                    $key    = array_search($lang, $languages);
                    $locale = $locales[$key];
                } else {
                    $locale = pll_default_language('locale');
                }
            } else {
                $locale = get_option('WPLANG');
            }

            $old_ids = frcTransient()->getData('menu-ids', 'global');

            if (isset($old_ids[$locale])) {
                $ids_for_lang = $old_ids[$locale];
            } else {
                $old_ids[$locale] = [];
                $ids_for_lang     = [];
            }

            $ids_for_lang[$menu_name] = $values;
            $old_ids[$locale]         = $ids_for_lang;

            frcTransient()->setData('menu-ids', $old_ids, 'global');

            frcTransient()->deleteTransientsLike('(' . $menu_name . '_navigation)', $locale);

            if (function_exists('frc_get_' . $menu_name . '_navigation')) {
                //calls function get_header_navigation('header___sv') (f.ex $key == 'header' $original_name == 'header___sv' )
                frcTransient()->callFuncTransient('(' . $menu_name . '_navigation)', 'frc_get_' . $menu_name . '_navigation', [$original_name], $locale);
            }

            frcTransient()->deleteTransientsLike("(menu-$menu_name)", $locale);
            //calls function Navigation::forLocation('header___sv') (f.ex $key == 'header' $original_name == 'header___sv' )
            frcTransient()->callFuncTransient("(menu-$menu_name)", [
                "Navigation",
                "forLocation"
            ], [$original_name], $locale);
        } // End foreach().
    } // End if().
}

add_action('wp_update_nav_menu', 'frcThemeFlushNavigation', 20, 2);

/**
 * @param $post_id
 * @param $post
 * @param $update
 */
function frcThemeMaybeFlushNavigation($post_id, $post, $update) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$update || get_post_type($post_id) === 'nav_menu_item') {
        return;
    }

    $menu_ids = frcTransient()->getData('menu-ids', 'global');
    if (function_exists('pll_get_post_language')) {
        $locale   = pll_get_post_language($post_id, 'locale');
        $language = pll_get_post_language($post_id);
    } else {
        $locale = get_option('WPLANG');
    }

    if (isset($menu_ids[$locale])) {
        foreach ($menu_ids[$locale] as $key => $values) {
            if (in_array($post_id, $values)) {
                $original_name = $key;

                if (function_exists('pll_default_language')) {
                    if (pll_default_language('locale') != $locale) {
                        $original_name = $key . '___' . $language;
                    }
                }

                frcTransient()->deleteTransientsLike('(' . $key . '_navigation)', $locale);
                //calls function get_header_navigation('header___sv') (f.ex $key == 'header' $original_name == 'header___sv' )
                frcTransient()->callFuncTransient('(' . $key . '_navigation)', 'get_' . $key . '_navigation', [$original_name], $locale);

                frcTransient()->deleteTransientsLike("(menu-$key)", $locale);
                //calls function Navigation::forLocation('header___sv') (f.ex $key == 'header' $original_name == 'header___sv' )
                frcTransient()->callFuncTransient("(menu-$key)", [
                    "Navigation",
                    "forLocation"
                ], [$original_name], $locale);
            }
        }
    }
}

add_action('save_post', 'frcThemeMaybeFlushNavigation', 20, 3);

/**
 * Example of flushing by post type
 */
function frcThemePostTypeTransients($post_id, $post, $update) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$update) {
        return;
    }
    $locale = 'fi';

    if (function_exists('pll_get_post_language')) {
        $locale = pll_get_post_language($post_id, 'locale');
    }

    switch (get_post_type($post_id)) :
        case 'page':
            if (function_exists('pll_get_post_language')) {
                $locale = pll_get_post_language($post_id, 'locale');
            }
            frcTransient()->deleteTransientsLike("(page)-$post_id", $locale);
            break;
        default:
            break;
    endswitch;

    //This function exists in extending example
    //frcTransient()->setPostData($post_id, $post, $locale);
}

add_action('save_post', 'frcThemePostTypeTransients', 9, 3);

