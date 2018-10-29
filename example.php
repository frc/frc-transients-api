
<?php
/**
 * @param $menu_id
 * @param bool $test
 */
function frc_theme_flush_navigation($menu_id, $test = false) {

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
            return ( isset($value->object_id) ) ? true : false;
        }));

        $locations = get_nav_menu_locations();

        $locations = array_keys(array_filter($locations, function ($location) use ($menu_id) {
            return ( $location === $menu_id ) ? true : false;
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
                    $locale = $locales[ $key ];
                } else {
                    $locale = pll_default_language('locale');
                }
            } else {
                $locale = get_option('WPLANG');
            }

            $old_ids = frcTransient()->getData('menu-ids', 'global');

            if (isset($old_ids[ $locale ])) {
                $ids_for_lang = $old_ids[ $locale ];
            } else {
                $old_ids[ $locale ] = [];
                $ids_for_lang       = [];
            }

            $ids_for_lang[ $menu_name ] = $values;
            $old_ids[ $locale ]         = $ids_for_lang;

            frcTransient()->setData('menu-ids', $old_ids, 'global');

            frcTransient()->deleteTransientsLike('(' . $menu_name . '_navigation)', $locale);

            if (function_exists('frc_get_' . $menu_name . '_navigation')) {
                frcTransient()->callFuncTransient('(' . $menu_name . '_navigation)', 'frc_get_' . $menu_name . '_navigation', [ $original_name ], $locale);
            }

            frcTransient()->deleteTransientsLike("(menu-$menu_name)", $locale);
            frcTransient()->callFuncTransient("(menu-$menu_name)", [
                "Navigation",
                "forLocation"
            ], [ $original_name ], $locale);
        } // End foreach().
    } // End if().
}
add_action('wp_update_nav_menu', 'frc_theme_flush_navigation', 20, 2);

/**
 * @param $post_id
 * @param $post
 * @param $update
 */
function frc_theme_maybe_flush_navigation($post_id, $post, $update) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || ! $update || get_post_type($post_id) === 'nav_menu_item') {
        return;
    }


    $menu_ids = frcTransient()->getData('menu-ids', 'global');
    if (function_exists('pll_get_post_language')) {
        $locale = pll_get_post_language($post_id, 'locale');
        $language = pll_get_post_language($post_id);
    } else {
        $locale = get_option('WPLANG');
    }

    if (isset($menu_ids[ $locale ])) {
        foreach ($menu_ids[ $locale ] as $key => $values) {
            if (in_array($post_id, $values)) {
                $original_name = $key;

                if (function_exists('pll_default_language')) {
                    if (pll_default_language('locale') != $locale) {
                        $original_name = $key . '___' . $language;
                    }
                }

                frcTransient()->deleteTransientsLike('(' . $key . '_navigation)', $locale);
                frcTransient()->callFuncTransient('(' . $key . '_navigation)', 'get_' . $key . '_navigation', [ $original_name ], $locale);

                frcTransient()->deleteTransientsLike("(menu-$key)", $locale);
                frcTransient()->callFuncTransient("(menu-$key)", [
                    "Navigation",
                    "forLocation"
                ], [ $original_name ], $locale);
            }
        }
    }
}
add_action('save_post', 'frc_theme_maybe_flush_navigation', 20, 3);

/**
 * Example of flushing by post type
 */
function frc_theme_post_type_transients($post_id, $post, $update) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || ! $update) {
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

    frcTransient()->setPostData($post_id, $post, $locale);
}
add_action('save_post', 'frc_theme_post_type_transients', 9, 3);

function frc_post_deleted($post_id) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }


    if (function_exists('pll_get_post_language')) {
        $post_locale = pll_get_post_language($post_id, 'locale');
    } else {
        $post_locale = get_option('WPLANG');
    }
    frcTransient()->deletePostTransients($post_id, $post_locale);
}
add_action('before_delete_post', 'frc_post_deleted', 10, 1);
add_action('trash_post', 'frc_post_deleted', 10, 1);

function frc_post_untrashed($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    frcTransient()->setAcfFields($post_id);
    frcTransient()->setPostData($post_id);
}
add_action('untrashed_post', 'frc_post_untrashed', 10, 1);

function frc_permalinks_changed($oldvalue, $_newvalue) {
}

add_action('permalink_structure_changed', 'frc_permalinks_changed', 10, 2);
