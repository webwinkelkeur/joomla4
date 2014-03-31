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
