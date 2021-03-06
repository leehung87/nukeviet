<?php

/**
 * @Project NUKEVIET 3.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2012 VINADES.,JSC. All rights reserved
 * @Createdate 2-2-2010 12:55
 */

if( ! defined( 'NV_IS_FILE_LANG' ) ) die( 'Stop!!!' );

$page_title = $lang_module['nv_lang_data'];

$xtpl = new XTemplate( 'main.tpl', NV_ROOTDIR . '/themes/' . $global_config['module_theme'] . '/modules/' . $module_file );
$xtpl->assign( 'LANG', $lang_module );
$xtpl->assign( 'GLANG', $lang_global );


$array_lang_setup = array();
$result = $db->query( 'SELECT `lang`, `setup` FROM `' . $db_config['prefix'] . '_setup_language`' );
while( $row = $result->fetch() )
{
	$array_lang_setup[$row['lang']] = intval( $row['setup'] );
}

if( defined( 'NV_IS_GODADMIN' ) OR ( $global_config['idsite'] > 0 AND defined( 'NV_IS_SPADMIN' ) ) )
{
	$checksess = $nv_Request->get_title( 'checksess', 'get', '' );
	$keylang = $nv_Request->get_title( 'keylang', 'get', '', 1 );
	$deletekeylang = $nv_Request->get_title( 'deletekeylang', 'get', '', 1 );

	if( $nv_Request->isset_request( 'activelang', 'get' ) and $checksess == md5( 'activelang_' . $keylang . session_id() ) and preg_match( '/^[a-z]{2}$/', $keylang ) )
	{
		$activelang = $nv_Request->get_int( 'activelang', 'get', 0 );
		$allow_sitelangs = $global_config['allow_sitelangs'];

		if( $activelang )
		{
			$allow_sitelangs[] = $keylang;
		}
		elseif( $keylang != $global_config['site_lang'] )
		{
			$allow_sitelangs = array_diff( $allow_sitelangs, array( $keylang ) );
		}

		$allow_sitelangs = array_unique( $allow_sitelangs );

		$sth = $db->prepare( "UPDATE `" . NV_CONFIG_GLOBALTABLE . "` SET `config_value` = :config_value WHERE `lang`='sys' AND `module` = 'global' AND `config_name` = 'allow_sitelangs'" );
		$sth->bindValue( ':config_value', implode( ',', $allow_sitelangs ), PDO::PARAM_STR );
		$sth->execute();

		$temp = ( $activelang == 1 ) ? $lang_global['yes'] : $lang_global['no'];

		nv_insert_logs( NV_LANG_DATA, $module_name, $lang_module['nv_lang_slsite'], ' langkey : ' . $keylang . ' [ ' . $temp . ' ]', $admin_info['userid'] );
		if( empty( $global_config['idsite'] ) )
		{
			nv_save_file_config_global();
		}
		else
		{
			$row = $db->query( 'SELECT * FROM `' . $db_config['dbsystem'] . '`.`' . $db_config['prefix'] . '_site` WHERE `idsite`=' . $global_config['idsite'] )->fetch();

			$array_domains = array_map( 'trim', explode( ',', $row['parked_domains'] ) );
			$array_domains[] = NV_SERVER_NAME;
			$array_domains[] = $row['domain'];
			$array_domains = array_unique( $array_domains );
			foreach( $array_domains as $domains )
			{
				if( ! empty( $domains ) )
				{
					$content_config = "<?php\n\n";
					$content_config .= NV_FILEHEAD . "\n\n";
					$content_config .= "if ( ! defined( 'NV_MAINFILE' ) )\n";
					$content_config .= "{\n";
					$content_config .= "\tdie( 'Stop!!!' );\n";
					$content_config .= "}\n\n";

					$content_config .= "\$db_config['dbsite'] = '" . $db_config['dbsite'] . "';\n";
					$content_config .= "\$global_config['idsite'] = " . $global_config['idsite'] . ";\n";
					$content_config .= "\$global_config['site_dir'] = '" . $global_config['site_dir'] . "';\n";
					$content_config .= "\$global_config['allow_sitelangs'] = '" . nv_unhtmlspecialchars( implode( ',', $allow_sitelangs ) ) . "';\n";
					$content_config .= "\n";
					$content_config .= "?>";

					file_put_contents( NV_ROOTDIR . '/' . NV_CONFIG_DIR . '/' . $domains . '.php', $content_config, LOCK_EX );
				}
			}
			nv_delete_all_cache();
		}
		$xtpl->assign( 'URL', NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op );

		$xtpl->parse( 'activelang' );
		$contents = $xtpl->text( 'activelang' );

		include NV_ROOTDIR . '/includes/header.php';
		echo nv_admin_theme( $contents );
		include NV_ROOTDIR . '/includes/footer.php';
		exit();
	}
	elseif( $checksess == md5( $keylang . session_id() ) and in_array( $keylang, $global_config['allow_adminlangs'] ) )
	{
		if( isset( $array_lang_setup[$keylang] ) and $array_lang_setup[$keylang] == 1 )
		{
			info_die( $lang_module['nv_data_setup'] );
		}
		else
		{
			nv_insert_logs( NV_LANG_DATA, $module_name, $lang_module['nv_setup_new'] . ' ' . $lang_module['nv_lang_data'], ' langkey : ' . $keylang, $admin_info['userid'] );

			$site_theme = $db->query( "SELECT `config_value` FROM `" . NV_CONFIG_GLOBALTABLE . "` where `lang`='" . $global_config['site_lang'] . "' AND `module`='global' AND `config_name`='site_theme'" )->fetchColumn();

			$global_config['site_theme'] = $site_theme;

			require_once NV_ROOTDIR . '/includes/sqldata.php';

			$sql_create_table = nv_create_table_sys( $keylang );

			foreach( $sql_create_table as $query )
			{
				$db->exec( $query );
			}

			$db->exec( "REPLACE INTO `" . $db_config['prefix'] . "_setup_language` (`lang`, `setup`) VALUES ('" . $keylang . "', '1')" );

			if( defined( 'NV_MODULE_SETUP_DEFAULT' ) )
			{
				$lang_module['modules'] = '';
				$lang_module['vmodule_add'] = '';
				$lang_module['blocks'] = '';
				$lang_module['autoinstall'] = '';
				$lang_global['mod_modules'] = '';

				$module_name = 'modules';
				require_once NV_ROOTDIR . '/' . NV_ADMINDIR . '/modules/functions.php';
				$module_name = '';

				$array_module_setup = explode( ',', NV_MODULE_SETUP_DEFAULT );
				$modules_exit = nv_scandir( NV_ROOTDIR . '/modules', $global_config['check_module'] );

				$sql = 'SELECT * FROM `' . $db_config['prefix'] . '_' . $keylang . '_modules` ORDER BY `weight` ASC';
				$result = $db->query( $sql );
				while( $row = $result->fetch() )
				{
					$setmodule = $row['title'];
					$row['module_file'] = $row['module_file'];
					if( in_array( $row['module_file'], $modules_exit ) AND in_array( $setmodule, $array_module_setup ))
					{
						nv_setup_data_module( $keylang, $setmodule );
					}
					else
					{
						$sth = $db->prepare( 'DELETE FROM `' . $db_config['prefix'] . '_' . $keylang . '_modules` WHERE `title`= :module' );
						$sth->bindParam( ':module', $setmodule, PDO::PARAM_STR );
						$sth->execute();
					}
				}

				//cai dat du lieu mau
				$filesavedata = '';
				$lang_data = $keylang;
				if( file_exists( NV_ROOTDIR . '/install/data_' . $keylang . '.php' ) )
				{
					$filesavedata = $keylang;
				}
				elseif( file_exists( NV_ROOTDIR . '/install/data_en.php' ) )
				{
					$filesavedata = 'en';
				}
				if( ! empty( $filesavedata ) )
				{
					$sql_create_table = array();
					include_once NV_ROOTDIR . '/install/data_' . $filesavedata . '.php' ;
					foreach( $sql_create_table as $query )
					{
						try
						{
							$db->exec( $query );
						}
						catch (PDOException $e)
						{
							include NV_ROOTDIR . '/includes/header.php';
							echo nv_admin_theme( 'ERROR SETUP SQL: <br />' . $query );
							include NV_ROOTDIR . '/includes/footer.php';
							exit();
						}
					}
					//xoa du lieu tai bang nv3_vi_modules
					$db->exec( "DELETE FROM `" . $db_config['prefix'] . "_" . $lang_data . "_modules` WHERE `module_file` NOT IN ('" . implode( "', '", $modules_exit ) . "')" );

					//xoa du lieu tai bang nv3_setup_modules
					$db->exec( "DELETE FROM `" . $db_config['prefix'] . "_setup_modules` WHERE `module_file` NOT IN ('" . implode( "', '", $modules_exit ) . "')" );

					//xoa du lieu tai bang nv3_vi_blocks
					$db->exec( 'DELETE FROM `' . $db_config['prefix'] . '_' . $lang_data . '_blocks_weight` WHERE `bid` in (SELECT `bid` FROM `' . $db_config['prefix'] . '_' . $lang_data . '_blocks_groups` WHERE `module` NOT IN (SELECT `title` FROM `' . $db_config['prefix'] . '_' . $lang_data . '_modules`))' );

					//xoa du lieu tai bang nv3_vi_blocks_groups
					$db->exec( 'DELETE FROM `' . $db_config['prefix'] . '_' . $lang_data . '_blocks_groups` WHERE `module` NOT IN (SELECT `title` FROM `' . $db_config['prefix'] . '_' . $lang_data . '_modules`)' );

					//xoa du lieu tai bang nv3_vi_modthemes
					$db->exec( 'DELETE FROM `' . $db_config['prefix'] . '_' . $lang_data . '_modthemes` WHERE `func_id` in (SELECT `func_id` FROM `' . $db_config['prefix'] . '_' . $lang_data . '_modfuncs` WHERE `in_module` NOT IN (SELECT `title` FROM `' . $db_config['prefix'] . '_' . $lang_data . '_modules`))' );

					//xoa du lieu tai bang nv3_vi_modfuncs
					$db->exec( 'DELETE FROM `' . $db_config['prefix'] . '_' . $lang_data . '_modfuncs` WHERE `in_module` NOT IN (SELECT `title` FROM `' . $db_config['prefix'] . '_' . $lang_data . '_modules`)' );

					//xoa du lieu tai bang nv3_config
					$db->exec( "DELETE FROM `" . $db_config['prefix'] . "_config` WHERE `lang`= '" . $lang_data . "' AND `module`!='global' AND `module` NOT IN (SELECT `title` FROM `" . $db_config['prefix'] . "_" . $lang_data . "_modules`)" );

					$result = $db->query( "SELECT * FROM `" . $db_config['prefix'] . "_" . $lang_data . "_modules` WHERE `title`='news'" );
					if( $result->rowCount() )
					{
						$result = $db->query( 'SELECT catid FROM `' . $db_config['prefix'] . '_' . $lang_data . '_news_cat` ORDER BY `order` ASC' );
						while( list( $catid_i ) = $result->fetch( 3 ) )
						{
							nv_create_table_news( $catid_i );
						}
						$result->closeCursor();

						$result = $db->query( 'SELECT id, listcatid FROM `' . $db_config['prefix'] . '_' . $lang_data . '_news_rows` ORDER BY `id` ASC' );
						while( list( $id, $listcatid ) = $result->fetch( 3 ) )
						{
							$arr_catid = explode( ',', $listcatid );
							foreach( $arr_catid as $catid )
							{
								$db->exec( 'INSERT INTO `' . $db_config['prefix'] . '_' . $lang_data . '_news_' . $catid . '` SELECT * FROM `' . $db_config['prefix'] . '_' . $lang_data . '_news_rows` WHERE `id`=' . $id );
							}
						}
						$result->closeCursor();
					}
				}
			}
			$nv_Request->set_Cookie( 'data_lang', $keylang, NV_LIVE_COOKIE_TIME );

			$xtpl->assign( 'URL', NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=settings&' . NV_OP_VARIABLE . '=main' );

			$xtpl->parse( 'contents_setup' );
			$contents = $xtpl->text( 'contents_setup' );

			include NV_ROOTDIR . '/includes/header.php';
			echo nv_admin_theme( $contents );
			include NV_ROOTDIR . '/includes/footer.php';
			exit();
		}
	}
	elseif( $checksess == md5( $deletekeylang . session_id() . 'deletekeylang' ) and ! in_array( $deletekeylang, $global_config['allow_sitelangs'] ) )
	{
		define( 'NV_IS_FILE_MODULES', true );

		$lang = $deletekeylang;

		$sql = 'SELECT `title`, `module_file`, `module_data` FROM `' . $db_config['prefix'] . '_' . $lang . '_modules` ORDER BY `weight` ASC';
		$result_del_module = $db->query( $sql );
		while( list( $title, $module_file, $module_data ) = $result_del_module->fetch( 3 ) )
		{
			if( file_exists( NV_ROOTDIR . '/modules/' . $module_file . '/action.php' ) )
			{
				$sql_drop_module = array();

				include NV_ROOTDIR . '/modules/' . $module_file . '/action.php' ;

				if( ! empty( $sql_drop_module ) )
				{
					foreach( $sql_drop_module as $sql )
					{
						try
						{
							$db->exec( $sql );
						}
						catch (PDOException $e)
						{
							trigger_error( $e->getMessage() );
						}
					}
				}
			}
		}

		require_once NV_ROOTDIR . '/includes/sqldata.php';

		$sql_drop_table = nv_delete_table_sys( $deletekeylang );

		foreach( $sql_drop_table as $sql )
		{
			try
			{
				$db->exec( $sql );
			}
			catch (PDOException $e)
			{
				trigger_error( $e->getMessage() );
			}
		}

		$db->exec( "DELETE FROM `" . NV_CONFIG_GLOBALTABLE . "` WHERE `lang` = '" . $deletekeylang . "'" );
		$db->exec( "DELETE FROM `" . $db_config['prefix'] . "_setup_language` WHERE `lang` = '" . $deletekeylang . "'" );

		nv_delete_all_cache();
		nv_insert_logs( NV_LANG_DATA, $module_name, $lang_module['nv_setup_delete'], ' langkey : ' . $deletekeylang, $admin_info['userid'] );

		Header( 'Location: ' . NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op . '&' . NV_LANG_VARIABLE . '=' . $global_config['site_lang'] . '&rand=' . nv_genpass() );
		exit();
	}
}
$a = 0;
foreach( $global_config['allow_adminlangs'] as $keylang )
{
	$delete = '';
	$allow_sitelangs = '';

	$xtpl->assign( 'ROW', array(
		'keylang' => $keylang,
		'name' => $language_array[$keylang]['name']
	) );

	if( defined( 'NV_IS_GODADMIN' ) OR ( $global_config['idsite'] > 0 AND defined( 'NV_IS_SPADMIN' ) ) )
	{
		if( isset( $array_lang_setup[$keylang] ) and $array_lang_setup[$keylang] == 1 )
		{
			if( ! in_array( $keylang, $global_config['allow_sitelangs'] ) )
			{
				$xtpl->assign( 'DELETE', NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=' . $op . '&amp;deletekeylang=' . $keylang . '&amp;checksess=' . md5( $keylang . session_id() . 'deletekeylang' ) );

				$xtpl->parse( 'main.loop.setup_delete' );
			}
			else
			{
				$xtpl->parse( 'main.loop.setup_note' );
			}

			if( $keylang != $global_config['site_lang'] )
			{
				$selected_yes = $selected_no = ' ';

				if( in_array( $keylang, $global_config['allow_sitelangs'] ) )
				{
					$selected_yes = ' selected="selected"';
				}
				else
				{
					$selected_no = ' selected="selected"';
				}

				$xtpl->assign( 'ALLOW_SITELANGS', array(
					'selected_yes' => $selected_yes,
					'selected_no' => $selected_no,
					'url_yes' => NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=' . $op . '&amp;keylang=' . $keylang . '&amp;activelang=1&amp;checksess=' . md5( 'activelang_' . $keylang . session_id() ),
					'url_no' => NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=' . $op . '&amp;keylang=' . $keylang . '&amp;activelang=0&amp;checksess=' . md5( 'activelang_' . $keylang . session_id() )
				) );

				$xtpl->parse( 'main.loop.allow_sitelangs' );
			}
			else
			{
				$xtpl->parse( 'main.loop.allow_sitelangs_note' );
			}
		}
		else
		{
			$xtpl->assign( 'INSTALL', NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=' . $op . '&amp;keylang=' . $keylang . '&amp;checksess=' . md5( $keylang . session_id() ) );
			$xtpl->parse( 'main.loop.setup_new' );
		}
	}
	$xtpl->parse( 'main.loop' );
}
$contents .= "</table>\n";

$contents .= "<div class=\"quote\" style=\"width:97.5%;\">\n";
$contents .= "<blockquote><span>" . $lang_module['nv_data_note'] . "</span></blockquote>\n";
$contents .= "</div>\n";
$contents .= "<div class=\"clear\"></div>\n";

$xtpl->parse( 'main' );
$contents = $xtpl->text( 'main' );

include NV_ROOTDIR . '/includes/header.php';
echo nv_admin_theme( $contents );
include NV_ROOTDIR . '/includes/footer.php';

?>