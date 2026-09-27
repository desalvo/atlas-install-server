<?php
function page_header($path=".") {
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <link rel="shortcut icon" href="<?php echo $path ?>/img/favicon.ico">
  <link rel="stylesheet" type="text/css" href="<?php echo $path ?>/css/ljsf.css">
  <link href="<?php echo $path ?>/css/dropdown/dropdown.css" media="screen" rel="stylesheet" type="text/css" />
  <link href="<?php echo $path ?>/css/dropdown/themes/ljsf.css" media="screen" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" type="text/css" href="<?php echo $path ?>/css/modern.css">
  <script type="text/javascript" src="<?php echo $path ?>/js/jquery-1.9.1.min.js"></script>

  <script type="text/javascript">
    $(function() {
      var moveLeft = 300;
      var moveDown = 10;
      $('a#trigger').hover(function() {
        $('div#help').show();
      }, function() {
        $('div#help').hide();
      });
      $('a#trigger').mousemove(function(e) {
        $('div#help').css('top', e.pageY + moveDown).css('left', e.pageX - moveLeft);
      });
    });
  </script>
<?php } ?>
