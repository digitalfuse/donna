<?php

/*
* Add your own functions here. You can also copy some of the theme functions into this file. 
* Wordpress will use those functions instead of the original functions then.
*/

/* Add Arapey fonts */
add_filter( 'avf_google_heading_font',  'avia_add_heading_font');
function avia_add_heading_font($fonts)
{
$fonts['Arapey'] = 'Arapey';
return $fonts;
}

add_filter( 'avf_google_content_font',  'avia_add_content_font');
function avia_add_content_font($fonts)
{
$fonts['Arapey'] = 'Arapey';
return $fonts;
}


/* Disable portfolio items */
add_action( 'after_setup_theme', 'remove_portfolio' );
function remove_portfolio() {
    remove_action( 'init', 'portfolio_register' );
}

/* Disable post and comments menu items */
function fd_hide_menu() {
    remove_menu_page( 'edit.php' );	// disable post
	remove_menu_page( 'edit-comments.php' ); // disable comments
}
add_action( 'admin_menu', 'fd_hide_menu' );

/* alow svg file to upload on medial library */
function add_file_types_to_uploads($file_types){
	$new_filetypes = array();
	$new_filetypes['svg'] = 'image/svg+xml';
	$file_types = array_merge($file_types, $new_filetypes );
	return $file_types;
}
add_action('upload_mimes', 'add_file_types_to_uploads');

/* Register and enqueue scripts */
function fd_custom_scripts() {
    wp_enqueue_script(
        'custom-script',
        get_stylesheet_directory_uri() . '/js/custom.js',
        array( 'jquery' )
    );
}

add_action( 'wp_enqueue_scripts', 'fd_custom_scripts' ); 


// add class to child page
add_filter( 'body_class', 'fd_parent_body_class' );
function fd_parent_body_class( $classes ) {
    	if( is_page() ) { 
        	$parents = get_post_ancestors( get_the_ID() );
			$id = ($parents) ? $parents[count($parents)-1]: get_the_ID();
		if ($id) {
			$classes[] = 'top-parent-' . $id;
		} else {
			$classes[] = 'top-parent-' . get_the_ID();
		}
	}
 
	return $classes;
}



/* ***************************************************
 * ********* Edit Easy Slider 716, 717, 734 **********
 * ************************************************* */
if ( !class_exists( 'avia_slideshow' ) )
{
	class avia_slideshow
	{
		static  $slider = 0; //slider count for the current page
		
		/**
		 * base config set on initialization
		 * 
		 * @var array 
		 */
		protected $config;	 				
		
		/**
		 * attachment posts for the current slider
		 * 
		 * @var array 
		 */
		protected $slides;	 				
	
		/**
		 * number of slides
		 * 
		 * @var int 
		 */
		protected $slide_count;
		
		/**
		 *
		 * @var array 
		 */
		protected $id_array;
		
		
		/**
		 *
		 * @var boolean 
		 */
		protected $need_conditional_load;


		/**
		 * 
		 * @global array $_wp_additional_image_sizes
		 * @param array $config
		 */
		public function __construct( array $config )
		{
			$this->slides = array();
			$this->slide_count = 0;
			$this->id_array = array();
			$this->need_conditional_load = false;

			$this->config = array_merge( array(
									'size'				=> 'featured',
									'lightbox_size'		=> 'large',
									'animation'			=> 'slide',
									'conditional_play'	=> '',
									'ids'				=> '',
									'video_counter'		=> 0,
									'autoplay'			=> 'false',
									'bg_slider'			=> 'false',
									'slide_height'		=> '',
									'handle'			=> '',
									'interval'			=> 5,
									'class'				=> "",
									'css_id'			=> "",
									'scroll_down'		=> "",
									'control_layout'	=> '',
									'content'			=> array(),
									'custom_markup'		=> '',
									'perma_caption'		=> '',
									'autoplay_stopper'	=> '',
									'image_attachment'	=> '',
									'min_height'		=> '0px'
							), $config );

			$this->config = apply_filters( 'avf_slideshow_config', $this->config );

			//check how large the slider is and change the classname accordingly
			global $_wp_additional_image_sizes;
			$width = 1500;

			if(isset($_wp_additional_image_sizes[$this->config['size']]['width']))
			{
				$width  = $_wp_additional_image_sizes[$this->config['size']]['width'];
				$height = $_wp_additional_image_sizes[$this->config['size']]['height'];
				
				/**
				 * Avoid notices in case user manipulated image settings to 0
				 */
				$height = is_numeric( $height ) && $height > 0 ? $height : get_option( 'medium_size_h', 300 );
				$width = is_numeric( $width ) && $width > 0 ? $width : get_option( 'medium_size_w', $height );
				
				$this->config['default-height'] = (100/$width) * $height;
				
			}
			else if($size = get_option( $this->config['size'].'_size_w' ))
			{
				$width = $size;
			}

			if($width < 600)
			{
				$this->config['class'] .= " avia-small-width-slider";
			}

			if($width < 305)
			{
				$this->config['class'] .= " avia-super-small-width-slider";
			}

			//if we got subslides overwrite the id array
			if(!empty($config['content']))
			{
				$this->extract_subslides($config['content']);
			}
			
			if("aviaTBautoplay_stopper" == $this->config['autoplay_stopper'])
			{
				$this->config['autoplay_stopper'] = true;
			}
			else
			{
				$this->config['autoplay_stopper'] = false;
			}

			$this->set_slides($this->config['ids']);
		}
		
		/**
		 * @since 4.4
		 */
		public function __destruct() 
		{
			unset( $this->config );
			unset( $this->slides );
			unset( $this->id_array );
		}

		
		/**
		 * 
		 * @param string $ids
		 * @return void
		 */
		public function set_slides( $ids )
		{
			$ids = trim( $ids );
			
			if( empty( $ids ) && empty( $this->config['video_counter'] ) ) 
			{
				return;
			}
			
			/**
			 * video slides have no id and return empty string - avoid an unnecessary db query if only video slides
			 */
			$post_ids = explode( ',', $ids );
			$post_ids = array_unique( $post_ids );
			if( ( 1 == count( $post_ids ) ) && empty( $post_ids[0] ) )
			{
				$post_ids = '';
			}
			else
			{
				$post_ids = implode(',', $post_ids );
			}
			
			if( ! empty( $post_ids ) )
			{
				$this->slides = get_posts(array(
					'include' => $ids,
					'post_status' => 'inherit',
					'post_type' => 'attachment',
					'post_mime_type' => 'image',
					'order' => 'ASC',
					'orderby' => 'post__in')
					);
			}
			else
			{
				$this->slides = array();
			}

			//resort slides so the id of each slide matches the post id
			$new_slides = array();
			foreach($this->slides as $slide)
			{
				$new_slides[$slide->ID] = $slide;
			}

			$slideshow_data = array();
			$slideshow_data['slides'] = $new_slides;
			$slideshow_data['id_array'] = explode( ',', $this->config['ids'] );
			$slideshow_data['slide_count'] = count(array_filter($slideshow_data['id_array'])) + $this->config['video_counter'];
			
			$slideshow_data = apply_filters( 'avf_avia_builder_slideshow_filter', $slideshow_data, $this );
			
			$this->slides = $slideshow_data['slides'];
			$this->id_array = $slideshow_data['id_array'];
			$this->slide_count = $slideshow_data['slide_count'];
		}

		/**
		 * 
		 * @param string $size
		 */
		public function set_size( $size )
		{
			$this->config['size'] = $size;
		}

		
		/**
		 * 
		 * @param string $class
		 */
		public function set_extra_class( $class )
		{
			$this->config['class'] .= " ".$class;
		}


		/**
		 * 
		 * @return string
		 */
		public function html()
		{
			$html 		= "";
			$counter 	= 0;
			$style   	= "";
			$extraClass = "";
			$cond_play_class = '';
			avia_slideshow::$slider++;
			
			if($this->slide_count == 0) 
			{
				return $html;
			}
			
			if(!empty($this->config['scroll_down']))
			{	
				$html .= "<a href='#next-section' title='' class='scroll-down-link ".$this->config['control_layout']."' ". av_icon_string( 'scrolldown' ). "></a>";
				$extraClass .= "av-slider-scroll-down-active";
			}
			
			if(!empty($this->config['control_layout'])) $extraClass .= " ".$this->config['control_layout'];
			
			if( ! empty( $this->config['conditional_play'] ) && $this->need_conditional_load )
			{
				$cond_play_class = 'av-show-video-on-click';
			}
						
			$style = "";
			$data = AviaHelper::create_data_string($this->config);
			$slide_html = empty($this->subslides) ? $this->default_slide() : $this->advanced_slide();
			
			if(!empty($this->config['default-height']))
			{
				$style = "style='padding-bottom: ".$this->config['default-height']."%;'";
				$extraClass .= " av-default-height-applied";
			}
			
			
            $markup = avia_markup_helper(array('context' => 'image','echo'=>false, 'custom_markup'=>$this->config['custom_markup']));

			
			$html .= "<div {$data} class='avia-slideshow avia-slideshow-".avia_slideshow::$slider." {$extraClass} avia-slideshow-".$this->config['size']." ".$this->config['handle']." ".$this->config['class']." avia-".$this->config['animation']."-slider ' $markup>";
			
			$html .= "<ul class='avia-slideshow-inner {$cond_play_class}' {$style} >";

			
			$html .= $slide_html;
			$html .= "</ul>";
			
			if($this->slide_count > 1)
			{
				$html .= $this->slide_navigation_arrows();
//				$html .= $this->slide_navigation_dots();
			}		
			
			if(!empty($this->config['caption_override'])) $html .= $this->config['caption_override'];
			

			$html .= "</div>";
		
			
			return $html;
		}

		//function that renders the usual slides. use when we didnt use sub-shorcodes to define the images but ids
		protected function default_slide()
		{
			$html = "";
			$counter = 0;

            $markup_url = avia_markup_helper(array('context' => 'image_url','echo'=>false, 'custom_markup'=>$this->config['custom_markup']));

			foreach($this->id_array as $id)
			{
				if(isset($this->slides[$id]))
				{
					$slide = $this->slides[$id];

					$counter ++;
					$img 	 = wp_get_attachment_image_src($slide->ID, $this->config['size']);
					$link	 = wp_get_attachment_image_src($slide->ID, $this->config['lightbox_size']);
					$caption = trim($slide->post_excerpt) ? '<div class="avia-caption capt-bottom capt-left"><div class="avia-inner-caption">'.wptexturize($slide->post_excerpt)."</div></div>": "";

                    $imgalt = get_post_meta($slide->ID, '_wp_attachment_image_alt', true);
                    $imgalt = !empty($imgalt) ? esc_attr($imgalt) : '';
                    $imgtitle = trim($slide->post_title) ? esc_attr($slide->post_title) : "";
                  	if($imgtitle == "-") $imgtitle = "";
                    $imgdescription = trim($slide->post_content) ? esc_attr($slide->post_content) : "";
					

					$tags = apply_filters('avf_slideshow_link_tags', array("a href='".$link[0]."' title='".$imgdescription."'",'a')); // can be filtered and for example be replaced by array('div','div')
					
					$html .= "<li class='slide-{$counter} slide-id-".$slide->ID."'>";
					$html .= "<".$tags[0]." >{$caption}<img src='".$img[0]."' width='".$img[1]."' height='".$img[2]."' title='".$imgtitle."' alt='".$imgalt."' $markup_url  /></ ".$tags[1]." >";
					$html .= "</li>";
				}
				else
				{
					$this->slide_count --;
				}
			}

			return $html;
		}

		//function that renders the slides. use when we did use sub-shorcodes to define the images
		protected function advanced_slide()
		{
			$html = "";
			$counter = 0;
			$this->ie8_fallback = "";

			foreach( $this->id_array as $key => $id )
			{
				$meta = array_merge( array( 'content'		=> $this->subslides[$key]['content'],
											'title'			=>'',
											'link_apply'	=>'',
											//direct link from image
											'link'			=>'',
											'link_target'	=>'',
											//button link 1
											'button_label'	=>'',
											'button_color'	=>'light',
											'link1'			=>'',
											'link_target1'	=>'',											
											//button link 2
											'button_label2'	=>'',
											'button_color2'	=>'light',
											'link2'			=>'',
											'link_target2'	=>'',
											
											'position'		=>'center center',
											'caption_pos'	=>'capt-bottom capt-left',
											'video_cover'	=>'',
											'video_controls'=>'',
											'video_mute'	=>'',
											'video_loop'	=>'',
											'video_format'	=>'',
											'video_autoplay'=>'',
											'video_ratio'	=>'16:9',
											'video_mobile_disabled'=>'',
											'video_mobile'	=>'mobile-fallback-image',
											'mobile_image'	=> '',
											'fallback_link' => '',
											'slide_type'	=>'',
											'custom_markup' => '',
											'custom_title_size' => '',
											'custom_content_size' => '',
											'font_color'	=>'',
											'custom_title' 	=> '',
											'custom_content' => '',
											'overlay_enable' => '',
			    							'overlay_opacity' => '',
			    							'overlay_color' => '',
			    							'overlay_pattern' => '',
			    							'overlay_custom_pattern' => '',
											'preload' => $this->need_conditional_load ? 'none' : ''

										), $this->subslides[$key]['attr'] );
				
				//return $av_font_classes, $av_title_font_classes and $av_display_classes 
				extract(AviaHelper::av_mobile_sizes($this->subslides[$key]['attr'])); 
				extract($meta);
				
				if(isset($this->slides[$id]) || $slide_type == 'video')
				{
					$img			= array('');
					$slide			= "";
					$attachment_id	= isset($this->slides[$id]) ? $id : false;
					$link			= AviaHelper::get_url($link, $attachment_id); 
					$extra_class 	= "";
					$linkdescription= "";
					$linkalt 		= "";
					$this->service  = false;
					$slider_data	= "";
					$stretch_height	= false;
					$final_ratio	= "";
					$viewport		= 16/9;
					
					$fallback_img_style = "";
					$fallback_img_class = "";
					
					
            		$markup_url = avia_markup_helper(array('context' => 'image_url','echo'=>false, 'id'=>$attachment_id, 'custom_markup'=>$custom_markup));
					
					if($slide_type == 'video')
					{
						$this->service    = avia_slideshow_video_helper::which_video_service($video);
						$video 			  = avia_slideshow_video_helper::set_video_slide($video, $this->service, $meta , $this->config); 
						$video_class	  = !empty( $video_controls ) ? " av-hide-video-controls" : "";
						$video_class	 .= !empty( $video_mute ) ? " av-mute-video" : "";
						$video_class	 .= !empty( $video_loop ) ? " av-loop-video" : "";
						$video_class	 .= !empty( $video_mobile ) ? " av-".$video_mobile : "";
			
						$extra_class 	.= " av-video-slide ".$video_cover." av-video-service-".$this->service." ".$video_class;
						$slider_data 	.= " data-controls='{$video_controls}' data-mute='{$video_mute}' data-loop='{$video_loop}' data-disable-autoplay='{$video_autoplay}' ";	
						
						if( $mobile_image )
						{
							$fallback_img 		= wp_get_attachment_image_src( $mobile_image, $this->config['size'] );
							$fallback_img_style = "style='background-image:url(\"{$fallback_img[0]}\");'";
							
							$slider_data .= " data-mobile-img='".$fallback_img[0]."'";
							
							if($fallback_link)
							{
								$slider_data .= " data-fallback-link='".$fallback_link."'";
							}
						}
						
						//if we dont use a fullscreen slider pass the video ratio to the slider
						if($this->config['bg_slider'] != "true")
						{
							global $avia_config;
							//if we use the small slideshow only allow the "full" $video_format
							if($this->config['handle'] == 'av_slideshow') $video_format = "full";
							
							
							//calculate the viewport ratio
							if(!empty($avia_config['imgSize'][$this->config['size']]))
							{
								$viewport = $avia_config['imgSize'][$this->config['size']]['width'] / $avia_config['imgSize'][$this->config['size']]['height'];
							}
							
							
							//calculate the ratio when passed as a string (eg: 16:9, 4:3). fallback is 16:9
							$video_ratio = explode(':',trim($video_ratio));
							if(empty($video_ratio[0])) $video_ratio[0] = 16;
							if(empty($video_ratio[1])) $video_ratio[1] = 9;
							$final_ratio = ((int) $video_ratio[0] / (int) $video_ratio[1]);							
							
							switch($video_format)
							{
								case "": 
									$final_ratio = $viewport; 
								break;
								case "stretch": 
									$final_ratio 	 = $viewport; 
									$stretch_height  = ceil( $viewport / ($video_ratio[0]/$video_ratio[1]) * 100 );
									$stretch_pos 	 = (($stretch_height - 100) / 2) * -1;
									$slider_data 	.= " data-video-height='{$stretch_height}'";
									$slider_data 	.= " data-video-toppos='{$stretch_pos}'";
									$extra_class 	.= " av-video-stretch";
								break;
								case "full": 
									// do nothing and apply the entered ratio
								break;
							}
							
							$slider_data .= " data-video-ratio='{$final_ratio}'";	
						}
						
					}
					else //img slide
					{
						$slide 			 = $this->slides[$id];
						$linktitle 		 = trim($slide->post_title) ? esc_attr($slide->post_title) : "";
						if($linktitle == "-") $linktitle = "";
                    	$linkdescription = (trim($slide->post_content) && empty($link)) ? "title='".esc_attr($slide->post_content)."'" : "";
                    	$linkalt 		 = get_post_meta($slide->ID, '_wp_attachment_image_alt', true);
                    	$linkalt 		 = !empty($linkalt) ? esc_attr($linkalt) : '';
						$img   			 = wp_get_attachment_image_src($slide->ID, $this->config['size']);
						$video			 = "";
					}
					
					if($this->slide_count === 1) $extra_class .= " av-single-slide";
					
					$blank = (strpos($link_target, '_blank') !== false || $link_target == 'yes') ? ' target="_blank" ' : "";
					$blank .= strpos($link_target, 'nofollow') !== false ? ' rel="nofollow" ' : "";
					$tags 			= (!empty($link) && $link_apply == 'image') ? array("a href='{$link}'{$blank}",'a') : array('div','div');
					$caption  		= "";
					$button_html 	= "";
					$counter ++;
					$button_count = "";
					if(strpos($link_apply, 'button-two') !== false){$button_count = "avia-multi-slideshow-button";}
					
					
					//if we got a CTA button apply the link to the button istead of the slide
					if(strpos($link_apply, 'button') !== false)
					{
						$button_html .= $this->slideshow_cta_button($link1, $link_target1, $button_color, $button_label, $button_count);
						$tags = array('div','div');
					}
					
					if(strpos($link_apply, 'button-two') !== false)
					{
						$button_count .= " avia-slideshow-button-2";
						$button_html .= $this->slideshow_cta_button($link2, $link_target2, $button_color2, $button_label2, $button_count);
					}
					
					
					//custom caption styles
					
					$title_styling 		 = !empty($custom_title_size) ? "font-size:{$custom_title_size}px; " : "";
					$content_styling 	 = !empty($custom_content_size) ? "font-size:{$custom_content_size}px; " : "";
					$content_class		 = "";
					
					if($font_color == "custom")
					{
						$title_styling 		.= !empty($custom_title) ? "color:{$custom_title}; " : "";
						$content_styling 	.= !empty($custom_content) ? "color:{$custom_content}; " : "";
					}
					
					if($title_styling) $title_styling = " style='{$title_styling}'" ;
					if($content_styling) 
					{
						$content_styling = " style='{$content_styling}'" ;
						$content_class	 = "av_inherit_color";
					}
					
					//check if we got a caption
                    $markup_description = avia_markup_helper(array('context' => 'description','echo'=>false, 'id'=>$attachment_id, 'custom_markup'=>$custom_markup));
                    $markup_name = avia_markup_helper(array('context' => 'name','echo'=>false, 'id'=>$attachment_id, 'custom_markup'=>$custom_markup));
					if(trim($title) != "")   $title 	= "<h2 {$title_styling} class='avia-caption-title {$av_title_font_classes}' $markup_name>".trim(apply_filters('avf_slideshow_title', $title))."</h2>";
					
					if(is_array($content)) $content = implode(' ',$content); //temp fix for trim() expects string warning until I can actually reproduce the problem
					if(trim($content) != "") $content 	= "<div class='avia-caption-content {$av_font_classes} {$content_class}' {$markup_description} {$content_styling}>".ShortcodeHelper::avia_apply_autop(ShortcodeHelper::avia_remove_autop(trim($content)))."</div>";

					if(trim($title.$content.$button_html) != "")
					{
						if(trim($title) != "" && trim($button_html) != "" && trim($content) == "") $content = "<br/>";

						if($this->config['handle'] == 'av_slideshow_full' || $this->config['handle'] == 'av_fullscreen')
						{
							$caption .= '<div class = "caption_fullwidth av-slideshow-caption '.$caption_pos.'">';
							$caption .= 	'<div class = "container caption_container">';
							$caption .= 			'<div class = "slideshow_caption">';
							$caption .= 				'<div class = "slideshow_inner_caption">';
							$caption .= 					'<div class = "slideshow_align_caption">';
							$caption .=						$title;
							$caption .=						$content;
							$caption .=						$button_html;
							$caption .= 					'</div>';
							$caption .= 				'</div>';
							$caption .= 			'</div>';
							$caption .= 	'</div>';
							$caption .= '</div>';
						}
						else
						{
							$caption = '<div class="avia-caption av-slideshow-caption"><div class="avia-inner-caption">'.$title.$content."</div></div>";
						}
					}

					if(!empty($this->config['perma_caption']) && empty($this->config['caption_override']))
					{
						$this->config['caption_override'] = $caption;
					}
                   	
                   	if(!empty($this->config['caption_override'])) $caption = "";
                    
					
					if(!empty($img[0]))
					{
						$slider_data .= $this->config['bg_slider'] == "true" ? "style='background-position:{$position};' data-img-url='".$img[0]."'" : "";
						
						if($slider_data )
						{
							if(empty($this->ie8_fallback))
							{
						    	$this->ie8_fallback .= "<!--[if lte IE 8]>";
								$this->ie8_fallback .= "<style type='text/css'>";
							}
							$this->ie8_fallback .= "\n #{$this->config['css_id']} .slide-{$counter}{";
							$this->ie8_fallback .= "\n -ms-filter: \"progid:DXImageTransform.Microsoft.AlphaImageLoader(src='{$img[0]}', sizingMethod='scale')\"; ";
						    $this->ie8_fallback .= "\n filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='{$img[0]}', sizingMethod='scale'); ";
							$this->ie8_fallback .= "\n } \n";
						}
					}
					
					
					
					
					// $img[0] = 'https://kriesi.at/themes/enfold-photography/files/2014/08/darkened_girl.jpg';


					$html .= "<li {$slider_data} class='{$extra_class} slide-{$counter} fd-easy-slider' >";
					$html .= "<".$tags[0]." data-rel='slideshow-".avia_slideshow::$slider."' class='avia-slide-wrap {$fallback_img_class}' {$fallback_img_style} {$linkdescription} >{$caption}";
					if($this->config['bg_slider'] != "true" && empty($video))
					{
						$img_style = "";
						if(!empty($this->config['min_height']) && $this->config['min_height'] != "0px")
						{
							$percent = 100 / (100/$img[2] * (int) $this->config['min_height'] );
							$this->config['min_width'] = ceil(($img[1] / $percent)) . "px";
							
							$img_style .= AviaHelper::style_string($this->config, 'min_height', 'min-height');
							$img_style .= AviaHelper::style_string($this->config, 'min_width', 'min-width');
							$img_style  = AviaHelper::style_string($img_style);
						}
				
						
						$html .= "<img src='".$img[0]."' width='".$img[1]."' height='".$img[2]."' title='".$linktitle."' alt='".$linkalt."' $markup_url $img_style />";
					}
					$html .= $video;
					$html .= $this->create_overlay($meta);
					$html .= $this->create_click_to_play_overlay();

// slide counts
					$html  .= "<div class='avia-slideshow-dots avia-slideshow-controls'>";
					//$html .= "<a href='#{$counter}' class='goto-slide new' >{$counter}</a>";
					//$html .= "<a href='#' class='goto-slide-last' >" . "&nbsp;/&nbsp;" . "$this->slide_count</a>";
					$html .= "<span class='goto-slide new' >{$counter}</span>";
					$html .= "<span class='goto-slide-last' >" . "&nbsp;/&nbsp;" . "$this->slide_count</span>";
					$html .= "</div>";
					
					$html .= "</".$tags[1].">";
					$html .= "</li>";
					
					if( $counter === 1 )
					{
						if(!empty($img[1]) && !empty($img[2]))
						{
							$this->config['default-height'] = (100/$img[1]) * $img[2];
						}
					}
					
			}
			else
			{
				$this->slide_count --;
			}
		}

			if(!empty($this->ie8_fallback))
			{
				$this->ie8_fallback .= "</style> <![endif]-->";
				add_action('wp_footer', array($this, 'add_ie8_fallback_to_footer'));
			}

			return $html;
		}

		public function add_ie8_fallback_to_footer()
		{
			// echo $this->ie8_fallback;
		}
		
		protected function slideshow_cta_button($link, $link_target, $button_color, $button_label, $button_count)
		{
			$button_html = "";
			$blank = (strpos($link_target, '_blank') !== false || $link_target == 'yes') ? ' target="_blank" ' : "";
			$blank .= strpos($link_target, 'nofollow') !== false ? ' rel="nofollow" ' : "";
			
			$link = AviaHelper::get_url($link); 
			
			$button_html .= "<a href='{$link}' {$blank} class='avia-slideshow-button avia-button avia-color-{$button_color} {$button_count}' data-duration='800' data-easing='easeInOutQuad'>";
			$button_html .= $button_label;
			$button_html .= "</a>";
			return $button_html;
		}


		protected function slide_navigation_arrows()
		{
			global $avia_config;
		
			$html  = "";
			$html .= "<div class='avia-slideshow-arrows avia-slideshow-controls'>";
			$html .= 	"<a id='fd-prev' href='#prev' class='prev-slide' ".av_icon_string('prev_big').">".__('','avia_framework' )."</a>";
			$html .= 	"<a id='fd-next' href='#next' class='next-slide' ".av_icon_string('next_big').">".__('','avia_framework' )."</a>";
			$html .= "</div>";

			return $html;
		}
/*
		protected function slide_navigation_dots()
		{
			$html   = "";
			$html  .= "<div class='avia-slideshow-dots avia-slideshow-controls'>";
			$active = "active";

			for($i = 1; $i <= $this->slide_count; $i++)
			{
				$html .= "<a href='#{$i}' class='goto-slide {$active}' >{$i}</a>";
				$active = "";
			}
			$html .= "<a href='#' class='goto-slide-last' >" . "/&nbsp;" . "$this->slide_count</a>";
			$html .= "</div>";

			return $html;
		}
*/
		/**
		 * 
		 * @param array $slide_array
		 */
		protected function extract_subslides( array $slide_array )
		{
			$this->config['ids']= array();
			$this->subslides 	= array();
		
			foreach($slide_array as $key => $slide)
			{
				$this->subslides[$key] = $slide;
				$this->config['ids'][] = $slide['attr']['id'];
			
				if( empty($slide['attr']['id']) && ! empty( $slide['attr']['video']) && $slide['attr']['slide_type'] === 'video')
				{
					$this->config['video_counter'] ++ ;
					if( avia_slideshow_video_helper::is_extern_service( $slide['attr']['video'] ) )
					{
						$this->need_conditional_load = true;
					}
					else
					{
						if( ! $this->need_conditional_load )
						{
							/**
							 * Allow to change default behaviour to lazy load all video files
							 * 
							 * @since 4.4
							 */
							$this->need_conditional_load = apply_filters( 'avf_video_slide_conditional_load_html5', true, $slide_array, $this );
						}
					}
				}
			}
			
			$this->config['ids'] = implode(',', $this->config['ids'] );
			unset($this->config['content']);
		}
		
		/**
		 * 
		 * @param array $meta
		 * @return string
		 */
		protected function create_overlay( array $meta)
		{
			extract($meta);
			
			/*check/create overlay*/
			$overlay = "";
			if(!empty($overlay_enable))
			{
				$overlay_src = "";
				$overlay = "opacity: {$overlay_opacity}; ";
				if(!empty($overlay_color)) $overlay .= "background-color: {$overlay_color}; ";
				if(!empty($overlay_pattern))
				{
					if($overlay_pattern == "custom")
					{
						$overlay_src = $overlay_custom_pattern;
					}
					else
					{
						$overlay_src = str_replace('{{AVIA_BASE_URL}}', AVIA_BASE_URL, $overlay_pattern);
					}
				}
				
				if(!empty($overlay_src)) $overlay .= "background-image: url({$overlay_src}); background-repeat: repeat;";
				$overlay = "<div class='av-section-color-overlay' style='{$overlay}'></div>";
			}
			
			return $overlay;
		}
		
		/**
		 * Returns an overlay div if we need late loading of videos
		 * 
		 * @since 4.4
		 * @return string
		 */
		protected function create_click_to_play_overlay()
		{
			if( ! $this->need_conditional_load )
			{
				return '';
			}
			
			
			$overlay =	"<div class='av-click-to-play-overlay'>";
			$overlay .= '<div class="avia_playpause_icon">';
			$overlay .=	'</div>';
			$overlay .=	'</div>';
			
			return $overlay;
		}
		
	}
}
