<?php

/*
 * Eqiat
 * Easy QTI Item Authoring Tool
 */

/*------------------------------------------------------------------------------
(c) 2010 JISC-funded EASiHE project, University of Southampton
Licensed under the Creative Commons 'Attribution non-commercial share alike' 
licence -- see the LICENCE file for more details
------------------------------------------------------------------------------*/

// get sorted array of items
// those which have titles in alphabetical order
// then others in identifier order
$items = array();
if (isset($_SESSION["items"])) foreach ($_SESSION["items"] as $id => $item)
	$items[] = $item;
usort($items, array("QTIAssessmentItem", "compare_by_modification_date"));
$items = array_reverse($items);

ob_start();
?>
$(document).ready(function() {
	if (location.hash.substr(0, 6) == "#item_")
		$(location.hash).addClass("highlight");

	$(".deleteitem").click(function(e) {
		e.preventDefault();

		if (!confirm("Are you sure you want to delete this item?"))
			return;

		jQuery.ajax({
			"cache": false,
			"context": $(this).parents("tr:first").get(0),
			"data": { "async": true, "qtiid": $(this).parents("tr:first").attr("id").split("_").splice(1).join("_") },
			"error": function(xhr, text, error) { console.error(error); },
			"success": function() { $(this.context).remove(); },
			"type": "POST",
			"url": "<?php echo SITEROOT_WEB; ?>?page=deleteAssessmentItem"
		});
	});
});
<?php
$GLOBALS["headerjs"] = ob_get_clean();
include "htmlheader.php";
?>

<h2>Main menu</h2>
<dl>
	<dt><a href="<?php echo SITEROOT_WEB; ?>?page=newAssessmentItem">New assessment item</a></dt>
	<dd>Write a new assessment item</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>?page=uploadAssessmentItem">Upload an existing assessment item</a></dt>
	<dd>Upload an existing assessment item so it can be edited and packaged</dd>

	<dt><a href="#itemlist">Item list</a></dt>
	<dd>A list of items currently in memory for your session</dd>
</dl>

<h3 id="itemlist">Item list</h3>
<?php if (empty($items)) { ?>
	<p>No items are in memory for this session</p>
<?php } else { ?>
	<p>There follows a list of items you are currently editing. They are not 
	permanent (they will disappear after <?php echo round(ini_get("session.gc_maxlifetime") / 60); ?> 
	minutes of inactivity) so ensure you save them manually before logging 
	off.</p>
	<table>
		<tr>
			<th>Modified</th>
			<th>Item type</th>
			<th>Title</th>
			<th>Description</th>
			<th>Keywords</th>
			<th>Status</th>
			<th>Actions</th>
		</tr>
		<?php $i = 0; foreach ($items as $item) { $odd = $i++ % 2; ?>
			<tr class="row<?php echo $odd; ?>" id="item_<?php echo $item->getQTIID(); ?>">
				<td><?php if (!is_null($item->getModified())) echo friendlydate_html($item->getModified()); ?></td>
				<td><?php echo htmlspecialchars($item->itemTypePrint()); ?></td>
				<td><?php echo is_null($item->data("title")) ? "[untitled]" : htmlspecialchars($item->data("title")); ?></td>
				<td><?php echo htmlspecialchars($item->data("description")); ?></td>
				<td><?php $keywords = $item->getKeywords(); if (!empty($keywords)) { ?><ul><?php foreach($keywords as $keyword) { ?><li><?php echo htmlspecialchars($keyword); ?></li><?php } ?></ul><?php } ?></td>
				<td class="<?php echo (!$item->getQTI() || count($item->getErrors())) ? "error" : (count($item->getWarnings()) ? "warning" : "good"); ?>">
					<?php if (is_null($item->data("title"))) { ?>
						Unfinished
					<?php } else { ?>
						<?php echo count($item->getErrors()); ?> error<?php echo plural($item->getErrors()); ?>
						<br />
						<?php echo count($item->getWarnings()); ?> warning<?php echo plural($item->getWarnings()); ?>
					<?php } ?>
				</td>
				<td><ul>
					<li><a href="<?php echo SITEROOT_WEB; ?>?page=editAssessmentItem&amp;qtiid=<?php echo $item->getQTIID(); ?>">Edit</a></li>
					<?php if ($item->getQTI() && !count($item->getErrors())) { ?>
						<li><a href="<?php echo SITEROOT_WEB; ?>?page=previewAssessmentItem&amp;qtiid=<?php echo $item->getQTIID(); ?>">Preview</a></li>
						<li><a href="<?php echo SITEROOT_WEB; ?>?page=downloadAssessmentItemXML&amp;qtiid=<?php echo $item->getQTIID(); ?>">Download XML</a></li>
						<li><a href="<?php echo SITEROOT_WEB; ?>?page=downloadAssessmentItemContentPackage&amp;qtiid=<?php echo $item->getQTIID(); ?>">Download content package</a></li>
					<?php } ?>
					<li><a href="<?php echo SITEROOT_WEB; ?>?page=cloneAssessmentItem&amp;qtiid=<?php echo $item->getQTIID(); ?>">Clone</a></li>
					<li><a class="deleteitem" href="<?php echo SITEROOT_WEB; ?>?page=deleteAssessmentItem&amp;qtiid=<?php echo $item->getQTIID(); ?>">Delete</a></li>
				</ul></td>
			</tr>
		<?php } ?>
	</table>
<?php } ?>

<?php include "htmlfooter.php"; ?>
