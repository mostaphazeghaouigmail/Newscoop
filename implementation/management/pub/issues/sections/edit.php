<?php
require_once($_SERVER['DOCUMENT_ROOT']. "/$ADMIN_DIR/pub/issues/sections/section_common.php");
require_once($_SERVER['DOCUMENT_ROOT']. '/classes/Template.php');

list($access, $User) = check_basic_access($_REQUEST);
if (!$access) {
	header("Location: /$ADMIN/logout.php");
	exit;
}
if (!$User->hasPermission('ManageSection')) {
	CampsiteInterface::DisplayError(getGS("You do not have the right to change section details"));	
	exit;
}
$Pub = Input::Get('Pub', 'int', 0);
$Issue = Input::Get('Issue', 'int', 0);
$Language = Input::Get('Language', 'int', 0);
$Section = Input::Get('Section', 'int', 0);


$publicationObj =& new Publication($Pub);
$issueObj =& new Issue($Pub, $Language, $Issue);
$sectionObj =& new Section($Pub, $Issue, $Language, $Section);
$templates =& Template::GetAllTemplates(array('ORDER BY' => array('Level' => 'ASC', 'Name' => 'ASC')));

## added by sebastian
if (function_exists ("incModFile")) {
  incModFile ();
}

$topArray = array('Pub' => $publicationObj, 'Issue' => $issueObj, 'Section' => $sectionObj);
CampsiteInterface::ContentTop(getGS("Configure section"), $topArray);

?>
<P>
<CENTER>
<TABLE BORDER="0" CELLSPACING="0" CELLPADDING="6" CLASS="table_input" ALIGN="CENTER">
<FORM NAME="dialog" METHOD="POST" ACTION="do_edit.php" >
<TR>
	<TD COLSPAN="2">
		<B><?php  putGS("Configure section"); ?></B>
		<HR NOSHADE SIZE="1" COLOR="BLACK">
	</TD>
</TR>

<TR>
	<TD ALIGN="RIGHT" ><?php  putGS("Name"); ?>:</TD>
	<TD>
		<INPUT TYPE="TEXT" class="input_text" NAME="cName" SIZE="32" MAXLENGTH="64" value="<?php  p(htmlspecialchars($sectionObj->getName())); ?>">
 	</TD>
</TR>

<TR>
	<TD ALIGN="RIGHT" ><?php  putGS("Section Template"); ?>:</TD>
	<TD>
		<SELECT NAME="cSectionTplId" class="input_select">
		<OPTION VALUE="0">---</OPTION>
		<?php 
		foreach ($templates as $template) {
			pcomboVar($template->getTemplateId(),$sectionObj->getSectionTemplateId(),$template->getName());
		}
		?>
		</SELECT>
	</TD>
</TR>

<TR>
	<TD ALIGN="RIGHT" ><?php  putGS("Article Template"); ?>:</TD>
	<TD>
		<SELECT NAME="cArticleTplId" class="input_select">
		<OPTION VALUE="0">---</OPTION>
		<?php 
		foreach ($templates as $template) {
			pcomboVar($template->getTemplateId(),$sectionObj->getArticleTemplateId(),$template->getName());
		}
		?>
		</SELECT>
	</TD>
</TR>

<TR>
	<TD ALIGN="RIGHT" ><?php  putGS("URL Name"); ?>:</TD>
	<TD>
	<INPUT TYPE="TEXT" class="input_text" NAME="cShortName" SIZE="32" MAXLENGTH="32" value="<?php  p(htmlspecialchars($sectionObj->getShortName())); ?>">
	</TD>
</TR>

<TR>
	<TD ALIGN="RIGHT" ><?php  putGS("Subscriptions"); ?>:</TD>
	<TD>
		<SELECT NAME="cSubs" class="input_select">
	   	<OPTION VALUE="n"> --- </OPTION>
	   	<OPTION VALUE="a"><?php  putGS("Add section to all subscriptions."); ?></OPTION>
	   	<OPTION VALUE="d"><?php  putGS("Delete section from all subscriptions."); ?></OPTION>
	  	</SELECT>
	</TD>
</TR>

 <?php
 ?>

<TR>
	<TD COLSPAN="2">
		<DIV ALIGN="CENTER">
	  	<INPUT TYPE="HIDDEN" NAME="Pub" VALUE="<?php  p($Pub); ?>">
	  	<INPUT TYPE="HIDDEN" NAME="Issue" VALUE="<?php  p($Issue); ?>">
	  	<INPUT TYPE="HIDDEN" NAME="Language" VALUE="<?php  p($Language); ?>">
	  	<INPUT TYPE="HIDDEN" NAME="Section" VALUE="<?php  p($Section); ?>">
	  	<INPUT TYPE="submit" class="button" NAME="Save" VALUE="<?php  putGS('Save changes'); ?>">
	  	<INPUT TYPE="button" class="button" NAME="Cancel" VALUE="<?php  putGS('Cancel'); ?>" ONCLICK="location.href='/admin/pub/issues/sections/?Pub=<?php  p($Pub); ?>&Issue=<?php  p($Issue); ?>&Language=<?php  p($Language); ?>'">
	 	</DIV>
	</TD>
</TR>
</FORM>
</TABLE>
</CENTER>
<P>

<?php CampsiteInterface::CopyrightNotice(); ?>