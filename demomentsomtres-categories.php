<?php

/*
  Plugin Name: DeMomentSomTres Categories
  Plugin URI: http://demomentsomtres.com/english/wordpress-plugins/demomentsomtres-categories/
  Description: Displays all categories based on shortcode filtering some of them.
  Version: 2.5.5
  Author: marcqueralt
  Author URI: http://demomentsomtres.com
  License: GPLv2 or later
 */

// Create a helper function for easy SDK access.
function dms3categories_fs() {
    global $dms3categories_fs;

    if (!isset($dms3categories_fs)) {
        // Include Freemius SDK.
        require_once dirname(__FILE__) . '/freemius/start.php';

        $dms3categories_fs = fs_dynamic_init(array(
            'id'             => '531',
            'slug'           => 'demomentsomtres-categories',
            'type'           => 'plugin',
            'public_key'     => 'pk_d106e32c7d2ff0792098c21bac984',
            'is_premium'     => false,
            'has_addons'     => false,
            'has_paid_plans' => false,
            'menu'           => array(
                'first-path' => 'plugins.php',
                'account'    => false,
                'contact'    => false,
                'support'    => false,
            ),
        ));
    }

    return $dms3categories_fs;
}

// Init Freemius.
dms3categories_fs();

require_once (dirname(__FILE__) . '/lib/class-tgm-plugin-activation.php');

define('DMS3_CATS_TEXT_DOMAIN', 'DeMomentSomTres-Categories');

$demomentsomtres_categories = new DeMomentSomTresCategories();

class DeMomentSomTresCategories {

    const MENU_SLUG            = 'dmst_categories';
    const OPTIONS              = 'dmst_categories';
    const OPTION_EXCLUDED_CATS = 'excludedCategories';
    const OPTION_FILTER_CATS   = 'OPTION_FILTER_CATS';
    const ACTION               = 'dms3CategoriesMigrate';

    private $pluginURL;
    private $pluginPath;
    private $langDir;

    /**
     * @since 2.0
     */
    function __construct() {
        $this->pluginURL  = plugin_dir_url(__FILE__);
        $this->pluginPath = plugin_dir_path(__FILE__);
        $this->langDir    = dirname(plugin_basename(__FILE__)) . '/languages';

        add_action('plugins_loaded', array(
            &$this,
            'plugin_init'
        ));
        add_action('tgmpa_register', array(
            $this,
            'required_plugins'
        ));
        add_action('tf_create_options', array(
            $this,
            'admin'
        ));
        add_action('widgets_init', array(
            &$this,
            'register_widgets'
        ));
        add_shortcode('DeMomentSomTres-Categories', array(
            &$this,
            'demomentsomtres_categories_shortcode'
        ));
        add_action("init", array(
            $this,
            "activar_filtre"
        ));
    }

    /**
     * @since 2.0
     */
    function plugin_init() {
        load_plugin_textdomain('DeMomentSomTres-Categories', false, $this->langDir);
    }

    /**
     * @since 2.3
     */
    function required_plugins() {
        $plugins = array(array(
                'name'     => 'Titan Framework',
                'slug'     => 'titan-framework',
                'required' => true
            ),);
        tgmpa($plugins);
    }

    /**
     * @since 2.3
     */
    function admin() {
        $titan     = TitanFramework::getInstance(self::OPTIONS);
        $panel     = $titan->createAdminPanel(array(
            'name'   => __("DeMomentSomTres Categories", 'DeMomentSomTres-Categories'),
            'id'     => "dms3categories",
            'title'  => __("DeMomentSomTres Categories", 'DeMomentSomTres-Categories'),
            'desc'   => __("Gestiona les categories que s'oculten", 'DeMomentSomTres-Categories'),
            'parent' => 'options-general.php',
        ));
        $tabConfig = $panel->createTab(array(
            'name'  => __('Configuration', 'DeMomentSomTres-Categories'),
            'title' => __('General Configuration', 'DeMomentSomTres-Categories'),
            'desc'  => __('Configure the plugin', 'DeMomentSomTres-Categories'),
            'id'    => 'configuration'
        ));
        $tabConfig->createOption(array(
            'name' => __("Activate", 'DeMomentSomTres-Categories'),
            'id'   => self::OPTION_FILTER_CATS,
            'type' => "checkbox",
            'desc' => __("Activate the_category filter", 'DeMomentSomTres-Categories'),
        ));
        $tabConfig->createOption(array(
            'name' => __("Categories", 'DeMomentSomTres-Categories'),
            'id'   => self::OPTION_EXCLUDED_CATS,
            'type' => 'multicheck-categories',
            'desc' => __('Select the categories that you want to exclude from the shortcode and the widget', 'DeMomentSomTres-Categories'),
        ));
        $tabConfig->createOption(array(
            'type'      => "save",
            'save'      => __("Save Changes", 'DeMomentSomTres-Categories'),
            'use_reset' => false
        ));
    }

    /**
     * @since 2.3
     */
    function activar_filtre() {
        $options = maybe_unserialize(get_option(self::OPTIONS . "_options"));
        if (isset($options[self::OPTION_FILTER_CATS])):
            add_filter('get_the_categories', array(
                $this,
                'the_category_filter'
                    ), 10, 2);
        endif;
    }

    /**
     * @since 2.0
     * @return boolean
     */
    function register_widgets() {
        return register_widget("DeMomentSomTresCategoriesWidget");
    }

    public static function getCategories($excludedCats, $args = array()) {
        $titan         = TitanFramework::getInstance(self::OPTIONS);
        $globalExclude = implode($titan->getOption(DeMomentSomTresCategories::OPTION_EXCLUDED_CATS), ',');
        $exclude       = rtrim(ltrim($excludedCats . ',' . $globalExclude, ','), ',');
        $args          = array(
            'type'         => 'post',
            'child_of'     => '',
            'parent'       => 0,
            'orderby'      => 'slug',
            'order'        => 'ASC',
            'hide_empty'   => 1,
            'hierarchical' => 1,
            'exclude'      => $exclude,
            'include'      => '',
            'number'       => '',
            'taxonomy'     => 'category',
            'pad_counts'   => false
        );
        $categories    = get_categories($args);
        return $categories;
    }

    /**
     * Generates all the content of the shortcode
     * @param mixed $atts
     * @return string
     * @since 1.0
     */
    function demomentsomtres_categories_shortcode($atts) {
        extract(shortcode_atts(array('exclude' => '',), $atts));
        $categories = $this->getCategories($exclude);
        $output     = '';
        $output     .= '<ul class="dmst-categories">';
        foreach ($categories as $category) {
            $output .= '<li>';
            $output .= '<a href="' . get_category_link($category->term_id) . '" title="' . $category->name . '" ' . '>' . $category->name . '</a>: ';
            $output .= $category->description;
            $output .= '</li>';
        }
        $output .= '</ul>';
        return $output;
    }

    /**
     *
     * @param type $thelist
     * @param type $separator
     * @return type
     * @since 2.1
     */
    public static function the_category_filter($cats, $separator = ',') {
        if (!is_admin()) {
            $options = maybe_unserialize(get_option(self::OPTIONS . "_options"));
            if (isset($options[self::OPTION_EXCLUDED_CATS])):
                $exclude = maybe_unserialize($options[self::OPTION_EXCLUDED_CATS]);
                $newlist = array();
                foreach ($cats as $cat) {
                    if (!in_array($cat->term_id, $exclude))
                        $newlist[] = $cat;
                }
                return $newlist;
            else:
                return $cats;
            endif;
        }
        return $cats;
    }

}

/**
 * @since 2.0
 */
class DeMomentSomTresCategoriesWidget extends WP_Widget {

    /**
     * @since 2.0
     */
    function __construct() {
        $widget_ops = array(
            'classname'   => 'DMS3-Categories',
            'description' => __('Shows a Categories List', 'DeMomentSomTres-Categories')
        );
        parent::__construct('DeMomentSomTresCategories', __('DeMomentSomTres Categories', 'DeMomentSomTres-Categories'), $widget_ops);
    }

    /**
     * @since 2.0
     */
    function form($instance) {
        $title        = esc_attr($instance['title']);
        $class        = esc_attr($instance['class']);
        $exclude      = esc_attr($instance['exclude']);
        $count        = isset($instance['count']) ? (bool) $instance['count'] : false;
        $hierarchical = isset($instance['hierarchical']) ? (bool) $instance['hierarchical'] : false;
        $dropdown     = isset($instance['dropdown']) ? (bool) $instance['dropdown'] : false;
        echo '<p><label for="' . $this->get_field_id('title') . '">' . __('Title:', 'DeMomentSomTres-Categories') . '</label>';
        echo '<input class="widefat" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" type="text" value="' . $title . '" /></p>';
        echo '<p><label for="' . $this->get_field_id('class') . '">' . __('List class:', 'DeMomentSomTres-Categories') . '</label>';
        echo '<input class="widefat" id="' . $this->get_field_id('class') . '" name="' . $this->get_field_name('class') . '" type="text" value="' . $class . '" /></p>';
        echo '<p><label for="' . $this->get_field_id('exclude') . '">' . __('Excluded categories ID (comma separated):', 'DeMomentSomTres-Categories') . '</label>';
        echo '<input class="widefat" id="' . $this->get_field_id('exclude') . '" name="' . $this->get_field_name('exclude') . '" type="text" value="' . $exclude . '" /></p>';
        echo '<p><input type="checkbox" class="checkbox" id="' . $this->get_field_id('dropdown') . '" name="' . $this->get_field_name('dropdown') . '"' . checked($dropdown) . ' />';
        echo '<label for="' . $this->get_field_id('dropdown') . '">' . __('Display as dropdown', 'DeMomentSomTres-Categories') . '</label><br />';
        echo '<input type="checkbox" class="checkbox" id="' . $this->get_field_id('count') . '" name="' . $this->get_field_name('count') . '"' . checked($count) . ' />';
        echo '<label for="' . $this->get_field_id('count') . '">' . __('Show post counts', 'DeMomentSomTres-Categories') . '</label><br />';
        echo '<input type="checkbox" class="checkbox" id="' . $this->get_field_id('hierarchical') . '" name="' . $this->get_field_name('hierarchical') . '"' . checked($hierarchical) . ' />';
        echo '<label for="' . $this->get_field_id('hierarchical') . '">' . __('Show hierarchy', 'DeMomentSomTres-Categories') . '</label></p>';
    }

    /**
     * @since 1.0
     */
    function update($new_instance, $old_instance) {
        $new_instance['title'] = strip_tags($new_instance['title']);
        return $new_instance;
    }

    /**
     * @since 1.0
     */
    function widget($args, $instance) {

        /** This filter is documented in wp-includes/default-widgets.php */
        $title   = apply_filters('widget_title', empty($instance['title']) ? __('Categories', 'DeMomentSomTres-Categories') : $instance['title'], $instance, $this->id_base);
        $class   = $instance['class'];
        $exclude = $instance['exclude'];

        $titan         = TitanFramework::getInstance(DeMomentSomTresCategories::OPTIONS);
        $globalExclude = implode($titan->getOption(DeMomentSomTresCategories::OPTION_EXCLUDED_CATS), ',');
        $exclude       = rtrim(ltrim($exclude . ',' . $globalExclude, ','), ',');

        $c = !empty($instance['count']) ? '1' : '0';
        $h = !empty($instance['hierarchical']) ? '1' : '0';
        $d = !empty($instance['dropdown']) ? '1' : '0';

        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        $cat_args = array(
            'orderby'      => 'name',
            'show_count'   => $c,
            'hierarchical' => $h,
            'exclude'      => $exclude
        );

        if ($d) {
            $cat_args['show_option_none'] = __('Select Category', 'DeMomentSomTres-Categories');

            wp_dropdown_categories(apply_filters('widget_categories_dropdown_args', $cat_args));
            echo "<script type='text/javascript'>
            var dropdown = document.getElementById('cat');
            function onCatChange() {
            if (dropdown.options[dropdown.selectedIndex].value > 0) {
            location.href = '" . home_url() . "/?cat=' + dropdown.options[dropdown.selectedIndex].value;
            }
            }
            dropdown.onchange = onCatChange;
            </script>";
        }
        else {
            echo "<ul class='$class'>";
            $cat_args['title_li'] = '';
            wp_list_categories(apply_filters('widget_categories_args', $cat_args));
            echo "</ul>";
        }
        echo $args['after_widget'];
    }

}
