<?php debug_backtrace() || die ("Direct access not permitted"); ?>
<footer>
    <section id="mainFooter">
        <div class="container" id="footer">
            <div class="row">
                <div class="col-md-4">
                    <?php displayWidgets("footer_col_1", $page_id); ?>
                </div>
                <div class="col-md-4">
                    <?php displayWidgets("footer_col_2", $page_id); ?>
                </div>
                <div class="col-md-4">
                    <?php displayWidgets("footer_col_3", $page_id); ?>
                </div>
            </div>
        </div>
    </section>
    <div id="footerRights">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>
                        &copy; <?php echo date("Y"); ?>
                        <?php echo OWNER." ".$texts['ALL_RIGHTS_RESERVED']; ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <p class="text-right">
                        <a href="<?php echo DOCBASE; ?>feed/" target="_blank" title="<?php echo $texts['RSS_FEED']; ?>"><i class="fa fa-rss"></i></a>
                        <?php
                        foreach($menus['footer'] as $nav_id => $nav){ ?>
                            <a href="<?php echo $nav['href']; ?>" title="<?php echo $nav['title']; ?>"><?php echo $nav['name']; ?></a>
                            &nbsp;&nbsp;
                            <?php
                        } ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>
<a href="#" id="toTop"><i class="fa-angle-up"></i></a>
</body>
</html>
<?php
$_SESSION['msg_error'] = "";
$_SESSION['msg_success'] = "";
$_SESSION['msg_notice'] = ""; ?>
