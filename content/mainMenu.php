<?php

// get sorted array of items
// new one at the top
// then those which have titles in alphabetical order
// then others in identifier order
$items = array();
if (isset($_SESSION["items"])) foreach ($_SESSION["items"] as $id => $item) {
	// skip new item for now -- we want that at the top of the list
	if ($id == "new")
		continue;
	$items[] = $item;
}
usort($items, array("QTIAssessmentItem", "compare_by_title"));
if (isset($_SESSION["items"]["new"]))
	$items[] = $_SESSION["items"]["new"];
$items = array_reverse($items);

?>
<?php include "htmlheader.php"; ?>

<script type="text/javascript">
	$(document).ready(function() {
		if (location.hash.substr(0, 6) == "#item_")
			$(location.hash).addClass("highlight");
	});
</script>

<h2>Main menu</h2>
<dl>
	<dt><a href="?page=newAssessmentItem">New assessment item</a></dt>
	<dd>
		Write a new assessment item
		<?php if (isset($_SESSION["items"]["new"])) { ?>
			(note that starting a new item will clear <a href="?page=editAssessmentItem&amp;qtiid=new">your current unfinished item</a>)
		<?php } ?>
	</dd>

	<dl><a href="?page=uploadAssessmentItem">Upload an existing assessment item</a></dl>
	<dd>Upload an existing assessment item so it can be edited and packaged</dd>

	<dt><a href="#itemlist">Item list</a><dt>
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
			<th>Status</th>
			<th>Actions</th>
		</tr>
		<?php $i = 0; foreach ($items as $item) { $odd = $i++ % 2; ?>
			<tr class="row<?php echo $odd; ?>" id="item_<?php echo $item->getQTIID(); ?>">
				<td><?php echo friendlydate_html($item->getModified()); ?></td>
				<td><?php echo htmlspecialchars($item->itemTypePrint()); ?></td>
				<td><?php echo htmlspecialchars($item->data("description")); ?></td>
				<td><?php echo $item->getTitle() === false ? "[untitled]" : htmlspecialchars($item->getTitle()); ?></td>
				<td class="<?php echo count($item->getErrors()) ? "error" : (count($item->getWarnings()) ? "warning" : "good"); ?>">
					<?php echo count($item->getErrors()); ?> error<?php echo plural($item->getErrors()); ?>
					<br />
					<?php echo count($item->getWarnings()); ?> warning<?php echo plural($item->getWarnings()); ?>
				</td>
				<td><ul>
					<li><a href="?page=editAssessmentItem&amp;qtiid=<?php echo $item->getQTIID(); ?>">Edit</a></li>
					<?php if (!count($item->getErrors())) { ?>
						<li><a href="?page=previewAssessmentItem&amp;qtiid=<?php echo $item->getQTIID(); ?>">Preview</a></li>
						<li><a href="?page=downloadAssessmentItemXML&amp;qtiid=<?php echo $item->getQTIID(); ?>">Download XML</a></li>
						<li><a href="?page=downloadAssessmentItemContentPackage&amp;qtiid=<?php echo $item->getQTIID(); ?>">Download content package</a></li>
					<?php } ?>
					<li><a href="?page=deleteAssessmentItem&amp;qtiid=<?php echo $item->getQTIID(); ?>" onclick="return confirm('Are you sure you want to delete this item?');">Delete</a></li>
				</ul></td>
			</tr>
		<?php } ?>
	</table>
<?php } ?>

<?php include "htmlfooter.php"; ?>
