<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<script>
(function() {
    <?php foreach($settings as $name => $value): ?>
    <?php echo $name; ?> = <?php echo json_encode($value); ?>;
    <?php endforeach; ?>
    var js = document.createElement("script"); js.type = "text/javascript";
    js.async = true; js.src = "//www.webwinkelkeur.nl/js/sidebar.js";
    var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(js, s);
})();
</script>
