<?php
require_once($_SERVER['DOCUMENT_ROOT']."/$ADMIN_DIR/articles/article_common.php");
require_once($_SERVER['DOCUMENT_ROOT']."/$ADMIN_DIR/javascript_common.php");
require_once($_SERVER['DOCUMENT_ROOT']."/classes/ArticleType.php");

global $Campsite;

if (!$g_user->hasPermission('ManageIssue') || !$g_user->hasPermission('AddArticle')) {
    camp_html_display_error(getGS("You do not have the right to import legacy archves."));
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT']."/$ADMIN_DIR/articles/article_content_lib.php");

// Whether form was submitted
$f_save = Input::Get('f_save', 'string', '', true);

// The article location dropdowns cause this page to reload,
// so we need to preserve the state with each refresh.
$f_article_type = Input::Get('f_article_type', 'string', '', true);
$f_article_language_id = Input::Get('f_article_language_id', 'int', 0, true);

// For choosing the article location.
$f_publication_id = Input::Get('f_publication_id', 'int', 0, true);
$f_issue_number = Input::Get('f_issue_number', 'int', 0, true);
$f_section_number = Input::Get('f_section_number', 'int', 0, true);

// Whether articles must be overwritten
$f_overwrite_articles = Input::Get('f_overwrite_articles', 'string', '', true);

if ($f_save) {
    if (isset($_FILES["f_input_file"])) {
        switch($_FILES["f_input_file"]['error']) {
	case 0: // UPLOAD_ERR_OK
	    break;
	case 1: // UPLOAD_ERR_INI_SIZE
	case 2: // UPLOAD_ERR_FORM_SIZE
	    camp_html_display_error(getGS("The file exceeds the allowed max file size."), null, true);
	    break;
	case 3: // UPLOAD_ERR_PARTIAL
	    camp_html_display_error(getGS("The uploaded file was only partially uploaded. This is common when the maximum time to upload a file is low in contrast with the file size you are trying to input. The maximum input time is specified in 'php.ini'"), null, true);
	    break;
	case 4: // UPLOAD_ERR_NO_FILE
	    camp_html_display_error(getGS("You must select a file to upload."), null, true);
	    break;
	case 6: // UPLOAD_ERR_NO_TMP_DIR
	case 7: // UPLOAD_ERR_CANT_WRITE
	    camp_html_display_error(getGS("There was a problem uploading the file."), null, true);
	    break;
	}
    } else {
        camp_html_display_error(getGS("The file exceeds the allowed max file size."), null, true);
    }
 }

if (!Input::IsValid()) {
    camp_html_display_error(getGS('Invalid input: $1', Input::GetErrorString()), $_SERVER['REQUEST_URI']);
    exit;
}

$articleTypes = ArticleType::GetArticleTypes();
$allPublications = Publication::GetPublications();
$allLanguages = Language::GetLanguages();

$isValidXMLFile = false;
if ($f_save && !empty($_FILES['f_input_file'])) {
    if (file_exists($_FILES['f_input_file']['tmp_name'])) {
        if (!($buffer = @file_get_contents($_FILES['f_input_file']['tmp_name']))) {
	    camp_html_display_error(getGS("File could not be read."));
	    exit;
	}
	$xml = new SimpleXMLElement($buffer);
	if (!is_object($xml)) {
	    camp_html_display_error(getGS("File is not a valid XML file."));
            exit;
	}

	if (!isset($xml->article->name)) {
	    camp_html_add_msg(getGS("Bad format in XML file."));
	}

	$isValidXMLFile = true;
	@unlink($_FILES['f_input_file']['tmp_name']);
    } else {
        camp_html_display_error(getGS("File does not exist."));
        exit;
    }
} elseif ($f_save) {
    camp_html_add_msg(getGS("File could not be uploaded."));
}


if ($isValidXMLFile) {
    if ($f_publication_id > 0) {
        $publicationObj = new Publication($f_publication_id);
	if (!$publicationObj->exists()) {
	    camp_html_display_error(getGS('Publication does not exist.'));
	    exit;
	}
	if ($f_issue_number > 0) {
	    $issueObj = new Issue($f_publication_id, $f_article_language_id, $f_issue_number);
	    if (!$issueObj->exists()) {
	        camp_html_display_error(getGS('Issue does not exist.'));
		exit;
	    }

	    if ($f_section_number > 0) {
	        $sectionObj = new Section($f_publication_id, $f_issue_number, $f_article_language_id, $f_section_number);
		if (!$sectionObj->exists()) {
		    camp_html_display_error(getGS('Section does not exist.'));
		    exit;
		}
	    }
	}
    }

    // Loads article data from XML file into database
    $errorMessages = array();
    foreach ($xml->article as $article) {
        $existingArticles = Article::GetByName((string) $article->name,
					       $f_publication_id,
					       $f_issue_number,
					       $f_section_number,
					       $f_article_language_id);
	// There is already an article with same name and language
	if (count($existingArticles) > 0) {
	    $existingArticle = array_pop($existingArticles);
	    // Is overwrite articles false? then skip and process next article
	    if ($f_overwrite_articles == 'N') {
	        $errorMessages[] = 'Article "<i>'.(string) $article->name.'</i>" '
		    .'already exist and was not overwritten.';
	        continue;
	    }
	}

	if (isset($existingArticle) && $existingArticle->exists()) {
	    $articleObj = $existingArticle;
	} else {
	    $articleObj = new Article($f_article_language_id);
	    $articleName = (string) $article->name;
	    $articleObj->create($f_article_type, $articleName, $f_publication_id, $f_issue_number, $f_section_number);
	}

	// Checks whether article was successfully created
	if (!$articleObj->exists()) {
	    camp_html_display_error(getGS('Article could not be created.'), $BackLink);
	    exit;
	}

	$articleTypeObj = $articleObj->getArticleData();
	$dbColumns = $articleTypeObj->getUserDefinedColumns();
	$articleTypeFields = array();
	foreach ($dbColumns as $dbColumn) {
	    $field = strtolower($dbColumn->getName());
	    if (!isset($article->articleTypeFields->$field)) {
	        $errorMessages[] = 'The article type field "<i>'
		    .$dbColumn->getName()
		    .'</i>" does not match any field from XML input file.';
		continue;
	    }

	    // Replace <span class="subhead"> ... </span> with
	    // <!** Title> ... <!** EndTitle>
	    $text = preg_replace_callback("/(<\s*span[^>]*class\s*=\s*[\"']campsite_subhead[\"'][^>]*>|<\s*span|<\s*\/\s*span\s*>)/i", "TransformSubheads", (string) $article->articleTypeFields->$field);

	    // Replace <a href="campsite_internal_link?IdPublication=1&..."
	    // ...> ... </a>
	    // with <!** Link Internal IdPublication=1&...> ... <!** EndLink>
	    $text = preg_replace_callback("/(<\s*a\s*(((href\s*=\s*[\"']campsite_internal_link[?][\w&=;]*[\"'])|(target\s*=\s*['\"][_\w]*['\"]))[\s]*)*[\s\w\"']*>)|(<\s*\/a\s*>)/i", "TransformInternalLinks", $text);

	    // Replace <img id=".." src=".." alt=".." title=".." align="..">
	    // with <!** Image [image_template_id] align=".." alt=".." sub="..">
	    $idAttr = "(id\s*=\s*\"[^\"]*\")";
	    $srcAttr = "(src\s*=\s*\"[^\"]*\")";
	    $altAttr = "(alt\s*=\s*\"[^\"]*\")";
	    $subAttr = "(title\s*=\s*\"[^\"]*\")";
	    $alignAttr = "(align\s*=\s*\"[^\"]*\")";
	    $widthAttr = "(width\s*=\s*\"[^\"]*\")";
	    $heightAttr = "(height\s*=\s*\"[^\"]*\")";
	    $otherAttr = "(\w+\s*=\s*\"[^\"]*\")*";
	    $pattern = "/<\s*img\s*(($idAttr|$srcAttr|$altAttr|$subAttr|$alignAttr|$widthAttr|$heightAttr|$otherAttr)\s*)*\/>/i";
	    $text = preg_replace_callback($pattern, "TransformImageTags", $text);
	    $articleTypeObj->setProperty($dbColumn->getName(), $text);
	}

	// Updates the article author
	$authorObj = new Author((string) $article->author);
	if (!$authorObj->exists()) {
	    $authorData = Author::ReadName((string) $article->author);
	    $authorObj->create($authorData);
	}
	$articleObj->setAuthorId($authorObj->getId());

	// Updates the article
	$articleObj->setCreatorId($g_user->getUserId());
	$articleObj->setKeywords((string) $article->keywords);
    }

    camp_html_add_msg(getGS("Legacy archive imported."), "ok");
}


// Gets all issues
$allIssues = array();
if ($f_publication_id > 0) {
    $allIssues = Issue::GetIssues($f_publication_id, $f_article_language_id, null, null, null, array("LIMIT" => 300, "ORDER BY" => array("Number" => "DESC")));
    // Automatically selects the issue if there is only one
    if (count($allIssues) == 1) {
        $tmpIssue = camp_array_peek($allIssues);
	$f_issue_number = $tmpIssue->getIssueNumber();
    }
}

// Gets all the sections
$allSections = array();
if ($f_issue_number > 0) {
    $destIssue = new Issue($f_publication_id);
    $allSections = Section::GetSections($f_publication_id, $f_issue_number, $f_article_language_id, null, null, array("ORDER BY" => array("Number" => "DESC")));
    // Automatically selects the section if there is only one
    if (count($allSections) == 1) {
        $tmpSection = camp_array_peek($allSections);
        $f_section_number = $tmpSection->getSectionNumber();
    }
}

$crumbs = array();
$crumbs[] = array(getGS("Actions"), "");
$crumbs[] = array(getGS("Import legacy archive"), "");
echo camp_html_breadcrumbs($crumbs);

?>

<?php camp_html_display_msgs(); ?>

<p>
<form name="import_archive" enctype="multipart/form-data" method="POST" action="la_import.php" onsubmit="return <?php camp_html_fvalidate(); ?>;">
<table border="0" cellspacing="0" cellpadding="6" class="table_input">
<tr>
  <td colspan="2">
    <b><?php putGS("Import legacy archive"); ?></b>
    <hr noshade size="1" color="black">
  </td>
</tr>
<tr>
  <td valign="top">
    <table>
    <tr>
      <td align="right"><?php putGS("Article Type"); ?>:</td>
      <td>
        <select name="f_article_type" id="f_article_type" class="input_select" alt="select" emsg="<?php putGS('You must select an article type.'); ?>">
        <option value=""><?php putGS('---Select article type---'); ?></option>
        <?php
        foreach ($articleTypes as $article_type) {
            $articleType = new ArticleType($article_type);
            camp_html_select_option($articleType->getTypeName(), $f_article_type, $articleType->getTypeName());
        }
        ?>
        </select>
      </td>
    </tr>
    <tr>
      <td align="right"><?php putGS("Language"); ?>:</td>
      <td>
        <select name="f_article_language_id" id="f_article_language_id" class="input_select" alt="select" emsg="<?php putGS('You must select an article language.'); ?>" onchange="if (this.options[this.selectedIndex].value != <?php p($f_article_language_id); ?>) {this.form.submit();}">
        <option value=""><?php putGS('---Select language---'); ?></option>
        <?php
        foreach ($allLanguages as $language) {
            camp_html_select_option($language->getLanguageId(), $f_article_language_id, $language->getName());
        }
        ?>
        </select>
      </td>
    </tr>
    <tr>
      <td align="right"><?php putGS("Publication"); ?>:</td>
      <td>
        <?php if ($f_article_language_id > 0 && count($allPublications) > 1) { ?>
        <select name="f_publication_id" id="f_publication_id" class="input_select" alt="select" emsg="<?php putGS('You must select a publication.'); ?>" onchange="if (this.options[this.selectedIndex].value != <?php p($f_publication_id); ?>) {this.form.submit();}">
        <option value=""><?php putGS('---Select publication---'); ?></option>
        <?php
        foreach ($allPublications as $publication) {
            camp_html_select_option($publication->getPublicationId(), $f_publication_id, $publication->getName());
        }
        ?>
        </select>
        <?php } else { ?>
        <select class="input_select" disabled><option><?php putGS('No publications'); ?></option></select>
        <?php } ?>
      </td>
    </tr>
    <tr>
      <td align="right"><?php putGS("Issue"); ?>:</td>
      <td>
        <?php if (($f_publication_id > 0) && (count($allIssues) >= 1)) { ?>
        <select name="f_issue_number" id="f_issue_number" class="input_select" onchange="if (this.options[this.selectedIndex].value != <?php p($f_issue_number); ?>) { this.form.submit(); }">
        <option value="0"><?php putGS('---Select issue---'); ?></option>
        <?php
            foreach ($allIssues as $issue) {
                camp_html_select_option($issue->getIssueNumber(), $f_issue_number, $issue->getName());
            }
        ?>
        </select>
        <?php } else { ?>
        <select class="input_select" disabled><option><?php putGS('No issues'); ?></option></select>
        <?php } ?>
        &nbsp;
        (<?php putGS('Optional'); ?>)
      </td>
    </tr>
    <tr>
      <td align="right"><?php putGS("Section"); ?>:</td>
      <td>
        <?php if (($f_issue_number > 0) && (count($allSections) >= 1)) { ?>
        <select name="f_section_number" id="f_section_number" class="input_select">
        <option value=""><?php putGS('---Select section---'); ?></option>
        <?php
            foreach ($allSections as $section) {
                camp_html_select_option($section->getSectionNumber(), $f_section_number, $section->getName());
            }
        ?>
        </select>
        <?php } else { ?>
        <select class="input_select" disabled><option><?php putGS('No sections'); ?></option></select>
        <?php } ?>
        &nbsp;
        (<?php putGS('Optional'); ?>)
      </td>
    </tr>
    <tr>
      <td align="right"><?php putGS("Overwrite existing articles"); ?>?:</td>
      <td>
        <input type="radio" name="f_overwrite_articles" value="Y" <?php if ($f_overwrite_articles == 'Y') p("checked"); ?> /> <?php putGS("Yes"); ?>
        <input type="radio" name="f_overwrite_articles" value="N" <?php if ($f_overwrite_articles == 'N' || $f_overwrite_articles == '') p("checked"); ?> /> <?php putGS("No"); ?>
      </td>
    </tr>
    <tr>
      <td align="right"><?php putGS("Input File"); ?>:</td>
      <td>
        <input type="file" name="f_input_file" id="f_input_file" size="40" class="input_text" alt="file|xml|0" emsg="<?php putGS('You must select a XML input file.'); ?>" />
      </td>
    </tr>
    </table>
  </td>
</tr>
<tr>
  <td colspan="2" align="center">
    <hr noshade size="1" color="black">
    <input type="submit" name="f_save" value="<?php putGS('Save'); ?>" class="button" />
  </td>
</tr>
</table><br />

<?php if (sizeof($errorMessages) > 0) { ?>
<table border="0" cellspacing="0" cellpadding="6" class="table_input">
<tr>
  <td>
    <b><?php putGS("Error List"); ?></b>
    <hr noshade size="1" color="black">
  </td>
</tr>
<tr>
  <td>
    <?php
    foreach ($errorMessages as $error) {
        print($error . "<br />");
    }
    ?>
  </td>
</tr>
</table>
<?php } ?>

<?php camp_html_copyright_notice(); ?>