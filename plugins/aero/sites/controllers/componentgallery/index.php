<?php
/** @var Aero\Sites\Controllers\ComponentGallery $this */
$blocks       = $this->vars['blocks'];
$themes       = $this->vars['themes'];
$defaultTheme = $this->vars['defaultThemeHandle'];
$previewUrl   = \Backend::url('aero/sites/componentgallery/preview');
$firstBlock   = array_key_first($blocks);
?>
<?php include __DIR__ . '/_gallery.php' ?>
