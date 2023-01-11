<?php

/**
 * Plugin name: Savior Fiverr Gig Post Filter
 * Author: Savior Team
 */


add_action('wp_ajax_nopriv_savior_filter_fiverr_gigs', 'savior_filter_fiverr_gigs');
add_action('wp_ajax_savior_filter_fiverr_gigs', 'savior_filter_fiverr_gigs');


function savior_acf_read_only_field( $field ) {

    $field_name = ['_hide_seller_name', '_hide_seller_number', '_hide_seller_level'];

    if(in_array($field['name'], $field_name)){
	    $field['disabled'] = true;
    }
	return $field;
}

add_filter( 'acf/load_field', 'savior_acf_read_only_field' );


// Save the meta box content
function savior_save_seller_meta_box($post_id){
	$current_user = wp_get_current_user();
	$author_id = !empty(get_post_field( 'post_author', $post_id )) ? get_post_field( 'post_author', $post_id ): $current_user->ID;
    $username = get_field('fiverr_user_id', 'user_'. $author_id );
	$fiverr_rating_number = get_field('fiverr_rating_number', 'user_'. $author_id );
	$fiverr_level = get_field('fiverr_level', 'user_'. $author_id );

    // update the field
	update_field('field_63bd2491a86c4', $username, $post_id);
	update_field('field_63bd24eba86c5', $fiverr_rating_number, $post_id);
	update_field('field_63bd24fea86c6', $fiverr_level['value'], $post_id);

}
add_action( 'save_post', 'savior_save_seller_meta_box' );


function savior_filter_fiverr_gigs(){
    ob_start();
    header("Content-Type: application/json");

	$paged = !empty($_POST['paged']) ? $_POST['paged']: 1;
	$meta_query = array('relation' => 'OR');
	if(isset($_POST['sellerName']) && $_POST['sellerName'] !== '') {
		$sellerName = sanitize_text_field( $_POST['sellerName'] );
		$meta_query[] = array(
			'key' => '_hide_seller_name',
			'value' => $sellerName,
			'compare' => '='
		);
	}
	if(isset($_POST['sellerNumber']) && $_POST['sellerNumber'] !== '') {
		$sellerNumber = sanitize_text_field( $_POST['sellerNumber'] );
		$meta_query[] = array(
			'key' => '_hide_seller_number',
			'value' => $sellerNumber,
			'compare' => '='
		);
	}

	if(isset($_POST['industry'])  && $_POST['industry'] !== '') {
		$industry = sanitize_text_field( $_POST['industry'] );
		$meta_query[] = array(
			'key' => 'industry',
			'value' => $industry,
			'compare' => '='
		);
	}
    if(isset($_POST['fiverr_level']) && $_POST['fiverr_level'] !== '') {
		$fiverr_level = sanitize_text_field( $_POST['fiverr_level'] );
		$meta_query[] = array(
			'key' => '_hide_seller_level',
			'value' => $fiverr_level,
			'compare' => 'LIKE'
		);
	}

    if(isset($_POST['fiverr_rating']) && $_POST['fiverr_rating'] !== '') {
		$fiverr_rating = sanitize_text_field( $_POST['fiverr_rating'] );
		$meta_query[] = array(
			'key' => 'fiverr_rating',
			'value' => $fiverr_rating,
			'compare' => '='
		);
	}
	$args = array(
		'post_type' => 'fiverr_gigs',
		'posts_per_page' => 3,
		'meta_query' => $meta_query,
        'paged' => $paged,
	);

	$fiverr_gigs = new WP_Query($args);

	if($fiverr_gigs->have_posts()){
		$result = [];
		while ($fiverr_gigs->have_posts()){ $fiverr_gigs->the_post();
			$author_id = get_the_author_meta('ID');
			$author_name = get_the_author();
			$fiverr_rating_number = get_field('fiverr_rating_number', 'user_'. $author_id );
			$fiverr_level = get_field('fiverr_level', 'user_'. $author_id );
			$fiverr_profile_url = get_field('fiverr_profile_url', 'user_'. $author_id );
			$fiverr_total_rating = get_field('fiverr_total_rating', 'user_'. $author_id );
			$fiverr_rating_star = get_field('fiverr_rating');
			$fiverr_user_id = get_field('fiverr_user_id', 'user_'. $author_id );

			$resultData = array(
				"id" => get_the_ID(),
				"title" => get_the_title(),
				"content" => get_the_content(),
				"permalink" => get_permalink(),
				"author_id" => $author_id,
				"author_name" => $author_name,
				"fiverr_user_name" => $fiverr_user_id,
				"fiverr_user_url" => $fiverr_profile_url,
				"fiverr_rating" => $fiverr_rating_number,
				"fiverr_rating_star" => $fiverr_rating_star,
				"fiverr_total_rating" => $fiverr_total_rating,
				"fiverr_level" => $fiverr_level['label'],
				"poster" => wp_get_attachment_url(get_post_thumbnail_id($post->ID),'full'),
				"fiIcon" => plugin_dir_url(__FILE__) . 'assets/fi-logo.svg',
				"placeholder" => plugin_dir_url(__FILE__) . 'assets/placeholder.jpg'
			);
            $result[] = create_html_data($resultData);

            wp_reset_query();
		}

        if($fiverr_gigs->max_num_pages >= $paged){
	        $result[] = addPagedNumber((int) $paged + 1);
        }


        if($fiverr_gigs->max_num_pages > $paged ){
	        $next_page = (int) $paged + 1;
        }
		$nextPage = !empty($next_page) ? $next_page : false;

        $pagination = savior_fiverr_gig_pagination($paged, $nextPage, $fiverr_gigs->max_num_pages);



		wp_send_json_success([
                'data' => $result,
                'nextPage' => $nextPage,
                'pagination' => $pagination,
                'total_query' => $fiverr_gigs->max_num_pages,
                'currentIndex' => $paged
        ]);
	}else{
		wp_send_json_error('No Fiverr Gig Found!');
	}
    return ob_get_clean();
}

function savior_fiverr_gig_pagination($paged, $next_page, $totalPage){
    ob_start();
    $currentPage = (int) $paged;

    if($currentPage >= 2 && $totalPage >= $currentPage){
        $currentPage = $currentPage === $totalPage ? $currentPage : $currentPage -1;
        printf('<button class="paginate_button" data-index="%s" id="prevousBtn">Previous</button>', $currentPage );
    }

//     paginate number
    $numberPaginate = [];
    if($currentPage < $totalPage){
        for ($i = 1; $i <= $totalPage; $i ++){
	        $numberPaginate[] = $i;

        }
    }

    array_map('savior_pagination_maping', $numberPaginate);

	if($next_page  <= $totalPage && $next_page != false){
		printf('<button class="paginate_button" data-index="%s" id="nextButton">Next</button>', $next_page);
	}

    return ob_get_clean();
}

function savior_pagination_maping($item){
    printf('<span class="paginate_wrap"><a data-index="%s" class="pagination_number" href="#">%s</a></span>', $item, $item);
}

function addPagedNumber($paged){
    ob_start();
    ?>
    <input type="hidden" id="pagedNumber" value="<?php echo esc_attr($paged); ?>">
        <?php
    return ob_get_clean();
}

function create_html_data($data){
    ob_start();
	$poster = !empty($data['poster']) ? $data['poster'] : $data['placeholder'];
	$fiverrUrl = !empty($data['fiverr_user_url']) ? $data['fiverr_user_url'] : (!empty($data['fiverr_user_name']) ? "https://www.fiverr.com/" . $data['fiverr_user_name']: '#');
	$avatar = get_avatar_url($data['author_id']);
	$rated_class = !empty($data['fiverr_total_rating']) ? 'rated' : 'not_rated';
	$rating = !empty($data['fiverr_total_rating']) ? $data['fiverr_total_rating'] : 0;

	?>

    <div class="fiverr_gigs gig_signle gig_<?php echo  esc_attr($data['id']); ?>">
        <div class="image_area">
            <div class="fiverr_profile_info">
                <a class="fiverr_icon" target="_blank" href="<?php echo esc_url($fiverrUrl); ?>">
                    <img src="<?php  echo esc_url($data['fiIcon']); ?>" class="fiverr_icon_img" alt="Fiverr Profile">
                </a>
            </div>
            <a href="<?php echo esc_url($data['permalink']); ?>">
                <img src="<?php echo esc_url($poster);?>" alt="<?php echo esc_attr($data['title']);?>">
            </a>
        </div>
        <div class="user_info_wrap">
            <div class="user_icon">
                <img src="<?php echo esc_url($avatar) ?>" alt="<?php echo esc_attr($data['author_name']); ?>">
            </div>
            <div class="user_name">
                <h4><?php echo esc_html($data['author_name']) ?></h4>
                <span class="autho_level"> <?php echo esc_html($data['fiverr_level']); ?></span>
            </div>
            <div class="rating_info <?php echo esc_attr($rated_class); ?>">
                <span class="sicon-star-full"></span>
				<?php
				if($rating > 0){
					echo "<span class='rating_titel'>(".esc_html($rating).")</span>";
				}
				?>

            </div>
        </div>
        <div class="post_title_wrap">
            <h2 class="gig_title"><a href="<?php echo esc_url($data['permalink']); ?>"><?php echo esc_html($data['title']) ?></a></h2>
            <div class="read_more">
                <a class="read_more gig_readmore" href="<?php echo esc_url($data['permalink']); ?>">Read More</a>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

function savior_fiverr_gig_filter_posts(){
	ob_start();
	?>
<style>
    .s_row {
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>
<div class="search_form">
	<form action="" id="fiverr_gig_search">
		<div class="row_1 s_row">
			<input class="text_field" type="text" id="sellerName" placeholder="Seller Name">
			<input class="text_field" type="text" id="sellerNumber" placeholder="Seller Number">
		</div>
		<div class="row_2 s_row">
			<div class="field_one">
				<select class="select_field" name="" id="industry">
					<option value="">industry</option>
					<?php
					$field = get_field_object('field_604a0f5c8c468');
					$choices = $field['choices'];
					foreach ($choices as $key => $values){
						echo '<option value="'.$key.'">'.$values.'</option>';
					} ?>
				</select>

			</div>
			<div class="field_one">
				<select class="select_field" name="" id="fiverr_level">
					<option value="">Fiverr Level</option>
					<option value="new-seller">New Seller</option>
					<option value="level-1">Level 1</option>
					<option value="level-2">Level 2</option>
					<option value="top-rated-seller">Top Rated Seller</option>
				</select>

			</div>
			<div class="field_one">
				<select class="select_field" name="" id="fiverr_rating">
					<option value="">Fiverr rating</option>
					<?php
					$field = get_field_object('field_604a2470272a2');
					$choices = $field['choices'];
					foreach ($choices as $key => $values){
						echo '<option value="'.$key.'">'.$values.'</option>';
					} ?>
				</select>
			</div>

		</div>
		<div class="s_row">
			<button class="search" id="search_button">Search</button>
            <button style="display: none" class="reset" id="reset_button">Reset</button>
		</div>
	</form>
</div>
    <style>
        <?php $plugin_assets = plugin_dir_url(__FILE__) . 'assets/' ?>
        @font-face {
            font-family: 'savior_star';
            src:  url('<?php echo esc_url($plugin_assets); ?>/fonts/savior_star.eot?5r20pg');
            src:  url('<?php echo esc_url($plugin_assets); ?>/fonts/savior_star.eot?5r20pg#iefix') format('embedded-opentype'),
            url('<?php echo esc_url($plugin_assets); ?>/fonts/savior_star.ttf?5r20pg') format('truetype'),
            url('<?php echo esc_url($plugin_assets); ?>/fonts/savior_star.woff?5r20pg') format('woff'),
            url('<?php echo esc_url($plugin_assets); ?>/fonts/savior_star.svg?5r20pg#savior_star') format('svg');
            font-weight: normal;
            font-style: normal;
            font-display: block;
        }

        [class^="sicon-"], [class*=" sicon-"] {
            /* use !important to prevent issues with browser extensions that change fonts */
            font-family: 'savior_star' !important;
            speak: never;
            font-style: normal;
            font-weight: normal;
            font-variant: normal;
            text-transform: none;
            line-height: 1;

            /* Better Font Rendering =========== */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .sicon-star-full:before {
            content: "\e9d9";
        }
        div#result_info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }

        .fiverr_gigs {
            width: 33%;
            padding: 15px;
        }
        .sicon-star-fullsicon_:before {
            content: "\e9d9";
        }
        .fiverr_profile_info {
            position: absolute;
            width: 40px;
            height: 40px;
            right: 15px;
            top: 15px;
            z-index: 999;
            background: #1ebf73;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .3s ease-in;
        }

        .image_area {
            position: relative;
        }

        a.fiverr_icon {
            text-align: center;
            display: block;
            width: 100%;
        }

        img.fiverr_icon_img {
            width: 20px;
            margin-top: 10px;
        }

        .fiverr_profile_info:hover {
            background: #17202d;
        }
        .loadingContainer{
            width: 100%;
            height: 500px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .savior_loader {
            z-index: 9999;
            width: 48px;
            height: 48px;
            border: 5px solid #17202d;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }

        @keyframes rotation {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
    </style>
    <div id="result_info"></div>
    <div id="data_load_more"></div>



<?php
	// Get Post type

	return ob_get_clean();
}

add_shortcode('savior_fiverr_gigs', 'savior_fiverr_gig_filter_posts');


add_action('wp_footer', 'add_scripts_to_footer', 999);

function add_scripts_to_footer(){
    ?>
    <script>
        ;(function($){
            window.onload = () =>{
                $('#fiverr_gig_search').submit();
            }
            $('#reset_button').on('click', function (event){
                event.preventDefault()
                $('#fiverr_gig_search').trigger('reset').submit();
                $('#reset_button').hide();
            })
            $('#search_button').on('click', function(event){
                $('#pagedNumber').val(1)
                event.preventDefault();
                $('#fiverr_gig_search').submit();
            })
            $('#fiverr_gig_search').on('submit', function(event){
                event.preventDefault();
                let paged = $('#pagedNumber').val() > 0 ? $('#pagedNumber').val() : 1;
                let sellerName = $('#sellerName').val().length !== 0 ? $('#sellerName').val(): '';
                let sellerNumber = $('#sellerNumber').val().length !== 0 ? $('#sellerNumber').val(): '';
                let industry = $('#industry').val().length !== 0 ? $('#industry').val(): '';
                let fiverr_level = $('#fiverr_level').val().length !== 0 ? $('#fiverr_level').val(): '';
                let fiverr_rating = $('#fiverr_rating').val().length !== 0 ? $('#fiverr_rating').val(): '';
                const data = {
                    sellerName,
                    sellerNumber,
                    industry,
                    fiverr_level,
                    fiverr_rating,
                    paged

                };

                fiverr_gigs_ajax_load(data);

                // check if any field has value
                // add reset button
                if(sellerName  || sellerNumber || industry || fiverr_level || fiverr_rating){
                    $('#reset_button').show();
                }

            });

            function fiverr_gigs_ajax_load(data){
                console.log(data);
                let resultInfo = $('#result_info');
                let dataLoadMore = $('#data_load_more');
                const loaderIcon = `<div class="loadingContainer" > <span class="savior_loader"></span> </div>`;

                // start loading
                resultInfo.empty();
                resultInfo.append(loaderIcon);

                let ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    dataType: 'json',
                    data:{
                        sellerName:data.sellerName,
                        sellerNumber: data.sellerNumber,
                        industry: data.industry,
                        fiverr_level: data.fiverr_level,
                        fiverr_rating: data.fiverr_rating,
                        action: 'savior_filter_fiverr_gigs',
                        paged: data.paged
                    },
                    success: function (response){
                        console.log(response);
                        if(response.success == false){
                            resultInfo.html('<h2>' + response.data +' </h2>');
                            $('.load_more').remove()
                            $("#data_load_more").html('')
                        }else{
                            resultInfo.html(response.data.data);
                            let nextPage = response.data.nextPage;
                            if( (nextPage !== null) && (nextPage <= response.data.total_query)){
                                $('.load_more').remove()
                                $("#data_load_more").html(response.data.pagination)
                                // desable current index
                                $(`a[data-index=${response.data.currentIndex}]`).attr('disabled', 'true').parent('.paginate_wrap').addClass('currentIndex')
                            }
                        }

                    },
                    error: err => {
                        if(err.success = false){
                            resultInfo.html('<h2>' + err.data +' </h2>');
                        }
                    }
                })
            }
            $('#data_load_more').on('click', function(event){
                event.preventDefault();
                if($(event.target).attr('data-index') > 0 && !isNaN($(event.target).attr('data-index'))){
                    $('#pagedNumber').val($(event.target).attr('data-index'))
                    $('html,body').animate({ scrollTop: $("#result_info").offset().top -100 }, 'slow');

                    $('#fiverr_gig_search').submit();
                }

            })
        })(jQuery)
    </script>

<?php
}