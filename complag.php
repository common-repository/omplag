<?php
/**
 * Plugin Name: Complag
 * Description: Работа с комментариями.
 * Plugin URI: https://site.com
 * Author: andru1
 * Version: 1.0.2
 * Author URI: https://profiles.wordpress.org/andru1
*/
// активация
register_activation_hook(__FILE__, 'complag_activate');
function complag_activate() {
	add_option('complag_option', '');
}

// деактивация
register_deactivation_hook(__FILE__, 'complag_deactivate');
function complag_deactivate(){
	delete_option('complag_option');
}

// страница настроек
add_action('admin_menu', 'complag_register');
function complag_register() { 
	add_submenu_page('options-general.php', 'Настройки Complag', 'Complag', 'manage_options', 'complag-set', 'complag_submenu_page_callback'); 
}
function complag_submenu_page_callback() {
	if (isset($_POST['complag_submit']) && current_user_can('edit_plugins') == true) {
		if (!isset($_POST['complag_update_setting'])) die("Проверка не пройдена!");
		if (!wp_verify_nonce($_POST['complag_update_setting'],'complag-update-setting')) die("Проверка не пройдена!");
        update_option('complag_option', sanitize_key($_POST['complag_key']));
    }
?>
	<div class="wrap">
		<h2><?php echo get_admin_page_title(); ?></h2>
	</div>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=complag-set">
		<table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Вставьте key'); ?></th>
				<td>
                    <input type="text" name="complag_key" size="80" value="<?php echo get_option('complag_option'); ?>" />
                </td>
            </tr>
        </table>
        <p class="submit">
			<input type="submit" name="complag_submit" value="<?php _e('Save Changes') ?>" />
			<input name="complag_update_setting" type="hidden" value="<?php echo wp_create_nonce('complag-update-setting'); ?>" />
        </p>
    </form>
<?php } ?>
<?php
add_action('wp_ajax_nopriv_complag_countcomments', 'complag_countcomments', 1001); // название хука, функция при срабатывании хука
add_action('wp_ajax_nopriv_complag_gettablepag', 'complag_gettablepag', 1002);
add_action('wp_ajax_nopriv_complag_changestatus', 'complag_changestatus', 1003);
add_action('wp_ajax_nopriv_complag_editcomment', 'complag_editcomment', 1004); // сохранение редактируемого комментария
add_action('wp_ajax_nopriv_complag_flush_all_w3tc', 'complag_flush_all_w3tc', 1005); // сбросить весь кеш из плагина w3tc
add_action('wp_ajax_nopriv_complag_reloadBlocks_bbq', 'complag_reloadBlocks_bbq', 1006); // синхронизировать блоки bbq

function complag_reloadBlocks_bbq () {
   if (sanitize_key($_GET['key']) == get_option('complag_option')) {
	    Bbq_Admin::reloadBlocks();
	    echo 1;
		wp_die();
	}
}

function complag_flush_all_w3tc () {
	if (sanitize_key($_GET['key']) == get_option('complag_option')) {
	    w3tc_flush_all($extras = null);
	    echo 1;
		wp_die();
	}
}
function complag_countcomments () {
	if (sanitize_key($_GET['key']) == get_option('complag_option')) {
		$mas = [];
		$comments_count = wp_count_comments();
		$stat = '';
		$stat .= "на модерации: " . $comments_count->moderated . "<br />"; 
		$stat .= "утвержденные: " . $comments_count->approved . "<br />";
		$stat .= "помеченные как спам: " . $comments_count->spam . "<br />";
		$stat .= "в корзине: " . $comments_count->trash . "<br />";
		$stat .= "всего: " . $comments_count->total_comments . "<br />";
		$mas['stat'] = $stat;
		$dataplag = get_plugin_data(__FILE__);
		$mas['verplag'] = $dataplag['Version']; // версия плагина
		echo json_encode($mas);
		wp_die();
	}
}
function complag_gettablepag () { // главная страница комментария
	if (sanitize_key($_GET['key']) == get_option('complag_option')) {
		$mas = [];
		$search = sanitize_text_field($_GET['search']); // искомый запрос
		$order = sanitize_text_field($_GET['order']); // сортировка
		$offset = sanitize_text_field($_GET['offset']); // сколько нужно пропустить
		$limit = sanitize_text_field($_GET['limit']); // лимит на вывод
		$status = sanitize_text_field($_GET['status']); // статус комментария
		$post_id = sanitize_text_field($_GET['postid']); // статус комментария
		$args = array(
			'search' => $search,
			'order' => $order,
			'offset' => $offset,
			'number' => $limit,
			'orderby' => 'comment_date',
			'type' => '', // только комментарии, без пингов и т.д...
			'status' => $status,
			'post_id' => $post_id
		);
		$comments_count = wp_count_comments();
		$mas['approved'] = $comments_count->approved; // одобренных комментариев
		$mas['total'] = $comments_count->total_comments; // всего комментариев
		$mas['moderated'] = $comments_count->moderated; // на модерации
		$mas['spam'] = $comments_count->spam; // количество спама
		$mas['trash'] = $comments_count->trash; // количество в корзине
		if($comments = get_comments($args)){
			foreach($comments as $comment){
				$getpost = get_post($comment->comment_post_ID);
				if ($comment->comment_parent != 0) {
					$parent_com = get_comment($comment->comment_parent);
					$parent_author = $parent_com->comment_author;
					if (trim($parent_author) != '') {
						$author = $parent_author;
					} else {
						$author = 'Аноним';
					}
					$paretn_comment = '<i class="votvet">В ответ:</i> '.$author;
				} else {
					$paretn_comment = 0;
				}
				$mas[] = array(
					'id' => $comment->comment_ID,
					'postid' => $comment->comment_post_ID,
					'url' => '<a href="'.get_permalink($comment->comment_post_ID).'">'.$getpost->post_title.'</a>',
					'author' => $comment->comment_author,
					'date' => $comment->comment_date,
					'content' => $comment->comment_content,
					'approved' => $comment->comment_approved,
					'countcommpost' => wp_count_comments($comment->comment_post_ID),
					'parent_author' => $paretn_comment
				);
			}
		}
		echo json_encode($mas);
		wp_die();
	}
}

function complag_changestatus () { // изменение статуса комментария
    if (sanitize_key($_GET['key']) == get_option('complag_option')) {
        echo wp_set_comment_status(sanitize_text_field($_GET['id']), sanitize_text_field($_GET['status']));
        exit();
    }
}

function complag_editcomment () { // редактирование комментария
    if (sanitize_key($_GET['key']) == get_option('complag_option')) {
		$commentarr = array();
		$commentarr['comment_ID'] = sanitize_text_field($_GET['id']);
		$commentarr['comment_content'] = sanitize_text_field($_GET['text']);
		echo wp_update_comment($commentarr);
		//echo wp_set_comment_status($_GET['id'], '1');
        exit();
    }
}