<?php
DUP_Util::hasCapability('read');

require_once(DUPLICATOR_PLUGIN_PATH . '/assets/js/javascript.php');
require_once(DUPLICATOR_PLUGIN_PATH . '/views/inc.header.php');
?>
<style>
    div.dup-support-all {font-size:13px; line-height:20px}
    div.dup-support-txts-links {width:100%;font-size:14px; font-weight:bold; line-height:26px; text-align:center}
    div.dup-support-hlp-area {width:375px; height:160px; float:left; border:1px solid #dfdfdf; border-radius:4px; margin:10px; line-height:18px;box-shadow: 0 8px 6px -6px #ccc;}
    table.dup-support-hlp-hdrs {border-collapse:collapse; width:100%; border-bottom:1px solid #dfdfdf}
    table.dup-support-hlp-hdrs {background-color:#efefef;}
    div.dup-support-hlp-hdrs {
        font-weight:bold; font-size:17px; height: 35px; padding:5px 5px 5px 10px;
        background-image:-ms-linear-gradient(top, #FFFFFF 0%, #DEDEDE 100%);
        background-image:-moz-linear-gradient(top, #FFFFFF 0%, #DEDEDE 100%);
        background-image:-o-linear-gradient(top, #FFFFFF 0%, #DEDEDE 100%);
        background-image:-webkit-gradient(linear, left top, left bottom, color-stop(0, #FFFFFF), color-stop(1, #DEDEDE));
        background-image:-webkit-linear-gradient(top, #FFFFFF 0%, #DEDEDE 100%);
        background-image:linear-gradient(to bottom, #FFFFFF 0%, #DEDEDE 100%);
    }
    div.dup-support-hlp-hdrs div {padding:5px; margin:4px 20px 0px -20px;  text-align: center;}
    div.dup-support-hlp-txt{padding:10px 4px 4px 4px; text-align:center}
</style>


<div class="wrap dup-wrap dup-support-all">
	
    <?php duplicator_header(__("Help", 'duplicator')) ?>
    <hr size="1" />

    <div style="width:800px; margin:auto; margin-top: 20px">
        <table>
            <tr>
                <td style="width:70px"><i class="fa fa-question-circle fa-5x"></i></td>
                <td valign="top" style="padding-top:10px; font-size:13px">
                    <?php
                    _e("Migrating WordPress is a complex process and the logic to make all the magic happen smoothly may not work quickly with every site.  With over 30,000 plugins and a very complex server eco-system some migrations may run into issues.  This is why the Duplicator includes a detailed knowledgebase that can help with many common issues.  Resources to additional support, approved hosting, and alternatives to fit your needs can be found below.", 'duplicator');
                    ?>
                </td>
            </tr>
        </table>
        <br/><br/>

        <!-- HELP LINKS -->
        <div class="dup-support-hlp-area">
            <div class="dup-support-hlp-hdrs">
                <i class="fa fa-cube fa-2x pull-left"></i>
                <div><?php _e('Knowledgebase', 'duplicator') ?></div>
            </div>
            <div class="dup-support-hlp-txt">
                <?php _e('Complete Online Documentation', 'duplicator'); ?><br/>
                <select id="dup-support-kb-lnks" style="margin-top:18px; font-size:16px; min-width: 170px">
                    <option> <?php _e('Choose A Section', 'duplicator') ?> </option>
                    <option value="https://snapcreek.com/duplicator/docs/quick-start/"><?php _e('Quick Start', 'duplicator') ?></option>
                    <option value="https://snapcreek.com/duplicator/docs/guide/"><?php _e('User Guide', 'duplicator') ?></option>
                    <option value="https://snapcreek.com/duplicator/docs/faqs-tech/"><?php _e('FAQs', 'duplicator') ?></option>
                    <option value="https://snapcreek.com/duplicator/docs/changelog/?lite"><?php _e('Change Log', 'duplicator') ?></option>
                </select>
            </div>
        </div>

        <!-- ONLINE SUPPORT -->
        <div class="dup-support-hlp-area">
            <div class="dup-support-hlp-hdrs">
                <i class="fa fa-lightbulb-o fa-2x pull-left"></i>
                <div><?php _e('Online Support', 'duplicator') ?></div>
            </div>
            <div class="dup-support-hlp-txt">
                <?php _e("Get Help From IT Professionals", 'duplicator'); ?> 
                <br/>
                <div class="dup-support-txts-links" style="margin:10px 0 10px 0">
                    <button class="button  button-primary button-large" onclick="Duplicator.OpenSupportWindow();return false;">
						<?php _e('Get Support!', 'duplicator') ?>
					</button> <br/>
                </div>	
				<small>Pro Users <a href="https://snapcreek.com/ticket" target="_blank">Support Here</a></small>
            </div>
        </div> 
        <br style="clear:both" /><br/><br/>


        <!-- APPROVED HOSTING -->
        <div class="dup-support-hlp-area">

            <div class="dup-support-hlp-hdrs">
                <i class="fa fa-bolt fa-2x pull-left"></i>
                <div><?php _e('Approved Hosting', 'duplicator') ?></div>
            </div>			
            <div class="dup-support-hlp-txt">
                <?php _e('Servers That Work With Duplicator', 'duplicator'); ?>
                <br/><br/>
                <div class="dup-support-txts-links">
                    <button class="button button-primary button-large" onclick="window.open('https://snapcreek.com/duplicator/docs/faqs-tech/#faq-resource-040-q', 'litg');"><?php _e('Trusted Providers!', 'duplicator') ?></button> &nbsp; 
                </div>
            </div>
        </div>

        <!-- ALTERNATIVES -->
        <div class="dup-support-hlp-area">

            <div class="dup-support-hlp-hdrs">
                <i class="fa fa-code-fork fa-2x pull-left"></i>
                <div><?php _e('Alternatives', 'duplicator') ?></div>
            </div>			
            <div class="dup-support-hlp-txt">
                <?php _e('Other Commercial Resources', 'duplicator'); ?>
                <br/><br/>
                <div class="dup-support-txts-links">
                    <button class="button button-primary button-large" onclick="window.open('https://snapcreek.com/duplicator/docs/faqs-tech/#faq-resource-050-q', 'litg');"><?php _e('Pro Solutions!', 'duplicator') ?></button> &nbsp; 
                </div>
            </div>
        </div>
    </div>
</div><br/><br/><br/><br/>

<script>
    jQuery(document).ready(function($) {

        Duplicator.OpenSupportWindow = function() {
            var url = 'https://snapcreek.com/duplicator/docs/faqs-tech/#faq-resource';
            window.open(url, 'litg');
        }

        //ATTACHED EVENTS
        jQuery('#dup-support-kb-lnks').change(function() {
            if (jQuery(this).val() != "null")
                window.open(jQuery(this).val())
        });

    });
</script>