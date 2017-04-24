<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="main">
 *
 * @package WordPress
 * @subpackage Twenty_Twelve
 * @since Twenty Twelve 1.0
 */
?><!DOCTYPE html>
<!--[if IE 7]>
<html class="ie ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html class="ie ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 7) | !(IE 8)  ]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width" />
<title><?php wp_title( '|', true, 'right' ); ?></title>
<link rel="profile" href="http://gmpg.org/xfn/11" />
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
<meta name="p:domain_verify" content="b4685b2c247204f31089525ef5c05dd4"/>



<!----- bootstrap css start ----->
<link href="<?php echo get_template_directory_uri(); ?>/css/bootstrap.css" rel="stylesheet" type="text/css" />
<link href="<?php echo get_template_directory_uri(); ?>/css/bootstrap-responsive.css" rel="stylesheet" type="text/css" />
<!----- bootstrap css end ----->
<script type="text/javascript" src="<?php echo get_template_directory_uri(); ?>/js/respond.min.js"></script>


<!----- Google font ----->
<link href='http://fonts.googleapis.com/css?family=Roboto:400,900,900italic,700italic,700,500italic,500,400italic,300,300italic' rel='stylesheet' type='text/css'>
<!--font-family: 'Roboto', sans-serif;-->


<?php wp_head(); ?>

</head>

<body <?php body_class(); ?>>
    
    <!--outer toppart start-->
	<div class="row-fluid topPart">
    	<div class="container">
        	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home" class="span3 logo"><img src="<?php echo get_template_directory_uri(); ?>/images/logo.png" alt="" />
            	<h2 class="site-description"><?php bloginfo( 'description' ); ?></h2>
            </a>
            
            <!--top right side start-->
            <div class="span8 topright_box">
            	<div class="row-fluid rightTop">
                	<?php dynamic_sidebar( 'sidebar-11' ); ?>
                </div>
                
                <div class="topLogo"><?php dynamic_sidebar( 'toplogo' ); ?></div>
                
                <!--manu start-->
                <div class="manu">
                	<nav id="site-navigation" class="main-navigation" role="navigation">
                        <h3 class="menu-toggle"><?php _e( 'Menu', 'twentytwelve' ); ?></h3>
                        <a class="assistive-text" href="#content" title="<?php esc_attr_e( 'Skip to content', 'twentytwelve' ); ?>"><?php _e( 'Skip to content', 'twentytwelve' ); ?></a>
                        <?php wp_nav_menu( array( 'theme_location' => 'primary', 'menu_class' => 'nav-menu' ) ); ?>
                    </nav>
                </div>
                <!--manu end-->
                
                <br clear="all" >
                <div class="hometopLogo"><?php dynamic_sidebar( 'hometoplogo' ); ?></div>
                
            </div>
            <!--top right side end-->
            
       </div>
    </div>
    <!--outer toppart end-->

	
    <!--Content area start-->
	<div class="row-fluid">