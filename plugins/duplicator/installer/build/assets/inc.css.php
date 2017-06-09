<style>
	body {font-family:Verdana,Arial,sans-serif; font-size:13px;}
	fieldset {border:1px solid silver; border-radius:5px; padding:10px}
	h3 {margin:1px; padding:1px; font-size:13px;}
	a {color:#222}
	a:hover{color:gray}
	input[type=text], input[type=password], select {width:97%; border-radius:2px; border:1px solid silver; padding:4px;  font-family:Verdana,Arial,sans-serif;}
	select {padding-left:0; width:99%}
	select:disabled {background:#EBEBE4}
	input.readonly {background-color:#efefef;}

	/* ============================
	COMMON VIEWS
     ============================ */
	div#content {border:1px solid #CDCDCD; width:750px; min-height:550px; margin:auto; margin-top:18px; border-radius:5px; box-shadow:0 8px 6px -6px #333; font-size:13px}
	div#content-inner {padding:10px 25px; min-height:550px}
	form.content-form {min-height:550px; position:relative; line-height:17px}
    div.status-badge-pass {border-radius:4px; color:#fff; padding:0 4px 0 4px;  font-size:12px; min-width:30px; text-align:center; background-color:#418446;display:inline-block }
    div.status-badge-fail {border-radius:4px; color:#fff; padding:0 4px 0 4px;  font-size:12px; min-width:30px; text-align:center; background-color:maroon; display:inline-block}
	
	/* WIZARD STEPS */
	table.dupx-header {border-top-left-radius:5px; border-top-right-radius:5px; width:100%; box-shadow:0 5px 3px -3px #999;	background-color:#F1F1F1; font-weight:bold;}
    .dupx-header-version {white-space:nowrap; color:#777; font-size:11px; font-style:italic; text-align:right;  padding:0 15px 5px 0; line-height:14px; font-weight:normal;}
	.dupx-header-version a {color:#555;}
    div.dupx-logfile-link {float:right; font-weight:normal; font-size:11px; font-style:italic}
	div#progress-area {padding:5px; margin:150px 0 0 0px; text-align:center;}
	div#ajaxerr-data {padding:5px; height:350px; width:99%; border:1px solid silver; border-radius:5px; background-color:#efefef; font-size:13px; overflow-y:scroll; line-height:24px}

    /*TITLE HEADERS */
    div.hdr-main {font-size:22px; padding:0 0 5px 0; border-bottom:1px solid #D3D3D3; font-weight:bold; margin:15px 0 20px 0;}
	div.hdr-main span.step {color:#DB4B38}
	div.hdr-sub1 {font-size:18px; margin-bottom:5px;border:1px solid #D3D3D3;padding:7px; background-color:#f9f9f9; font-weight:bold; border-radius:4px}
	div.hdr-sub1 a {cursor:pointer; text-decoration: none !important}
	div.hdr-sub1:hover {cursor:pointer; background-color:#f1f1f1; border:1px solid #dcdcdc; }
	div.hdr-sub1:hover a{color:#000}
	div.hdr-sub2 {font-size:15px; padding:2px 2px 2px 0; border-bottom:1px solid #D3D3D3; font-weight:bold; margin-bottom:5px; border:none}
	div.hdr-sub3 {font-size:15px; padding:2px 2px 2px 0; border-bottom:1px solid #D3D3D3; font-weight:bold; margin-bottom:5px;}
	div.hdr-sub4 {font-size:15px; padding:7px; border:1px solid #D3D3D3;; font-weight:bold; background-color:#e9e9e9;}
	div.hdr-sub4:hover  {background-color:#dfdfdf; cursor:pointer}

    /* BUTTONS */
	div.dupx-footer-buttons {position:absolute; bottom:10px; padding:10px;  right:0}
	div.dupx-footer-buttons  input:hover, button:hover {border:1px solid #000}
	div.dupx-footer-buttons input[disabled=disabled]{background-color:#F4F4F4; color:silver; border:1px solid silver;}
	div.dupx-footer-buttons button[disabled]{background-color:#F4F4F4; color:silver; border:1px solid silver;}
    button.default-btn, input.default-btn {
		cursor:pointer; color:#fff; font-size:16px; border-radius:5px;	padding:8px 25px 6px 25px;
	    background-color:#13659C; border:1px solid gray;
	}
    table.dupx-opts {width:100%; border:0px;}
	table.dupx-opts td{white-space:nowrap; padding:3px;}
	table.dupx-opts td:first-child{width:125px; font-weight: bold}
	table.dupx-advopts td:first-child{width:125px; font-weight:bold}
	table.dupx-advopts td label{min-width:60px; display:inline-block; cursor:pointer}

    .dupx-pass {display:inline-block; color:green;}
	.dupx-fail {display:inline-block; color:#AF0000;}
	.dupx-notice {display:inline-block; color:#000;}
	div.dupx-ui-error {padding-top:2px; font-size:13px; line-height: 20px}

	 /*Dialog Info */
	div.dlg-serv-info {line-height:22px; font-size:12px; margin:0}
	div.dlg-serv-info div.info-txt {text-align: center; font-size:11px; font-style:italic}
	div.dlg-serv-info label {display:inline-block; width:175px; font-weight: bold}
    div.dlg-serv-info div.hdr {background-color: #dfdfdf; font-weight: bold; margin-top:5px; border-radius: 4px; padding:2px 5px 2px 5px; border: 1px solid silver; font-size: 16px}
	div#modal-window div.modal-title {background-color:#D0D0D0}
	div#modal-window div.modal-text {padding-top:10px !important}
	div.archive-onlydb {color:#DB4B38; font-weight:normal; position:absolute; top:5px; right:20px; font-style:italic; font-size:11px}
	
	/* ======================================
	STEP 1 VIEW
    ====================================== */
	table.s1-archive-local {width:100%}
    table.s1-archive-local td {padding:4px 4px 4px 4px}
	table.s1-archive-local td:first-child {font-weight:bold; width:55px}
    div#s1-area-sys-setup {padding:5px 0 0 10px}
	div#s1-area-sys-setup div.info-top {text-align:center; font-style:italic; font-size:11px; padding:0 5px 5px 5px}
	table.s1-checks-area {width:100%; margin:0; padding:0}
	table.s1-checks-area td.title {font-size:16px; width:100%}
	table.s1-checks-area td.title small {font-size:11px; font-weight:normal}
	table.s1-checks-area td.toggle {font-size:11px; margin-right:7px; font-weight:normal}

	div.s1-reqs {background-color:#efefef; border:1px solid silver; border-radius:5px; margin-top:-5px}
	div.s1-reqs div.header {background-color:#E0E0E0; color:#000;  border-bottom: 1px solid silver; padding:2px; font-weight:bold }
	div.s1-reqs div.notice {background-color:#E0E0E0; color:#000; text-align:center; font-size:12px; border-bottom: 1px solid silver; padding:2px; font-style:italic}
	div.s1-reqs div.status {float:right; border-radius:4px; color:#fff; padding:0 4px 0 4px; margin:4px 5px 0 0; font-size:12px; min-width:30px; text-align:center; font-weight:bold}
	div.s1-reqs div.pass {background-color:green;}
	div.s1-reqs div.fail {background-color:maroon;}
	div.s1-reqs div.title {padding:4px; font-size:13px;}
	div.s1-reqs div.title:hover {background-color:#dfdfdf; cursor:pointer}
	div.s1-reqs div.info {padding:8px 8px 20px 8px; background-color:#fff; display:none; line-height:18px; font-size: 12px}
	div.s1-reqs div.info a {color:#485AA3;}
    div.s1-archive-failed-msg {padding:15px; border:1px dashed silver; font-size: 12px; border-radius:5px}
    div.s1-err-msg {padding:8px;  border:1px dashed #999; margin:20px 0 20px 0px; border-radius:5px; color:maroon}

    /*Terms and Notices*/
	div#s1-warning-check label{cursor:pointer;}
    div#s1-warning-msg {padding:5px;font-size:12px; color:#333; line-height:14px;font-style:italic; overflow-y:scroll; height:150px; border:1px solid #dfdfdf; background:#fff; border-radius:3px}
	div#s1-warning-check {padding:3px; font-size:14px; font-weight:normal;}
    input#accept-warnings {height: 17px; width:17px}
	
    /* ======================================
	STEP 2 VIEW
    ====================================== */
	/*Toggle Buttons */
	div.s2-btngrp {text-align:center; margin:0 auto 10px auto}
	div.s2-btngrp input[type=button] {font-size:14px; padding:6px; width:120px; border:1px solid silver;  cursor:pointer}
	div.s2-btngrp input[type=button]:first-child {border-radius:5px 0 0 5px; margin-right:-2px}
	div.s2-btngrp input[type=button]:last-child {border-radius:0 5px 5px 0; margin-left:-4px}
	div.s2-btngrp input[type=button].active {background-color:#13659C; color:#fff;}
	div.s2-btngrp input[type=button].in-active {background-color:#E4E4E4; }
	div.s2-btngrp input[type=button]:hover {border:1px solid #999}

	div.s2-modes {padding:0px 15px 0 0px;}
	div#s2-dbconn {margin:auto; text-align:center; margin:15px 0 10px 0px}
	input.s2-small-btn {height:25px; border:1px solid gray; border-radius:3px; cursor:pointer}
    table.s2-opts-dbhost td {padding:0; margin:0}
	input#s2-dbport-btn { width:80px}
	div.s2-db-test small{display:block; font-style:italic; color:#333; padding:3px 2px 5px 2px; border-bottom:1px dashed silver; margin-bottom:10px; text-align: center }
	table.s2-db-test-dtls {text-align: left; margin: auto}
	table.s2-db-test-dtls td:first-child {font-weight: bold}
	div#s2-dbconn-test-msg {font-size:12px}
	div#s2-dbconn-status {border:1px solid silver; border-radius:3px; background-color:#f9f9f9; padding:2px 5px; margin-top:10px; height:175px; overflow-y: scroll}
	div#s2-dbconn-status div.warn-msg {text-align: left; padding:5px; margin:10px 0 10px 0}
	div#s2-dbconn-status div.warn-msg b{color:maroon}

	/*cPanel Tab */
	div#s2-cpnl-pane {display: none; min-height: 190px;}
	div.s2-gopro {color: black; margin-top:10px; padding:0 20px 10px 20px; border: 1px solid silver; background-color:#F6F6F6; border-radius: 4px}
	div.s2-gopro h2 {text-align: center; margin:10px}
	div.s2-gopro small {font-style: italic}
	
	/*Advanced Options & Warning Area*/
	div#s2-area-adv-opts label {cursor: pointer}
	div#s2-warning {padding:5px;font-size:12px; color:gray; line-height:12px;font-style:italic; overflow-y:scroll; height:150px; border:1px solid #dfdfdf; background-color:#fff; border-radius:3px}
	div#s2-warning-check {padding:5px; font-size:12px; font-weight:normal; font-style:italic;}
    div#s2-warning-check label {cursor: pointer; line-height: 14px}
	div#s2-warning-emptydb {display:none; color:#AF2222; margin:2px 0 0 0; font-size: 11px}
	table.s2-advopts label.radio {width:50px; display:inline-block}

	/* ======================================
	STEP 3 VIEW
    ====================================== */
	table.s3-table-inputs {width:100%; border:0px;}
	table.s3-table-inputs td{white-space:nowrap; padding:2px;}
    table.s3-table-inputs td:first-child{font-weight: bold; width:125px}
	div#s3-adv-opts {margin-top:5px; }
	div.s3-allnonelinks {font-size:11px; float:right;}

	/* password indicator */
	.top_testresult{font-weight:bold;	font-size:11px; color:#222;	padding:1px 1px 1px 4px; margin:4px 0 0 0px; width:495px; dislay:inline-block}
	.top_testresult span{margin:0;}
	.top_shortPass{background:#edabab; border:1px solid #bc0000;display:block;}
	.top_badPass{background:#edabab;border:1px solid #bc0000;display:block;}
	.top_goodPass{background:#ffffe0; border:1px solid #e6db55;	display:block;}
	.top_strongPass{background:#d3edab;	border:1px solid #73bc00; display:block;}

	/* ======================================
	STEP 4 VIEW
	====================================== */
	div.s4-final-title {color:#BE2323;}
	div.s4-connect {font-size:12px; text-align:center; font-style:italic; position:absolute; bottom:10px; padding:10px; width:100%; margin-top:20px}
	table.s4-report-results,
	table.s4-report-errs {border-collapse:collapse; border:1px solid #dfdfdf; }
	table.s4-report-errs  td {text-align:center; width:33%}
	table.s4-report-results th, table.s4-report-errs th {background-color:#efefef; padding:0px; font-size:13px; padding:0px}
	table.s4-report-results td, table.s4-report-errs td {padding:0px; white-space:nowrap; border:1px solid #dfdfdf; text-align:center; font-size:12px}
	table.s4-report-results td:first-child {text-align:left; font-weight:bold; padding-left:3px}
	div.s4-err-title {width:100%; background-color: #dfdfdf; font-weight: bold; margin:-5px 0 15px 0; padding:3px 0 1px 3px; border-radius: 4px; font-size:13.5px}

	div.s4-err-msg {padding:8px;  display:none; border:1px dashed #999; margin:10px 0 20px 0px; border-radius:5px;}
	div.s4-err-msg div.content{padding:5px; font-size:11px; line-height:17px; max-height:125px; overflow-y:scroll; border:1px solid silver; margin:3px;  }
	div.s4-err-msg div.info-error{padding:7px; background-color:#EAA9AA; border:1px solid silver; border-radius:5px; font-size:12px; line-height:16px }
	div.s4-err-msg div.info-notice{padding:7px; background-color:#FCFEC5; border:1px solid silver; border-radius:5px; font-size:12px; line-height:16px;}
	table.s4-final-step {width:100%;}
	table.s4-final-step td {padding:5px 15px 5px 5px}
	table.s4-final-step td:first-child {white-space:nowrap;}
	div.s4-go-back {border-bottom:1px dotted #dfdfdf; border-top:1px dotted #dfdfdf; margin:auto; text-align:center; font-size: 12px}
	a.s4-final-btns {display: block; width:135; padding:5px; line-height: 1.4; background-color:#F1F1F1; border:1px solid silver;
		color: #000; box-shadow: 5px 5px 5px -5px #949494; text-decoration: none; text-align: center; border-radius: 4px;
	}
	a.s4-final-btns:hover {background-color: #dfdfdf;}
	div.s4-gopro-btn {text-align:center; font-size:14px; margin:auto; width:200px; font-style: italic; font-weight:bold}
	div.s4-gopro-btn a{color:green}


	/* PARSLEY:Overrides*/
	input.parsley-error, textarea.parsley-error, select.parsley-error {
	  color:#B94A48 !important;  background-color:#F2DEDE !important; border:1px solid #EED3D7 !important;
	}
	ul.parsley-errors-list {margin:1px 0 0 -40px; list-style-type:none; font-size:10px}

	/* ============================
	STEP 5 HELP
	============================	*/
	div.help-target {float:right; font-size:11px}
	div#main-help a.help-target {display:block; margin:5px}
	div#main-help sup {font-size:11px; font-weight:normal; font-style:italic; color:blue}
	div.help-online {text-align:center; font-size:18px; padding:10px 0 0 0; line-height:24px}
	div.help {color:#555; font-style:italic; font-size:11px; padding:4px; border-top:1px solid #dfdfdf}
	div.help-page {padding:5px 0 0 5px}
	div.help-page fieldset {margin-bottom:25px}
    div#main-help {font-size:13px; line-height:17px}
	div#main-help h2 {background-color:#F1F1F1; border:1px solid silver; border-radius:4px; padding:10px; margin:26px 0 8px 0; font-size:22px; }
	div#main-help h3 {border-bottom:1px solid silver; padding:8px; margin:4px 0 8px 0; font-size:20px}
    div#main-help span.step {color:#DB4B38}
	table.help-opt {width: 100%; border: none; border-collapse: collapse;  margin:5px 0 0 0;}
	table.help-opt td.section {background-color:#dfdfdf;}
	table.help-opt td, th {padding:7px; border:1px solid silver;}
	table.help-opt td:first-child {font-weight:bold; padding-right:10px; white-space:nowrap}
	table.help-opt th {background: #333; color: #fff;border:1px solid #333; padding:3px}


	<?php if ($GLOBALS['DUPX_DEBUG']) : ?>
		.dupx-debug {display:block; margin:4px 0 30px 0; font-size:11px;}
		.dupx-debug label {font-weight:bold; display:block; margin:6px 0 2px 0}
		.dupx-debug textarea {width:95%; height:100px; font-size:11px}
	<?php else : ?>
		.dupx-debug {display:none}
	<?php endif; ?>

</style>