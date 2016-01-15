<?php

// Plaintext Output
  header('Content-type: text/plain');

// Definitions
  define('DS', DIRECTORY_SEPARATOR);
  define('_WPEXEC', true);
  define('WP_ADMIN', true);
  define('WP_NETWORK_ADMIN', false);
  define('WP_USER_ADMIN', false);

// Base Path
  $base_path = implode(DS, array_slice(explode(DS, dirname(getcwd())), 0, -2));
  if( !file_exists($base_path . '/wp-load.php') && isset($_SERVER['SCRIPT_FILENAME']) ){
    $base_path = implode(DS, array_slice(explode(DS, $_SERVER['SCRIPT_FILENAME']), 0, -4));
  }

// Basic Initialization
  require_once $base_path . '/wp-load.php';
  require_once ABSPATH . 'wp-admin/includes/admin.php';

// Import Configuration
  if( is_readable('connect.config.php') ){
    include 'connect.config.php';
  }
  else {
    /**
     * Load Plugin Cofiguration from Wordpress
     */
  }

// Filter Required
  if( empty($ipFilter) && empty($userFilter) ){
    header('HTTP/1.0 403 Forbidden');
    die('HTTP/1.0 403 Forbidden');
  }

// Simple IP Filter
  if( !empty($ipFilter) ){
    require_once __DIR__ . '/classes/ipv4filter.class.php';
    $ipv4filter = new wbSiteManager_IPV4Filter($ipFilter);
    if( !$ipv4filter->check( $_SERVER['REMOTE_ADDR'] ) ){
      header('HTTP/1.0 401 Unauthorized ' . $_SERVER['REMOTE_ADDR']);
      die('HTTP/1.0 401 Unauthorized ' . $_SERVER['REMOTE_ADDR']);
    }
  }


// User Auth Filter
  if( !empty($userFilter) ){
    $headers = getallheaders();
    if( !empty($headers['Authorization']) ){
      $headerAuth = explode(' ', $headers['Authorization'], 2);
      $authCredentials = array_combine(array('username', 'password'), explode(':', base64_decode(end($headerAuth)), 2));
      if( is_array($userFilter) && !in_array($authCredentials['username'], $userFilter) ){
        header('HTTP/1.0 401 Unauthorized');
        die('HTTP/1.0 401 Unauthorized');
      }
      $authResult = wp_authenticate( $authCredentials['username'], $authCredentials['password'] );
      if( !$authResult || get_class($authResult) != 'WP_User' ){
        header('HTTP/1.0 401 Unauthorized');
        die('HTTP/1.0 401 Unauthorized');
      }
    }
    else {
      header('HTTP/1.0 400 Bad Request');
      die('HTTP/1.0 400 Bad Request');
    }
  }

// Prepare CLI Requirements
  $_SERVER['argv'] = array('autoupdate.php');
  $mQuery = array_merge($_GET, $_POST);
  foreach( $mQuery AS $k => $v ){
    if( strlen($v) ){
      $_SERVER['argv'][] = '--' . $k;
      $_SERVER['argv'][] = $v;
    }
    else
      $_SERVER['argv'][] = '-' . $k;
  }

// Include / Execute CLI Class
  require __DIR__ . '/autoupdate.php';
