<?php
if (!isset($base_path)) {
    $base_path = '';
}
?>
    <script src="<?php echo $base_path; ?>js/main.js"></script>
    <?php if (isset($extra_scripts)) { echo $extra_scripts; } ?>
</body>
</html>
