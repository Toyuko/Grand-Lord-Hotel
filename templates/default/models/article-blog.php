<?php
if($article_alias == "") err404();

if($article_id > 0){
    
    $title_tag = $article['title']." - ".$title_tag;
    $page_title = $article['title'];
    $page_subtitle = $article['subtitle'];
    $page_alias = $article['alias'];
    $publish_date = $article['publish_date'];
    $edit_date = $article['edit_date'];
    
    if(is_null($publish_date)) $publish_date = $article['add_date'];
    if(is_null($edit_date)) $edit_date = $publish_date;
    
    $result_article_file = $db->query("SELECT * FROM pm_article_file WHERE id_item = ".$article_id." AND checked = 1 AND lang = ".DEFAULT_LANG." AND type = 'image' AND file != '' ORDER BY rank LIMIT 1");
    if($result_article_file !== false && $db->last_row_count() > 0){
        
        $row = $result_article_file->fetch();
        
        $file_id = $row['id'];
        $filename = $row['file'];
        
        if(is_file(SYSBASE."medias/article/medium/".$file_id."/".$filename))
            $page_img = getUrl(true)."/medias/article/medium/".$file_id."/".$filename;
    }
    
    $result_tag = $db->query("SELECT * FROM pm_tag WHERE id IN (".$article['tags'].") AND checked = 1 AND lang = ".LANG_ID." ORDER BY rank");
    if($result_tag !== false){
        $nb_tags = $db->last_row_count();
        
        $article_tags = "";
        foreach($result_tag as $i => $row){
            $tag_id = $row['id'];
            $tag_value = $row['value'];

            $article_tags .= $tag_value;
            if($i+1 < $nb_tags) $article_tags .= ", ";
        }
    }
    
}else err404();

check_URI(DOCBASE.$page_alias);

/* ==============================================
 * CSS AND JAVASCRIPT USED IN THIS MODEL
 * ==============================================
 */
$javascripts[] = DOCBASE."js/plugins/owl-carousel/owl.carousel.min.js";
$javascripts[] = DOCBASE."js/plugins/sharrre/jquery.sharrre.min.js";

$stylesheets[] = array("file" => DOCBASE."js/plugins/owl-carousel/owl.carousel.css", "media" => "all");
$stylesheets[] = array("file" => DOCBASE."js/plugins/owl-carousel/owl.theme.css", "media" => "all");

$stylesheets[] = array("file" => DOCBASE."js/plugins/star-rating/css/star-rating.min.css", "media" => "all");
$javascripts[] = DOCBASE."js/plugins/star-rating/js/star-rating.min.js";

require(getFromTemplate("common/send_comment.php", false));

require(getFromTemplate("common/header.php", false)); ?>

<article id="page">
    <?php include(getFromTemplate("common/page_header.php", false)); ?>
    
    <div id="content" class="pt30 pb30">
        <div class="container">

            <div class="alert alert-success" style="display:none;"></div>
            <div class="alert alert-danger" style="display:none;"></div>
            
            <div class="row">
                <?php
                $widgetsLeft = getWidgets("left", $page_id);
                $widgetsRight = getWidgets("right", $page_id);
                
                if(!empty($widgetsLeft)){ ?>
                    <div class="col-sm-3">
                        <?php displayWidgets("left", $page_id); ?>
                    </div>
                    <?php
                } ?>
                
                <div class="col-sm-<?php if(!empty($widgetsLeft) && !empty($widgetsRight)) echo 6; elseif(!empty($widgetsLeft) || !empty($widgetsRight)) echo 9; else echo 12; ?>">
                    <article class="mb30" itemscope itemtype="http://schema.org/BlogPosting" itemprop="mainEntity">
                        <link itemprop="mainEntityOfPage" href="<?php echo getUrl(); ?>">
                        <header>
                            <h1 itemprop="name headline"><?php echo $article['title']; ?></h1>
                            <?php
                            $nb_comments = 0;
                            $item_type = "article";
                            $item_id = $article_id;
                            $allow_comment = $article['comment'];
                            $allow_rating = $article['rating'];
                            if($allow_comment == 1){
                                $result_comment = $db->query("SELECT * FROM pm_comment WHERE id_item = ".$item_id." AND item_type = '".$item_type."' AND checked = 1 ORDER BY add_date DESC");
                                if($result_comment !== false)
                                    $nb_comments = $db->last_row_count();
                            } ?>
                            <div class="mb10 mt10 labels" dir="ltr">
                                <span class="label label-default"><i class="fa fa-thumb-tack"></i> <time itemprop="dateCreated datePublished dateModified" datetime="<?php echo date("c", $publish_date); ?>"><?php echo (!RTL_DIR) ? strftime(DATE_FORMAT, $publish_date) : strftime("%F", $publish_date); ?></time></span>
                                <span class="label label-default"><i class="fa fa-comment"></i> <?php echo $nb_comments." ".mb_strtolower($texts['COMMENTS'], "UTF-8"); ?></span>
                                <?php
                                $result_users = $db->query("SELECT * FROM pm_user WHERE id IN(".$article['users'].")");
                                if($result_users->execute() != false){
                                    foreach($result_users as $user_article){ ?>
                                        <span class="label label-default"><i class="fa fa-user"></i> <span itemprop="creator author publisher"><?php echo $user_article['login']; ?></span></span>
                                        <?php
                                    }
                                }
                                if(isset($article_tags) && $article_tags != ""){ ?>
                                    <span class="label label-default"><i class="fa fa-tags"></i>
                                        <span itemprop="about"><?php echo $article_tags; ?></span>
                                    </span>
                                    <?php
                                } ?>
                            </div>
                            <?php
                            $result_article_file = $db->query("SELECT * FROM pm_article_file WHERE id_item = ".$article_id." AND checked = 1 AND lang = ".DEFAULT_LANG." AND type = 'image' AND file != '' ORDER BY rank");
                            if($result_article_file !== false){
                                
                                foreach($result_article_file as $i => $row){
                                
                                    $file_id = $row['id'];
                                    $filename = $row['file'];
                                    $label = $row['label'];
                                    
                                    $realpath = SYSBASE."medias/article/big/".$file_id."/".$filename;
                                    $thumbpath = DOCBASE."medias/article/big/".$file_id."/".$filename;
                                    
                                    if(is_file($realpath)){
                                        $size = getimagesize($realpath);
                                        $w = $size[0];
                                        $h = $size[1]; ?>
                                        <figure itemprop="image" itemscope itemtype="http://schema.org/ImageObject">
                                            <img alt="<?php echo $label; ?>" src="<?php echo $thumbpath; ?>" class="img-responsive full-framed mb10" itemprop="url" height="<?php echo $h; ?>" width="<?php echo $w; ?>">
                                            <meta itemprop="height" content="<?php echo $h; ?>">
                                            <meta itemprop="width" content="<?php echo $w; ?>">
                                        </figure>
                                        <?php
                                    }
                                }
                            } ?>
                        </header>
                        
                        <section itemprop="articleBody">
                            <?php echo $article['text']; ?>
                        </section>
                        
                        <?php
                        $short_text = strtrunc(strip_tags($article['text']), 155);
                        $site_url = getUrl(); ?>
                       
                        <div id="twitter"></div>
                        <div id="facebook"></div>
                        <div id="pinterest" data-media="<?php if(isset($page_img)) echo $page_img; ?>" data-text="<?php echo $short_text; ?>"></div>
                        <div id="googleplus" data-curl="<?php echo DOCBASE."js/plugins/sharrre/sharrre.php"; ?>"></div>
                        
                        <div class="clearfix"></div>
                    </article>
                    
                    <?php include(getFromTemplate("common/comments.php", false)); ?>
                </div>
                <?php
                if(!empty($widgetsRight)){ ?>
                    <div class="col-sm-3">
                        <?php displayWidgets("right", $page_id); ?>
                    </div>
                    <?php
                } ?>
            </div>
        </div>
    </div>
</article>
