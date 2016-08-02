<?php
/**
 * Short code codex_languages for Languages Templates
 *
 * @package HelpHub
 */

/**
 * Short code for Codex Language link.
 *
 * @example     [languages en="Version 4.6" ja="version 4.6"]
 * @param       language indicator. Refer
 *              https://codex.wordpress.org/Multilingual_Codex#Language_Cross_Reference
 */
function codex_languages_func( $atts ) {
  $str = '<p class="language-links"><a href="https://codex.wordpress.org/Multilingual_Codex" title="Multilingual Codex" class="mw-redirect">Languages</a>: <strong class="selflink">English</strong>';
  $lang_table = array(
         array("Arabic", "العربية","ar","https://codex.wordpress.org/ar:%1s"),
         array("Azerbaijani", "Azərbaycanca","azr","https://codex.wordpress.org/azr:%1s"),
         array("Azeri", "آذری","azb","https://codex.wordpress.org/azb:%1s"),
         array("Bulgarian", "Български","bg","https://codex.wordpress.org/bg:%1s"),
         array("Bengali", "বাংলা","bn","https://codex.wordpress.org/bn:%1s"),
         array("Bosnian", "Bosanski","bs","https://codex.wordpress.org/bs:%1s"),
         array("Catalan", "Catalan","ca","https://codex.wordpress.org/ca:%1s"),
         array("Czech", "Čeština","cs","https://codex.wordpress.org/cs:%1s"),
         array("Danish", "Dansk","da","https://codex.wordpress.org/da:%1s"),
         array("German", "Deutsch","de","https://codex.wordpress.org/de:%1s"),
         array("Greek", "Greek","el","http://wpgreece.org/%1s"),
         array("Spanish", "Español","es","https://codex.wordpress.org/es:%1s"),
         array("Finnish", "suomi","fi","https://codex.wordpress.org/fi:%1s"),
         array("French", "Français","fr","https://codex.wordpress.org/fr:%1s"),
         array("Croatian", "Hrvatski","hr","https://codex.wordpress.org/hr:%1s"),
         array("Hebrew", "עברית","he","https://codex.wordpress.org/he:%1s"),
         array("Hindi", "हिन्दी","hi","https://codex.wordpress.org/hi:%1s"),
         array("Hungarian", "Magyar","hu","https://codex.wordpress.org/hu%1s"),
         array("Indonesian", "Bahasa Indonesia","id","http://id.wordpress.net/codex/%1s"),
         array("Italian", "Italiano","it","https://codex.wordpress.org/it:%1s"),
         array("Japanese", "日本語","ja","http://wpdocs.sourceforge.jp/%1s"),
         array("Georgian", "ქართული","ka","https://codex.wordpress.org/ka:%1s"),
         array("Khmer", "ភាសា​ខ្មែរ","km","http://khmerwp.com/%1s"),
         array("Korean", "한국어","ko","http://wordpress.co.kr/codex/%1s"),
         array("Lao", "ລາວ","lo","http://www.laowordpress.com/%1s"),
         array("Macedonian", "Македонски","mk","https://codex.wordpress.org/mk:%1s"),
         array("Moldavian", "Română","md","https://codex.wordpress.org/md:%1s"),
         array("Mongolian", "Mongolian","mn","https://codex.wordpress.org/mn:%1s"),
         array("Myanmar", "myanmar","mya","http://www.myanmarwp.com/%1s"),
         array("Dutch", "Nederlands","nl","https://codex.wordpress.org/nl:%1s"),
         array("Persian", "فارسی","fa","http://codex.wp-persian.com/%1s"),
         array("Farsi", "فارسی","fax","http://www.isawpi.ir/wiki/%1s"),
         array("Polish", "Polski","pl","https://codex.wordpress.org/pl:%1s"),
         array("Portuguese_Português","pt","https://codex.wordpress.org/pt:%1s"),
         array("Brazilian Portuguese","Português do Brasil","pt-br","https://codex.wordpress.org/pt-br:%1s"),
         array("Romanian", "Română","ro","https://codex.wordpress.org/ro:%1s"),
         array("Russian", "Русский","ru","https://codex.wordpress.org/ru:%1s"),
         array("Serbian", "Српски","sr","https://codex.wordpress.org/sr:%1s"),
         array("Slovak", "Slovenčina","sk","https://codex.wordpress.org/sk:%1s"),
         array("Slovenian", "Slovenščina","sl","https://codex.wordpress.org/sl:%1s"),
         array("Albanian", "Shqip","sq","https://codex.wordpress.org/al:%1s"),
         array("Swedish", "Svenska","sv","http://wp-support.se/dokumentation/%1s"),
         array("Tamil", "Tamil","ta","http://codex.wordpress.com/ta:%1s"),
         array("Telugu", "తెలుగు","te","https://codex.wordpress.org/te:%1s"),
         array("Thai", "ไทย","th","http://codex.wordthai.com/%1s"),
         array("Turkish", "Türkçe","tr","https://codex.wordpress.org/tr:%1s"),
         array("Ukrainian", "Українська","uk","https://codex.wordpress.org/uk:%1s"),
         array("Vietnamese", "Tiếng Việt","vi","https://codex.wordpress.org/vi:%1s"),
         array("Chinese", "zh-cn","https://codex.wordpress.org/zh-cn:%1s"),
         array("Chinese (Taiwan)","中文(繁體)","zh-tw","https://codex.wordpress.org/zh-tw:%1s"),
         array("Kannada", "ಕನ್ನಡ","kn","https://codex.wordpress.org/kn:%1s"));
  $shortcode_params = array();
  foreach( $lang_table as $lang) {
    $shortcode_params[ $lang[2] ] = null;
  }
  $args = shortcode_atts( $shortcode_params, $atts );
  $i = 0;
  foreach ($args as $key => $value) {
    if ($value != null) {
      $str .= sprintf( ' • <a class="external text" href="' . $lang_table[$i][3] . '">' . $lang_table[$i][1] . '</a>', $value );
    }
    $i++;
  }
  $str .= "</p>";
  return $str;
}
add_shortcode( 'codex_languages', 'codex_languages_func' );
