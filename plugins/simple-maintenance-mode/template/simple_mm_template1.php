<?php


$simple_mm_option_string = '_simple_mm__settings';
$simple_mm_options = get_option($simple_mm_option_string);
$simple_mm_option_start = '_simple_mm__general_';


?>


<html>
<head>
<title>Maintenance Mode</title>
<!-- version 1.03 -->

<style type="text/css">


/*
body{
	 background-attachment: fixed;
    background-clip: border-box;
    background-color: <?php echo $simple_mm_options[$simple_mm_option_start.'background_color'] ?>;
   <?php if($simple_mm_options['$simple_mm_option_start.background-image']){ ?>  background-image: url("<?php echo $simple_mm_options[$simple_mm_option_start.'background-image'] ?>"); <?php } //endif?>
    background-origin: padding-box;
    background-position: center top;
    background-repeat: no-repeat;
    background-size: cover;
}

img#bg {
  position:fixed;
  top:0;
  left:0;
  width:100%;
  height:100%;
  z-index:-1
} 
*/
 #message{
 	font-family: 'arial';
 	font-size:28px;
 	color:<?php echo $simple_mm_options[$simple_mm_option_start.'message_font_color'] ?>;
 	
 }
 
 
 <?php // if($simple_mm_options[$simple_mm_option_start.'show_info_background_color']){ ?>
 /*
 #content_area {
    background: none repeat scroll 0 0 padding-box rgba(0, 0, 0, 0.8);
    border-radius: 4px 4px 4px 4px;
    box-shadow: 0 0 6px rgba(0, 0, 0, 0.25);
}
*/
<?php //} ?>



</style>

</head>
<body>


<table align="center">
		<table height="400" width="600" align="center" valign="center" id="content_area">
		<tr>
			<td>
				<?php if($simple_mm_options[$simple_mm_option_start.'logo']){ ?>
				<tr valign="bottom">
					<td align="center"><img src="<?php echo $simple_mm_options[$simple_mm_option_start.'logo'] ?>"></td>
				</tr>
				<?php } //endif ?>
				<tr valign="bottom">
					<td align="center" id="message"><h1><?php echo stripslashes($simple_mm_options[$simple_mm_option_start.'message']) ?></h1></td>
				</tr>
				
				
			</td>
		</tr>
	</table>
</table>

  
</body>
</html>
