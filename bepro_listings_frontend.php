<?php
/*
	This file is part of BePro Listings.

    BePro Listings is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    BePro Listings is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with BePro Listings.  If not, see <http://www.gnu.org/licenses/>.
*/	

	//Create map, used by shortcode and widget
	function generate_map($atts = array(), $raw_results = array()){
		global $wpdb;
		
		$echo_this = (!empty($atts))? true:false;
		extract(shortcode_atts(array(
			  'pop_up' => $wpdb->escape($_POST["pop_up"]),
			  'size' => $wpdb->escape($_POST["size"]),
			  'show_paging' => $wpdb->escape($_POST["show_paging"])
		 ), $atts));
		 
		//Setup data
		$data = get_option("bepro_listings");
		$num_results = $data["num_listings"]; 
		$size = empty($size)? 1:$size;
		
		
		//Get Listing Results
		$findings = process_listings_results($show_paging, $num_results);				
		$raw_results = $findings[0];
		
		//Setup Listing Markers
		$counter = 0;
		foreach($raw_results as $result){
			if (!empty($result->lat) && !empty($result->lon)){
				$map_cities .= apply_filters("bepro_listings_map_marker","", $result, $counter);
				$currlat = $result->lat;
				$currlon = $result->lon;
				if($pop_up){
					$map_cities .= bepro_listings_detailed_infowindow($result, $counter);
				}else{
					$map_cities .= bepro_listings_simple_infowindow($result, $counter);
				}
			}
			$counter++;
		}
		$declare_for_map = apply_filters("bepro_listings_declare_for_map", '');
		//javascript initialization of the map
		$map = "<script type='text/javascript'>
			jQuery(document).ready(function(){
				var currentlat;
				var currentlon;
				markers = new Array();
				positions = new Array();
				var currentlat = $currlat;
				var currentlon = $currlon;
				var openwindow = false;
				var latlng = new google.maps.LatLng(currentlat, currentlon);
				icon_1 = new google.maps.MarkerImage('".plugins_url("images/icons/icon_1.png", __FILE__)."');
				var myOptions = {
					zoom:10,
					center: latlng,
					mapTypeId: google.maps.MapTypeId.ROADMAP
				}
				$declare_for_map
				map = new google.maps.Map(document.getElementById('map'), myOptions);
				
				$map_cities
				//cluster markers
				if(markers.length > 1){
					var markerCluster = new MarkerClusterer(map, markers);
					//makes sure map view fits all markers
					 latlngbounds = new google.maps.LatLngBounds();
					for ( var i = 0; i < positions.length; i++ ) {
						latlngbounds.extend( positions[ i ] );
					}
					map.fitBounds( latlngbounds );
				}
			});
		</script>
		<div id='map' class='result_map_$size'></div>";
		if($echo_this){
			echo $map;
		}else{	
			return $map;
		}
	}
	
	function bepro_listings_vars_for_map($vars){
		return $vars;
	}
	
	function bepro_listings_simple_infowindow($result, $counter){
		$permalink = get_permalink( $result->post_id );
		return 'var infowindow_'.$counter.' = new google.maps.InfoWindow( { content: "<div class=\"marker_content\"><span class=\"marker_detais\">'.$result->post_title.'</span></div>", size: new google.maps.Size(50,50)});
				  google.maps.event.addListener(marker_'.$counter.', "mouseover", function() {
					if(openwindow){
						eval(openwindow).close();
					}
					infowindow_'.$counter.'.open(map,marker_'.$counter.');
					openwindow = infowindow_'.$counter.';
				  });
				  google.maps.event.addListener(marker_'.$counter.', "click", function() {
					window.location.href = "'.$permalink.'";
				  });
			';
	}
	
	function bepro_listings_detailed_infowindow($result, $counter){
		$thumbnail = get_the_post_thumbnail($result->post_id, 'thumbnail'); 
		$default_img = (!empty($thumbnail))? $thumbnail:'<img src="'.$data["default_image"].'"/>';

		return "var infowindow_".$counter." = new google.maps.InfoWindow( { content: '<div class=\"marker_content\"><span class=\"marker_title\">".addslashes(substr($result->post_title,0,18))."</span><span class=\"marker_img\">".$default_img."</span><span class=\"marker_detais\">".$result->address_line1.", ".$result->city.", ".$result->state.", ".$result->country."</span><span class=\"marker_links\"><a href=\"http://".urlencode($result->website)."\">Visit Website</a><br /><a href=\"".get_permalink($result->post_id)."\">View Listing</a></span></div>', size: new google.maps.Size(50,50)});
				  google.maps.event.addListener(marker_".$counter.", \"click\", function() {
					if(openwindow){
						eval(openwindow).close();
					}
					infowindow_".$counter.".open(map,marker_".$counter.");
					openwindow = infowindow_".$counter.";
				  });
			";
	}
	
	function bepro_listings_generate_map_marker($var, $result, $counter){
		return 'position = new google.maps.LatLng('.$result->lat.','.$result->lon.');
					var marker_'.$counter.' = new google.maps.Marker({
						position: position,
						icon:icon_1,
						map: map,
						clickable: true,
						title: "'.$result->item_name.'",
					});
					
					markers.push(marker_'.$counter.');
					positions.push(position);	
				';
	}
	
	//Show categories Called from shortcode
	function display_listing_categories($atts = array()){
		global $wpdb;
		$no_img = plugins_url("images/no_img.jpg", __FILE__ );
		extract(shortcode_atts(array(
			  'url_input' => $wpdb->escape($_POST["url"]),
			  'ctype' => $wpdb->escape($_POST["ctype"]),
			  'cat' => $wpdb->escape($_POST["cat"])
		 ), $atts));
		
		
		$cat_heading = (!empty($_GET["type"]) && is_numeric($_GET["type"]))? "Sub Categories":"Categories";
		$parent = (!empty($cat) && is_numeric($cat))? $cat:0;
		$parent = (!empty($_GET["type"]) && is_numeric($_GET["type"]))? $_GET["type"]:0;  
		
		$categories = get_terms( 'bepro_listing_types', 'orderby=count&hide_empty=0&parent='.$parent );
		
		$cat_list = "<h3>".__($cat_heading,"bepro_listings")."</h3><div class='cat_lists'>";
		
		if($categories && (count($categories) > 0)){
			foreach($categories as $cat){
				$cat_list.= bepro_cat_templates($cat, $url_input, $ctype);
			}
		}else{
			$cat_list .= "<div class='cat_list_no_item'>No ".$cat_heading." Created.</div>";
		}
		$cat_list.= "</div>";
		
		if($echo_this){
			echo $cat_list;
		}else{	
			return $cat_list;
		}
	}
	
	function bepro_cat_templates($cat, $url_input, $template = 0){
		$url = $url_input."?filter_search=1&type=".$cat->term_id;
		$cat_list = "";
		if($template == 1){
			$thumb_id = get_bepro_listings_term_meta( $cat->term_id, "thumbnail_id");
			$img = empty($thumb_id)? $no_img:wp_get_attachment_url( $thumb_id );
			$cat_list .= "<div class='cat_list_item'>
			<div class='cat_img'><a href='".$url."'><img src='".$img."' /></a></div>
			<div class='cat_title'><a href='".$url."'>".$cat->name."&nbsp;(".$cat->count.")</a></div>
			</div>
			";
		}else if($template == 2){
			$thumb_id = get_bepro_listings_term_meta( $cat->term_id, "thumbnail_id");
			$img = empty($thumb_id)? $no_img:wp_get_attachment_url( $thumb_id );
			$cat_list .= "<div class='cat_list_item'>
			<div class='cat_img'><a href='".$url."'><img src='".$img."' /></a></div>
			<div class='cat_title'><a href='".$url."'>".$cat->name."&nbsp;(".$cat->count.")</a></div>
			<div class='cat_desc'>".$cat->description."</div>
			</div>
			";
		}else if($template == 3){
			$sub_categories = get_terms( 'bepro_listing_types', 'orderby=count&hide_empty=0&parent='.$cat->term_id );
			$cat_list = "<div class='cat_list_item'>
			<div class='cat_title cat_head'><a href='".$url."'>".$cat->name."&nbsp;(".$cat->count.")</a></div>
			<div class='cat_desc'>".$cat->description."</div>
			";
			if(!empty($sub_categories)){
				$cat_list .="<ul>";
				foreach($sub_categories as $sub_cat){
					$sub_url = $url_input."?filter_search=1&type=".$sub_cat->term_id;
					$cat_list .= "<li><a href='".$sub_url."'>".$sub_cat->name."&nbsp;(".$sub_cat->count.")</a></li>";
				}
				$cat_list .="</ul>";
			}
			$cat_list .= "</div>";
		}else{
			$sub_categories = get_terms( 'bepro_listing_types', 'orderby=count&hide_empty=0&parent='.$cat->term_id );
			$cat_list = "<div class='cat_list_item'>
			<div class='cat_title cat_head'><a href='".$url."'>".$cat->name."&nbsp;(".$cat->count.")</a></div>";
			if(!empty($sub_categories)){
				$cat_list .="<ul>";
				foreach($sub_categories as $sub_cat){
					$sub_url = $base_url."?filter_search=1&type=".$sub_cat->term_id;
					$cat_list .= "<li><a href='".$sub_url."'>".$sub_cat->name."&nbsp;(".$sub_cat->count.")</a></li>";
				}
				$cat_list .="</ul>";
			}
			$cat_list .= "</div>";
		}
		
		return $cat_list;
	}
	
	
	//Show listings Called from shortcode
	function display_listings($atts = array(), $raw_results = array(), $enlarge_map = 0){
		global $wpdb;
		extract(shortcode_atts(array(
			  'shorten' => $wpdb->escape($_POST["shorten"]),
			  'type' => $wpdb->escape($_POST["type"]),
			  'show_paging' => $wpdb->escape($_POST["show_paging"])
		 ), $atts));
		 
		$data = get_option("bepro_listings");
		$num_results = $data["num_listings"]; 
		$type = empty($type)? 1:$type;
		$echo_this = (empty($raw_results))? false:true;
		$findings = process_listings_results($show_paging, $num_results);				
		$raw_results = $findings[0];				
			
		//Create the GUI layout for the listings
		if(empty($raw_results) || is_null($raw_results)){
			$results = "<p>your criteria returned no results.</p>";
		}else{
			//item listing template
			$list_templates = isset($data['bepro_listings_list_template_'.$type])? $data['bepro_listings_list_template_'.$type]: $data['bepro_listings_list_template_1'];
			foreach($list_templates as $key => $val){
				if($key == "style")
					$results .="<link href='".$val."' rel='stylesheet' />";
				else
					add_action($key, $val);
			}
			
			//loop over listing template file
			foreach($raw_results as $result){
				$results .= basic_listing_layout($result, $type);
			}
		}
		
		if($show_paging == 1){
			$pages = 0;
			$pages = $findings[1];
			$counter = 1;
			$paging = "<div style='clear:both'><br /></div><div class='paging'>Pages: ";
			while($pages != 0){
				$paging .= "<a href='?lpage=".$counter."'>".$counter."</a>";
				$pages--;
				$counter++;
			}
			$paging .= "</div>";
			if($counter > 1) $results.= $paging; // if no pages then dont show this
		}
		if($echo_this){
			echo $results;
		}else{	
			return $results;
		}
	}
	
	//process paging and listings
	function process_listings_results($show_paging = false, $num_results = false){
		if(!empty($_REQUEST["filter_search"]))$returncaluse = Bepro_listings::listitems(array());
		$filter_cat = (!empty($_REQUEST["type"]))? true:false;

		
		//Handle Paging selection calculations and process listings
		if($show_paging == 1){
			$page = (empty($_GET["lpage"]))? 1 : $_GET["lpage"];
			$page = ($page - 1) * $num_results;
			$limit_clause = " ORDER BY posts.post_title ASC LIMIT $page , $num_results";
			$resvs = bepro_get_listings($returncaluse);
			$pages = ceil(count($resvs)/$num_results);
			$findings[1] = $pages;
			$raw_results = bepro_get_listings($returncaluse, $filter_cat, $limit_clause);
		}else{
			$raw_results = bepro_get_listings($returncaluse, $filter_cat);
		}
		$findings[0] = $raw_results;
		return $findings;
	}
	
	function basic_listing_layout($result, $type = 1){
		//allow other features to tie in
		$listing_template_file = plugin_dir_path( __FILE__ ).'/templates/listings/generic.php';
		$get_listing_template = apply_filters("bepro_listings_list_template", $listing_template_file);
		if($get_listing_template != -1)$listing_template_file = $get_listing_template;
		
		ob_start();
		include($listing_template_file);
		$results = ob_get_contents();
		ob_end_clean();	
			
		return $results;			
	}
	
	function bepro_listings_list_title_template($bp_listing){
		echo "<div class='result_name'>".substr($bp_listing->post_title,0, 18).((strlen($bp_listing->post_title) > 18)? "...":"")."</div>";
	}
	function bepro_listings_list_category_template($bp_listing){
		echo '<span class="result_type">'.get_the_term_list($bp_listing->post_id, 'bepro_listing_types', '', ', ','').'</span>';
	}
	function bepro_listings_list_image_template($bp_listing){
		$permalink = get_permalink( $bp_listing->post_id );
		$data = get_option("bepro_listings");
		$thumbnail = get_the_post_thumbnail($bp_listing->post_id, 'thumbnail'); 
		$thumbnail_check = apply_filters("bepro_listings_list_thumbnail",$bp_listing->post_id);
		if(!is_numeric($thumbnail_check)) $thumbnail = $thumbnail_check;
		$default_img = (!empty($thumbnail))? $thumbnail:'<img src="'.$data["default_image"].'"/>';
		echo '<span class="result_img"><a href="'.$permalink.'" target="_blank">'.$default_img.'</a></span>';
	}
	function bepro_listings_list_geo_template($bp_listing){
		$data = get_option("bepro_listings");
		if($data["show_geo"])
			echo '<span class="result_title">'.$bp_listing->city.','.$bp_listing->state.','.$bp_listing->country.'</span>';
	}
	function bepro_listings_list_content_template($bp_listing){
		$content =  substr($bp_listing->post_content, 0, 130);
		echo '<span class="result_desc">'.do_shortcode($content).'</span>';
	}
	function bepro_listings_list_links_template($bp_listing){
		$data = get_option("bepro_listings");
		$permalink = get_permalink( $bp_listing->post_id );
		if($data["show_cost"]){
			if(is_numeric($bp_listing->cost)){ 
				//formats the price to have comas and dollar sign like currency.
				setlocale(LC_MONETARY, "en_US");
				$cost = ($bp_listing->cost == 0)? "Free" : sprintf('%01.2f', $bp_listing->cost);
			}else{
				$cost = "Please Contact";
			} 
			//cost
			echo '<span class="result_cost">'.$cost.'</span>';
		}
		
		//website link
		if(!empty($bp_listing->website))
			echo '<span class="result_button"><a href="http://'.$bp_listing->website.'" target="_blank">Website</a></span>';
		
		//If not private then don't show link to listing
		if($bp_listing->post_status == "publish")
			echo '<span class="result_button"><a href="'.$permalink.'" target="_blank">Item</a></span>';
	}
	
	//User form for creating Bepro Listings
	function user_create_listing($atts = array()){
		global $wpdb;
		
		extract(shortcode_atts(array(
			  'register' => $wpdb->escape($_POST["register"])
		 ), $atts));
		
		//get settings
		$data = get_option("bepro_listings");
		$default_user_id = $data["default_user_id"];
		$num_images = $data["num_images"];
		$validate = $data["validate_form"];
		$show_cost = $data["show_cost"];
		$show_con = $data["show_con"];
		$show_geo = $data["show_geo"];
		
		if(empty($default_user_id) && empty($register)){
			echo "You must provide a 'default user id' in the admin settings or use the registration=1 option.";	
			return;
		}
		
		if(!empty($_POST["save_bepro_listing"])){
			$wp_upload_dir = wp_upload_dir();
			if(bepro_listings_save()){
				echo "<h2>Listing Successfully Saved</h2>";
			}else{
				echo "<h2>Issue saving your listing. Please contact the website administrator</h2>";
			}
		}
		include( dirname( __FILE__ )."/templates/form.php");
	}
	
	
	//content templates
	function bepro_listings_item_title_template(){
		echo get_the_title();
	}
	function bepro_listings_item_gallery_template(){
		$data = get_option("bepro_listings");
		$num_images = $data["num_images"];
		
		//Show wordpress gallery for this page
		$gallery = ($num_images == 0)? "":do_shortcode("[gallery size='".$data["gallery_size"]."' columns=".((!empty($data["gallery_cols"]))? $data["gallery_cols"]:3)."]");
		echo "<div class='bepro_listing_gallery'>".apply_filters("bepro_listings_item_gallery_feature", $gallery)."</div>";
	}
	function bepro_listings_item_after_gallery_template(){
		$page_id = get_the_ID();
		//show categories
		// CP remove space before colon :
		$cat_section = "<h3>Categories: </h3><div class='bepro_listing_category_section'>".get_the_term_list($page_id, 'bepro_listing_types', '', ', ','')."</div>";

		echo $cat_section;
	}
	function bepro_listings_item_details_template(){
		global $wpdb;
		$page_id = get_the_ID();
		$item = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix.BEPRO_LISTINGS_TABLE_NAME." WHERE post_id = ".$page_id);
		//get settings
		$data = get_option("bepro_listings");
		if(is_numeric($item->cost)){
			//formats the price to have comas and dollar sign like currency.
			$cost = ($item->cost == 0)? __("Free", "bepro-listings") : sprintf('%01.2f', $item->cost);
		}else{
			$cost = __("Please Contact", "bepro-listings");
		}
		
		if(!empty($data["show_details"]) && (($data["show_details"] == "on")|| ($data["show_details"] == "on")) ){
			echo "<h3>Details: </h3><span class='bepro_listing_info'>";
			if($data["show_cost"] == "on"){
				echo "<div class='item_cost'>".__("Cost", "bepro-listings")." - ".$cost."</div>";
			}	
				//If we have geographic data then we can show this listings address information
				if($item->lat){
					$map_url = "http://maps.google.com/maps?&z=10&q=".$item->lat."+".$item->lon."+(".urlencode($item->address_line1.", ".$item->city.", ".$item->state.", ".$item->country).")&mrt=yp ";
					echo "<div class='bepro_address_info'><span class='item_label'>".__("Address", "bepro-listings")."</span> - <a href='$map_url' target='_blank'>".__("View Map", "bepro-listings")."</a></div>";
				}
				//If there is contact information then show it
				if($data["show_con"] == "on"){
					echo "<div class='item_contactinfo'>
							".(empty($item->first_name)? "":"<span class='item_label'>".__("First Name", "bepro-listings")."</span> - ".$item->first_name."<br />")."
							".(empty($item->last_name)? "":"<span class='item_label'>".__("Last Name", "bepro-listings")."</span> - ".$item->last_name."<br />")."
							".(empty($item->email)? "":"<span class='item_label'>".__("Email", "bepro-listings")."</span> - <a href='mailto:".$item->email."'>".$item->email."</a>"."<br />")."
							".(empty($item->phone)? "":"<span class='item_label'>".__("Phone", "bepro-listings")."</span> - <a href='tel:".$item->phone."'>".$item->phone."</a>")."
						</div>";
				}
			echo "</span>";
		}
		
	}
	function bepro_listings_item_content_template(){
		$data = get_option("bepro_listings");
		if(!empty($data["show_content"]) && (($data["show_content"] == "on")|| ($data["show_content"] == 1)) ){
			echo "<div class='bepro_listing_desc'>".apply_filters("bepro_listings_item_content",bepro_listings_item_tabs())."</div>";
		}	
	}
	
	//comments template
	
	function bepro_listings_item_tabs(){
		include(plugin_dir_path( __FILE__ ).'/templates/tabs.php');
	}
	
	
?>
