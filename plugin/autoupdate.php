<?php

/**
 *
 * This is a CLI Script only
 *   /usr/bin/php /path/to/site/cli/autoupdate.php
 *
 * For Help
 *   php autoupdate.php -h
 *
 */

// Set Version
  const _WordpressCliAutoUpdateVersion = '0.1.0';

// CLI or Valid Include
  if( php_sapi_name() != 'cli' && !defined('_WPEXEC') )
    die('Invalid Access');

// Definitions
  defined('_WPEXEC') || define('_WPEXEC', true);
  defined('WP_ADMIN') || define('WP_ADMIN', true);
  defined('WP_NETWORK_ADMIN') || define('WP_NETWORK_ADMIN', false);
  defined('WP_USER_ADMIN') || define('WP_USER_ADMIN', false);
  defined('STDIN') || define('STDIN', fopen('php://input', 'r'));
  defined('STDOUT') || define('STDOUT', fopen('php://output', 'w'));

// Basic Initialization
  if( !defined('ABSPATH') )
    require_once( __DIR__ . '/../../../wp-load.php' );
  require_once( __DIR__ . '/classes/params.class.php' );
  require_once( ABSPATH . 'wp-admin/includes/admin.php' );
  require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
  require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

/**
 * This script will download and install all available updates.
 */

  class WordpressCliAutoUpdate {

    /**
     * [$__outputBuffer description]
     * @var null
     */
    public $__outputBuffer = null;
    public $db             = null;
    public $updater        = null;
    public $installer      = null;
    public $config         = null;

    /**
     * [__construct description]
     */
    public function __construct(){

      // Input
        $this->input = new wbSiteManager_Params( array_slice($_SERVER['argv'], 1), 'cli' );

      // Execute
        if( $this->input->get('x', $this->input->get('export')) ){
          $this->startOutputBuffer();
        }
        if( $this->input->get('p', $this->input->get('purge')) ){
          $this->doPurgeUpdatesCache();
        }
        if( $this->input->get('f', $this->input->get('fetch')) ){
          $this->doFetchUpdates();
        }
        if(
          $this->input->get('l', $this->input->get('list'))
          ||
          $this->input->get('u', $this->input->get('update'))
          ){
          $this->doIterateUpdates();
        }
        if( $this->input->get('h', $this->input->get('help')) ){
          $this->doEchoHelp();
        }
        if( $this->input->get('x', $this->input->get('export')) ){
          $this->dumpOutputBuffer();
        }

    }

    /**
     * [doPurgeUpdatesCache description]
     * @return [type] [description]
     */
    public function doPurgeUpdatesCache(){
      // wp_clean_update_cache();
      set_site_transient( 'update_core', null );
      set_site_transient( 'update_plugins', null );
      set_site_transient( 'update_themes', null );
    }

    /**
     * [doFetchUpdates description]
     * @return [type] [description]
     */
    public function doFetchUpdates(){
      wp_version_check( array() );
      wp_update_plugins( array() );
      wp_update_themes( array() );
    }

    /**
     * [getUpdateRows description]
     * @param  [type] $lookup [description]
     * @param  [type] $start  [description]
     * @param  [type] $limit  [description]
     * @return [type]         [description]
     */
    public function getUpdateRows( $lookup = null, $start = null, $limit = null ){
      $updates = array();

      // Core
        $core_updates = get_site_transient( 'update_core' );
        if( !empty($core_updates) && !empty($core_updates->updates) ){
          $core_update = reset($core_updates->updates);
          if( version_compare($core_updates->version_checked, $core_update->version, '<') )
            $updates[] = $this->_newUpdateRow(array(
              'update_id'         => 'core:'.$core_update->version,
              'extension_id'      => 'core',
              'name'              => 'Wordpress',
              'element'           => 'wordpress',
              'type'              => 'core',
              'status'            => 'available',
              'locale'            => $core_update->locale,
              'version'           => $core_update->version,
              'detailsurl'        => $core_update->download,
              'installed_version' => $core_updates->version_checked
              ));
        }

      // Plugins
        $plugins = get_plugins();
        $plugin_updates = get_site_transient( 'update_plugins' );
        if( !empty($plugin_updates) && !empty($plugin_updates->response) ){
          foreach( $plugin_updates->response AS $key => $data ){
            if( isset($plugins[ $data->plugin ]) ){
              if( version_compare($plugins[ $data->plugin ]['Version'], $data->new_version, '<') )
                $updates[] = $this->_newUpdateRow(array(
                  'update_id'         => 'plugin:'.$data->slug.':'.$data->new_version,
                  'extension_id'      => $data->plugin,
                  'name'              => $plugins[ $data->plugin ]['Name'],
                  'description'       => $plugins[ $data->plugin ]['Description'],
                  'element'           => $data->slug,
                  'type'              => 'plugin',
                  'status'            => 'available',
                  'version'           => $data->new_version,
                  'detailsurl'        => $data->package,
                  'infourl'           => $plugins[ $data->plugin ]['PluginURI'],
                  'installed_version' => $plugins[ $data->plugin ]['Version'],
                  ));
            }
          }
        }

      // Plugins
        $themes = get_themes();
        $theme_updates = get_site_transient( 'update_themes' );
        if( !empty($theme_updates) && !empty($theme_updates->response) ){
          foreach( $theme_updates->response AS $key => $data ){
            if( isset($themes[ $data->theme ]) ){
              $updates[] = $this->_newUpdateRow(array(
                'update_id'         => 'theme:'.$data->slug.':'.$data->new_version,
                'extension_id'      => $data->theme,
                'name'              => $themes[ $data->theme ]['Name'],
                'description'       => $themes[ $data->theme ]['Description'],
                'element'           => $data->slug,
                'type'              => 'theme',
                'status'            => 'available',
                'version'           => $data->new_version,
                'detailsurl'        => $data->package,
                'infourl'           => $themes[ $data->theme ]['PluginURI'],
                'installed_version' => $themes[ $data->theme ]['Version'],
                ));
            }
          }
        }

      // Filter Lookup
        $start = $start ? (int)$start : 0;
        $count = $limit ? $start + $limit : count($updates);
        $count = $count > count($updates) ? count($updates) : $count;
        $final_updates = array();
        for( $i = $start; $i < $count; $i++ ){
          $passed = true;
          if( $lookup && is_array($lookup) ){
            foreach($lookup AS $key => $val){
              switch( $key ){
                case 'update_id':
                  $update_rule = explode(':', $val);
                  $match_rule  = explode(':', $updates[$i]->{$key});
                  for( $r=0; $r < count($match_rule); $r++ ){
                    if( !isset($update_rule[$r]) || ($update_rule[$r] != '*' && $update_rule[$r] != $match_rule[$r]) ){
                      $passed = false;
                      break 2;
                    }
                  }
                  break;
                default:
                  if( $updates[$i]->{$key} != $val ){
                    $passed = false;
                    break;
                  }
                  break;
              }
            }
            if( !$passed ){
              continue;
            }
          }
          $final_updates[] = $updates[$i];
        }

      // Return
        return $final_updates;

    }

    /**
     * [_newUpdateRow description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    private function _newUpdateRow( $data ){
      return new wbSiteManager_Params(array_merge( array(
        'update_id'         => null,
        'update_site_id'    => null,
        'extension_id'      => null,
        'name'              => null,
        'description'       => null,
        'element'           => null,
        'type'              => null,
        'locale'            => null,
        'folder'            => null,
        'client_id'         => null,
        'version'           => null,
        'data'              => null,
        'detailsurl'        => null,
        'infourl'           => null,
        'extra_query'       => null,
        'installed_version' => null
        ), $data ));
    }

    /**
     * [doInstallUpdate description]
     * @param  [type] $update_id   [description]
     * @param  [type] $build_url   [description]
     * @param  [type] $package_url [description]
     * @return [type]              [description]
     */
    public function doInstallUpdate( $update_row ){
      global $wp_filesystem;

      /*
      // Parse Update ID
        $update_keys = array('type', 'element', 'version', 'locale');
        $update_vals = explode( ':', $update_row->update_id );
        $update_rule = new wbSiteManager_Params( array_combine(array_intersect_key($update_keys, $update_vals), array_intersect_key($update_vals, $update_keys)) );
        if( empty($update_rule->type) ){
          $this->out('Invalid Update ID: ' . $update_id);
          return false;
        }
        $this->out('Processing Update ID: '. $update_id);
      */

      // Switch Type
        $this->out('Processing Update ID: '. $update_row->update_id);
        switch( $update_row->type ){

          case 'core':

            // Load Update Record
              $remoteUrl = 'update-core.php?action=do-core-upgrade';
              $reinstall = false;
              if( $update_row->version == $update_row->installed_version ){
                $reinstall = true;
                $remoteUrl = 'update-core.php?action=do-core-reinstall';
              }
              $update = find_core_update( $update_row->version, $update_row->get('locale', 'en_US') );
              if( !$update ){
                $this->out(' - Failed to Load Update');
                return false;
              }
              if( $reinstall )
                $update->response = 'reinstall';

            // Confirm Write Access
              $allow_relaxed_file_ownership = isset( $update->new_files ) && !$update->new_files;
              if( false === ($credentials = request_filesystem_credentials($remoteUrl, '', false, ABSPATH, array('version', 'locale'), $allow_relaxed_file_ownership)) ){
                $this->out(' - Invalid File Permission');
                return false;
              }
              if( !WP_Filesystem( $credentials, ABSPATH, $allow_relaxed_file_ownership ) ){
                $this->out(' - Failed to load File Permissions');
                return false;
              }
              if( $wp_filesystem->errors->get_error_code() ){
                foreach( $wp_filesystem->errors->get_error_messages() AS $message )
                  $this->out(' - File Error: ' . $message);
                return false;
              }

            // Run Update
              $upgrader_skin = new wbSiteManager_WP_Upgrader_Skin( array(), $this );
              $upgrader = new Core_Upgrader( $upgrader_skin );
              $result = $upgrader->upgrade( $update, array('allow_relaxed_file_ownership' => $allow_relaxed_file_ownership) );
              $response_html = explode("\n", strip_tags(ob_get_clean())); ob_end_clean();
              if( is_wp_error($result) ){
                if( $result->get_error_data() && is_string($result->get_error_data()) )
                  $message = $result->get_error_message() . ': ' . $result->get_error_data();
                else
                  $message = $result->get_error_message();
                $this->out(' - Update Error: ' . $message);
                if( 'up_to_date' != $result->get_error_code() )
                  $this->out(' - Insallation Failed');
                return false;
              }

            // Clear Cache
              set_site_transient( 'update_core', null );

            break;

          case 'plugin':

            // Install vs Upgrade
              if( $install ){

                // Get Plugins API
                  $plugin_api = plugins_api( 'plugin_information', array(
                    'slug' => $update_row->extension_id,
                    'fields' => array(
                      'short_description' => false,
                      'sections'          => false,
                      'requires'          => false,
                      'rating'            => false,
                      'ratings'           => false,
                      'downloaded'        => false,
                      'last_updated'      => false,
                      'added'             => false,
                      'tags'              => false,
                      'compatibility'     => false,
                      'homepage'          => false,
                      'donate_link'       => false,
                    )));

                // Load Plugin Updater
                  $upgrader = new Plugin_Upgrader(
                    new wbSiteManager_Plugin_Upgrader_Skin( array(
                      'title'  => 'Install Plugin: ' . $update_row->extension_id . ' v' . $update_row->version,
                      'nonce'  => 'install-plugin_' . $update_row->extension_id,
                      'url'    => 'update.php?action=install-plugin&plugin=' . urlencode( $update_row->extension_id ),
                      'plugin' => $update_row->extension_id,
                      'api'    => $api
                      ), $this )
                    );
                  $upgrader->install( $plugin_api->download_link );

              }
              else {

                // Load Plugin Updater
                  $upgrader = new Plugin_Upgrader(
                    new wbSiteManager_Plugin_Upgrader_Skin( array(
                      'title'  => 'Upgrade Plugin: ' . $update_row->extension_id . ' v' . $update_row->version,
                      'nonce'  => 'upgrade-plugin_' . $update_row->extension_id,
                      'url'    => 'update.php?action=upgrade-plugin&plugin=' . urlencode( $update_row->extension_id ),
                      'plugin' => $update_row->extension_id
                      ), $this )
                    );
                  $upgrader->upgrade( $update_row->extension_id );

              }

            // Process Result
              if( empty($upgrader->result) ){
                $this->out(' - Installation Failed');
                return false;
              }

            // Clear Cache
              // set_site_transient( 'update_core', null );

            break;

          case 'theme':

            // Install vs Upgrade
              if( $install ){

                // Load API
                  $api = themes_api('theme_information', array(
                    'slug'   => $update_row->extension_id,
                    'fields' => array(
                      'sections' => false,
                      'tags'     => false
                      )
                    ));

                // Load Theme Updater
                  $upgrader = new Theme_Upgrader(
                    new wbSiteManager_Theme_Upgrader_Skin(array(
                      'title' => 'Install Theme: ' . $update_row->extension_id,
                      'nonce' => 'install-theme_' . $update_row->extension_id,
                      'url'   => 'update.php?action=install-theme&theme=' . urlencode( $update_row->extension_id ),
                      'theme' => $update_row->extension_id,
                      'api'   => $api
                      ), $this )
                    );
                  $upgrader->install( $api->download_link );

              }
              else {

                // Load Theme Updater
                  $upgrader = new Theme_Upgrader(
                    new wbSiteManager_Theme_Upgrader_Skin(array(
                      'title' => 'Upgrade Theme: ' . $update_row->extension_id,
                      'nonce' => 'upgrade-theme_' . $update_row->extension_id,
                      'url'   => 'update.php?action=upgrade-theme&theme=' . urlencode( $update_row->extension_id ),
                      'theme' => $update_row->extension_id
                      ), $this )
                    );
                  $upgrader->upgrade( $update_row->extension_id );

              }

            // Process Result
              if( empty($upgrader->result) ){
                $this->out(' - Installation Failed');
                return false;
              }

            // Clear Cache
              // set_site_transient( 'update_core', null );

            break;

        }

      // Complete
        $this->out(' - Update Success');
        return true;

    }

    /**
     * [doIterateUpdates description]
     * @return [type] [description]
     */
    public function doIterateUpdates(){

      // Build Update Filter
        $update_lookup = array();

      // All Items
        if( $this->input->get('a', $this->input->get('all')) ){
        }

      // Core Items
        if( $this->input->get('c', $this->input->get('core')) ){
          $lookup = array(
            'type'    => 'file',
            'element' => 'wordpress'
            );
          if( $version = $this->input->get('v', $this->input->get('version')) ){
            $lookup['version'] = $version;
          }
          $update_lookup[] = $lookup;
        }

      // Extension Lookup
        if( $extension_lookup = $this->input->get('e', $this->input->get('extension')) ){
          if( is_numeric($extension_lookup) ){
            $lookup = array(
              'extension_id' => (int)$extension_lookup
              );
          }
          else {
            $lookup = array(
              'element' => (string)$extension_lookup
              );
          }
          if( $type = $this->input->get('t', $this->input->get('type')) ){
            $lookup['type'] = $type;
          }
          if( $version = $this->input->get('v', $this->input->get('version')) ){
            $lookup['version'] = $version;
          }
          $update_lookup[] = $lookup;
        }

      // Update ID
        if( $update_id = $this->input->get('i', $this->input->get('id')) ){
          $update_lookup[] = array(
            'update_id' => $update_id
            );
        }

      // List / Export / Process Updates
        $update_rows = $this->getUpdateRows( array_shift($update_lookup) );
        if( $update_rows ){
          $do_list     = $this->input->get('l', $this->input->get('list'));
          $do_export   = $this->input->get('x', $this->input->get('export'));
          $do_update   = $this->input->get('u', $this->input->get('update'));
          $export_data = null;
          if( $do_export ){
            $export_data = array(
              'updates' => array()
              );
          }
          else if( $do_list ){
            $this->out(implode('',array(
              $this->cli_str('element', 14),
              $this->cli_str('type', 8),
              $this->cli_str('version', 10),
              $this->cli_str('installed', 10),
              $this->cli_str('eid', 16),
              $this->cli_str('uid', 22)
              )));
          }
          $run_update_rows = array();
          do {
            foreach( $update_rows AS $update_row ){
              if( $do_export ){
                $export_data['updates'][] = $update_row;
              }
              else if( $do_list ){
                $this->out(implode('',array(
                  $this->cli_str($update_row->element, 14),
                  $this->cli_str($update_row->type, 8),
                  $this->cli_str($update_row->version, 10),
                  $this->cli_str($update_row->installed_version, 10),
                  $this->cli_str($update_row->extension_id, 16),
                  $this->cli_str($update_row->update_id, 22, false)
                  )));
              }
            }
            if( $do_update ){
              $run_update_rows += $update_rows;
            }
          } while(
            count($update_lookup)
            && $update_rows = $this->getUpdateRows( array_shift($update_lookup) )
            );
          if( count($run_update_rows) ){
            foreach( $run_update_rows AS $update_row ){
              if( !$this->doInstallUpdate( $update_row ) ){
                return false;
              }
            }
            $this->out('Update processing complete');
          }
          if( isset($export_data) ){
            $this->out( $export_data );
          }
        }
        else {
          $this->out('No updates found');
        }

    }

    /**
     * [startOutputBuffer description]
     * @return [type] [description]
     */
    public function startOutputBuffer(){
      $this->__outputBuffer = array(
        'log'    => array(),
        'data'   => array()
        );
    }

    /**
     * [dumpOutputBuffer description]
     * @return [type] [description]
     */
    public function dumpOutputBuffer(){
      fwrite(STDOUT, json_encode($this->__outputBuffer) );
    }

    /**
     * [out description]
     * @param  string  $text [description]
     * @param  boolean $nl   [description]
     * @return [type]        [description]
     */
    public function out( $text = '', $nl = true ){
      if( isset($this->__outputBuffer) ){
        if( is_string($text) ){
          $this->__outputBuffer['log'][] = $text;
        }
        else {
          $this->__outputBuffer['data'] = array_merge( $this->__outputBuffer['data'], $text );
        }
        return $this;
      }
      fwrite(STDOUT, $text . ($nl ? "\n" : ''));
    }

    /**
     * [cli_str description]
     * @param  [type] $str [description]
     * @param  [type] $len [description]
     * @return [type]      [description]
     */
    function cli_str( $str, $len, $crop = true ){
      return str_pad(($crop ? substr($str, 0, $len - 1) : $str), $len, ' ', STR_PAD_RIGHT);
    }

    /**
     * [doEchoHelp description]
     * @return [type] [description]
     */
    public function doEchoHelp(){
      $version = _WordpressCliAutoUpdateVersion;
      echo <<<EOHELP
Wordpress CLI Autoupdate by Webuddha v{$version}
This script can be used to examine the extension of a local Joomla!
installation, fetch available updates, download and install update packages.

Operations
  -f, --fetch                 Run Fetch
  -u, --update                Run Update
  -l, --list                  List Updates
  -p, --purge                 Purge Updates
  -P, --package-archive URL   Install from Package Archive
  -B, --build-xml URL         Install from Package Build XML

Update Filters
  -i, --id ID                 Update ID
  -a, --all                   All Packages
  -V, --version VER           Version Filter
  -c, --core                  Joomla! Core Packages
  -e, --extension LOOKUP      Extension by ID/NAME
  -t, --type VAL              Type

Additional Flags
  -x, --export                Output in JSON format
  -h, --help                  Help
  -v, --verbose               Verbose

EOHELP;
    }

  }

/**
 * Inspector
 */
  if( !function_exists('inspect') ){
    function inspect(){
      print_r( func_get_args() );
    }
  }

/**
 * Upgrader Skin
 */

  class wbSiteManager_WP_Upgrader_Skin extends WP_Upgrader_Skin {

    public $_parent = null;

    public function __construct( $args = array(), $parent = null ){
      $this->_parent = $parent;
      parent::__construct( $args );
    }

    public function header() {
      if( $this->done_header )
        return;
      $this->done_header = true;
      $this->_parent->out( $this->options['title'] );
    }

    public function footer() {
      if( $this->done_footer )
        return;
      $this->done_footer = true;
    }

    public function feedback($string) {
      if ( isset( $this->upgrader->strings[$string] ) )
        $string = $this->upgrader->strings[$string];
      if ( strpos($string, '%') !== false ) {
        $args = func_get_args();
        $args = array_splice($args, 1);
        if ( $args ) {
          $args = array_map( 'strip_tags', $args );
          $args = array_map( 'esc_html', $args );
          $string = vsprintf($string, $args);
        }
      }
      if( empty($string) )
        return;
      $string = html_entity_decode(strip_tags( $string ));
      $this->_parent->out( ' - ' . $string );
    }

  }

/**
 * Plugin Updater Skin
 */

  class wbSiteManager_Plugin_Upgrader_Skin extends wbSiteManager_WP_Upgrader_Skin {
  }

/**
 * Theme Updater Skin
 */

  class wbSiteManager_Theme_Upgrader_Skin extends wbSiteManager_WP_Upgrader_Skin {
  }

/**
 * Trigger Execution
 */

  new WordpressCliAutoUpdate();
