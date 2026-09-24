<?php
/** Standalone regression coverage for exact asset and redirect recovery. */
namespace RankMath\Redirections {
	class DB {
		public static $rows = array();
		public static function get_redirection_by_id($id,$status='all') { return self::$rows[$id] ?? false; }
		public static function get_redirections($args=array()) { return array('redirections'=>array_values(self::$rows),'count'=>count(self::$rows)); }
		public static function delete($ids) { foreach($ids as $id) { unset(self::$rows[$id]); } return 1; }
		public static function change_status($ids,$status) { foreach($ids as $id) { self::$rows[$id]['status']=$status; } return 1; }
		public static function add($args) { $args['id']=99; self::$rows[99]=$args; return 99; }
	}
}
namespace {
	require __DIR__.'/fixtures/safety-harness.php';
	require_once CC_ASSISTANT_DIR.'includes/class-asset-references.php';
	function redirect_fixture($status='active') {
		return array('id'=>7,'sources'=>serialize(array(array('pattern'=>'old-path','comparison'=>'exact'))),'url_to'=>'https://owned-site.example/new','header_code'=>'301','status'=>$status,'hits'=>9);
	}
	function capture_redirect($type,$before) {
		reset_review(); \RankMath\Redirections\DB::$rows=array(7=>$before);
		$id=pending($type,current_time('mysql'),'approved',0); $proposed=array('id'=>7);
		$r=CC_Assistant_Recovery::capture(CC_Assistant_Pending_Changes::get($id),$proposed,null);
		check($r===true,'Redirect recovery saved before '.$type);
		return $id;
	}
	$id=capture_redirect('delete_redirect',redirect_fixture());
	unset(\RankMath\Redirections\DB::$rows[7]);
	$r=CC_Assistant_Apply::rollback_pending($id,17);
	check(!is_wp_error($r) && \RankMath\Redirections\DB::$rows[99]['sources'][0]['pattern']==='old-path' && \RankMath\Redirections\DB::$rows[99]['url_to']==='https://owned-site.example/new','Deleted redirect recovers all sources/settings with a new ID');
	$id=capture_redirect('delete_redirect',redirect_fixture());
	\RankMath\Redirections\DB::$rows=array(8=>array_merge(redirect_fixture(),array('id'=>8)));
	$r=CC_Assistant_Apply::rollback_pending($id,17);
	check(err($r,'rollback_conflict') && !isset(\RankMath\Redirections\DB::$rows[99]),'Redirect rollback refuses reassigned source');
	$id=capture_redirect('untrash_redirect',redirect_fixture('inactive'));
	\RankMath\Redirections\DB::$rows[7]['status']='active';
	\RankMath\Redirections\DB::$rows[7]['hits']=100;
	$r=CC_Assistant_Apply::rollback_pending($id,17);
	check(!is_wp_error($r) && \RankMath\Redirections\DB::$rows[7]['status']==='inactive','Untrash rollback restores prior status despite ordinary hit count changes');
	reset_review(); $id=pending('create_redirect',current_time('mysql'),'approved',0);
	\RankMath\Redirections\DB::$rows=array(7=>redirect_fixture());
	CC_Assistant_Recovery::complete(CC_Assistant_Pending_Changes::get($id),array(),array('redirection_id'=>7),null);
	$r=CC_Assistant_Apply::rollback_pending($id,17);
	check(!is_wp_error($r) && !isset(\RankMath\Redirections\DB::$rows[7]),'Created redirect rollback removes only its saved redirect');
	reset_review(); $id=pending('create_redirect',current_time('mysql'),'approved',0);
	\RankMath\Redirections\DB::$rows=array(7=>redirect_fixture());
	CC_Assistant_Recovery::complete(CC_Assistant_Pending_Changes::get($id),array(),array('redirection_id'=>7),null);
	\RankMath\Redirections\DB::$rows[7]['url_to']='https://owned-site.example/human-edit';
	$r=CC_Assistant_Apply::rollback_pending($id,17);
	check(err($r,'rollback_conflict'),'Created redirect rollback protects later edits');
	reset_review();
	$wpdb=$GLOBALS['wpdb']; $wpdb->posts='wp_posts'; $wpdb->postmeta='wp_postmeta'; $wpdb->termmeta='wp_termmeta';
	$wpdb->db->exec('CREATE TABLE wp_postmeta (meta_id INTEGER PRIMARY KEY, meta_value TEXT)');
	$old='https://owned-site.example/old.png'; $new='https://owned-site.example/new.png';
	$before=serialize(array('first'=>$old,'already_new'=>$new,'caption'=>'A "quoted" caption'));
	$wpdb->db->exec($wpdb->prepare('INSERT INTO wp_postmeta VALUES (1,%s)',$before));
	$id=pending('asset_reference_replace',current_time('mysql'),'approved',0);
	$plan=array('old_url'=>$old,'new_url'=>$new,'targets'=>array(array('kind'=>'postmeta','id'=>1)));
	$r=CC_Assistant_Recovery::capture(CC_Assistant_Pending_Changes::get($id),$plan,null);
	check($r===true && isset($plan['targets'][0]['recovery_sha256']),'Asset before image and apply guard saved');
	list($after)=CC_Assistant_Asset_References::rewrite_value($before,$old,$new);
	$outcome=CC_Assistant_Asset_References::apply_plan($plan);
	check(count($outcome['written'])===1 && $wpdb->get_var('SELECT meta_value FROM wp_postmeta WHERE meta_id=1')===$after,'Asset apply writes captured row through conditional update');
	$r=CC_Assistant_Apply::rollback_pending($id,17);
	check(!is_wp_error($r) && $wpdb->get_var('SELECT meta_value FROM wp_postmeta WHERE meta_id=1')===$before,'Asset rollback restores exact serialized bytes without reversing pre-existing destination URLs');
	$records=array(array('location'=>array('kind'=>'postmeta','id'=>1),'before'=>$before,'after_hash'=>hash('sha256',$after)));
	$wpdb->update('wp_postmeta',array('meta_value'=>'later editor content'),array('meta_id'=>1));
	$r=CC_Assistant_Asset_References::restore_locations($records);
	check(err($r,'rollback_conflict') && $wpdb->get_var('SELECT meta_value FROM wp_postmeta WHERE meta_id=1')==='later editor content','Asset rollback protects later edits');
	echo "Recovery regression checks passed.\n";
}
