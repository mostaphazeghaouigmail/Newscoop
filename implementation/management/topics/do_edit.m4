B_HTML
INCLUDE_PHP_LIB(<*..*>)
B_DATABASE

CHECK_BASIC_ACCESS
CHECK_ACCESS(<*ManageTopics*>)

B_HEAD
	X_EXPIRES
	X_TITLE(<*Changing topic name*>)
<?php  if ($access == 0) { ?>dnl
	X_AD(<*You do not have the right to change topic name.*>)
<?php  } ?>dnl
E_HEAD

<?php  if ($access) { ?>dnl
B_STYLE
E_STYLE

B_BODY

<?php 
	todefnum('IdCateg');
	todefnum('EdCateg');
	todef('cName');
?>dnl
B_HEADER(<*Changing topic name*>)
B_HEADER_BUTTONS
X_HBUTTON(<*Home*>, <*home.php*>)
X_HBUTTON(<*Logout*>, <*logout.php*>)
E_HEADER_BUTTONS
E_HEADER

<?php 
	$correct= 1;
	$created= 0;
	query ("SELECT * FROM Topics WHERE Id=$EdCateg AND LanguageId = 1", 'q_cat');
    if ($NUM_ROWS) {
	fetchRow($q_cat);
?>dnl

B_CURRENT
X_CURRENT(<*Topic*>, <*<B><?php  pgetHVar($q_cat,'Name'); ?></B>*>)
E_CURRENT

<P>
B_MSGBOX(<*Changing topic name*>)
	X_MSGBOX_TEXT(<*
<?php 
	$cName=trim($cName);
	 if ($cName == "" || $cName== " ") {
		$correct=0; ?>dnl
		<LI><?php  putGS('You must complete the $1 field.','<B>'.getGS('Name').'</B>'); ?></LI>
	<?php  }

	if ($correct) {
		query ("UPDATE Topics SET Name='".decS($cName)."' WHERE Id=$EdCateg AND LanguageId = 1");
		$created= ($AFFECTED_ROWS > 0);
	}

	if ($created) { ?>dnl
		<LI><?php  putGS('The topic $1 has been successfuly updated.',"<B>".encHTML(decS($cName))."</B>"); ?></LI>
		X_AUDIT(<*143*>, <*getGS('Topic $1 updated',$cName)*>)
	<?php  } else {

	if ($correct != 0) { ?>dnl
		<LI><?php  putGS('The topic name could not be updated.'); ?></LI>
	<?php  }
    } ?>dnl
*>)
	B_MSGBOX_BUTTONS
<?php  if ($correct && $created) { ?>dnl
		REDIRECT(<*Done*>, <*Done*>, <*X_ROOT/topics/index.php?IdCateg=<?php p($IdCateg);?>*>)
<?php  } else { ?>
		REDIRECT(<*OK*>, <*OK*>, <*X_ROOT/topics/edit.php?IdCateg=<?php  p($IdCateg); ?>&EdCateg=<?php  p($EdCateg); ?>*>)
<?php  } ?>dnl
	E_MSGBOX_BUTTONS
E_MSGBOX
<P>
<?php  } else { ?>dnl
<BLOCKQUOTE>
	<LI><?php  putGS('No such topic.'); ?></LI>
</BLOCKQUOTE>
<?php  } ?>dnl

X_HR
X_COPYRIGHT
E_BODY
<?php  } ?>dnl

E_DATABASE
E_HTML
