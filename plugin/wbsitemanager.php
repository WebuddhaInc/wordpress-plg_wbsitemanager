<?php

/**
 * Plugin Name: Webuddha Site Manager
 * Plugin URI: http://www.webuddha.com/wordpress/wbsitemanager
 * Description: Fetch, List, and Update wordpress core, plugins, and themes remotely or from the command line.
 * Version: 0.1.0
 * Author: webuddha
 * Author URI: http://www.webuddha.com
 * License: GPL2
 */

/**
 * Wordpress Hooks
 */

  register_activation_hook( __FILE__, 'wbsitemanager::activate' );
  add_action('admin_menu', 'wbsitemanager::admin_menu');

/**
 * wbSiteManager Core Class
 */

  class wbSiteManager {

    /**
     * [__construct description]
     */
    public function __construct(){
    }

    /**
     * [admin_menu description]
     * @return [type] [description]
     */
    static function admin_menu(){
      add_menu_page(
        'Wordpress Site Manager by Webuddha',
        'Site Manager',
        'activate_plugins',
        'wbsitemanager',
        'wbsitemanager::page_main'
        );
      add_submenu_page(
        'wbsitemanager',
        'Webuddha Site Manager',
        'View History',
        'activate_plugins',
        'wbsitemanager_alt',
        'wbsitemanager::page_history'
        );
    }

    static function page_main(){
      echo '12';
    }
    static function page_history(){
      echo '12';
    }

    /**
     * [activate description]
     * @return [type] [description]
     */
    static function activate(){
      return true;
    }

    /**
     * [process_update description]
     * @return [type] [description]
     */
    public function process_update(){
    }

    /**
     * [purge_updates description]
     * @return [type] [description]
     */
    public function purge_updates(){
    }

    /**
     * [fetch_updates description]
     * @return [type] [description]
     */
    public function fetch_updates(){
    }

    /**
     * [list_updates description]
     * @return [type] [description]
     */
    public function list_updates(){
    }

  }
