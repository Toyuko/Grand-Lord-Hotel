<?php
/**
 * Display the right model of the template according to the url
 */
require("common/lib.php");
require("common/define.php");

if(MAINTENANCE_MODE == 0 || (isset($_SESSION['user']) && ($_SESSION['user']['type'] == "administrator" || $_SESSION['user']['type'] == "manager"))){

    $uri = preg_split("#[\\\\/]#", REQUEST_URI);
    $err404 = false;
    $pages = array();
    $articles = array();
    $sys_pages = array();
    $parents = array();
    $ishome = false;
    $results = false;
    $page = null;
    $article = null;
    $page_id = 0;
    $article_id = 0;
    $page_alias = "";
    $article_alias = "";

    $result_page = $db->query("SELECT * FROM pm_page WHERE (checked = 1 OR checked = 0) AND lang = ".LANG_ID." ORDER BY rank");
    if($result_page !== false){
        foreach($result_page as $i => $row){

            $alias = $row['alias'];
            
            if($row['home'] != 1){
                $alias = text_format($alias);
                $currequest = $alias;
            }else{
                $alias = "";
                $currequest = "";
            }
            
            $alias = trim(LANG_ALIAS.$alias, "/\\");
            $currequest = trim(LANG_ALIAS.$currequest, "/\\");
            
            $row['alias'] = $alias;
            $row['currequest'] = $currequest;
            if($row['system'] == 1) $sys_pages[$row['page_model']] = $row;
            
            if($row['home'] == 1) $homepage = $row;
            
            $row['articles'] = array();
            
            $pages[$row['id']] = $row;
            $parents[$row['id_parent']][] = $row['id'];
        }
    }
    
    define("URL_404", DOCBASE.$sys_pages['404']['alias']);

    $count_uri = count($uri);

    if((LANG_ENABLED && $count_uri == 1) || (!LANG_ENABLED && $uri[0] == "")) $ishome = true;
    else{
        $i = (LANG_ENABLED) ? 1 : 0;
        $page_alias = trim(LANG_ALIAS.$uri[$i], "/\\");
        if($count_uri > $i+2) err404();
        if(isset($uri[$i+1])) $article_alias = $uri[$i+1];
    }
    
    $result_article = $db->query("SELECT * FROM pm_article WHERE id_page IN(".implode(",", array_keys($pages)).") AND (checked = 1 OR checked = 3) AND (publish_date IS NULL || publish_date <= ".time().") AND (unpublish_date IS NULL || unpublish_date > ".time().") AND lang = ".LANG_ID." ORDER BY CASE WHEN publish_date IS NOT NULL THEN publish_date ELSE add_date END DESC");
    if($result_article !== false){
        foreach($result_article as $i => $row){
            
            $alias = $row['alias'];
            
            $full_alias = $pages[$row['id_page']]['alias']."/".text_format($alias);
            $row['alias'] = $full_alias;
            $articles[$row['id']] = $row;
            
            $pages[$row['id_page']]['articles'][$row['id']] = $row;
            
            //current article
            if($article_alias != "" && $article_alias == $alias){
                $article_id = $row['id'];
                $article = $row;
            }
        }
    }

    $found = false;
    if(!empty($pages)){
        foreach($pages as $row){
            
            //current page
            if(($ishome && $row['home'] == 1) XOR ($row['alias'] != "" && $page_alias == $row['alias'])){
                $page_id = $row['id'];
                if($article_alias == "" && $row['currequest'] != REQUEST_URI) err404();
                else{
                    $page = $row;
                    $found = true;
                }
            }
        }
    }
    
    if($found === false) err404();

    $title_tag = $page['title_tag'];

    if($article_alias != "" && $page['article_model'] == "") err404();
    if($article_alias == "" && $page['page_model'] == "") err404();

    if($article_alias != "") $page_model = $page['article_model'];
    else $page_model = $page['page_model'];

    $menus['main'] = array();
    $menus['footer'] = array();
    $result_menu = $db->query("SELECT * FROM pm_menu WHERE checked = 1 AND lang = ".LANG_ID." ORDER BY rank");
    if($result_menu !== false){
        foreach($result_menu as $row){
            
            if(($row['item_type'] == "page" && isset($pages[$row['id_item']]) && $pages[$row['id_item']]['checked'] == 1)
            || ($row['item_type'] == "article" && isset($articles[$row['id_item']]))
            || $row['item_type'] == "url"
            || $row['item_type'] == "none"){
                
                $href = get_nav_url($row);
                $row['href'] = $href;
            
                if($row['main'] == 1) $menus['main'][$row['id']] = $row;
                if($row['footer'] == 1) $menus['footer'][$row['id']] = $row;
            }
        }
    }
                        
    $breadcrumbs = array();
    $id_parent = $page['id_parent'];
    while(isset($parents[$id_parent])){
        if($id_parent > 0 && $id_parent != $homepage['id']){
            $breadcrumbs[] = $id_parent;
            $id_parent = $pages[$id_parent]['id_parent'];
        }else break;
    }

    $breadcrumbs = array_reverse($breadcrumbs);

    $page_model = SYSBASE."templates/".TEMPLATE."/models/".str_replace("_","/",$page_model).".php";
    
    if(is_file($page_model)) include($page_model);

    require(SYSBASE."templates/".TEMPLATE."/common/footer.php");
}else{
    header("HTTP/1.1 503 Service Temporarily Unavailable");
    if(DOCBASE.REQUEST_URI != DOCBASE) header("Location: ".DOCBASE);
    require(SYSBASE."templates/".TEMPLATE."/maintenance.php");
}

if(ob_get_level() > 0) ob_flush();
