<?php

/*
 * Question Bank
 */

/*------------------------------------------------------------------------------
(c) 2010 JISC-funded EASiHE project, University of Southampton
Licensed under the Creative Commons 'Attribution non-commercial share alike' 
licence -- see the LICENCE file for more details
------------------------------------------------------------------------------*/

// render a comment
function rendercomment($comment) {
	ob_start();
	?>
	<li>
		<div class="commentmetadata">
			<?php if (!is_null($comment["rating"])) { ?>
				<div class="stars right">
					<div class="on" style="width: <?php echo ($on = 100 * $comment["rating"] / 5); ?>%;"></div>
					<div class="off" style="width: <?php echo 100 - $on; ?>%;"></div>
				</div>
			<?php } ?>
			<p>Posted <?php echo friendlydate_html($comment["posted"]); ?> by <?php if ($comment["userdeleted"]) { ?>deleted user <?php } ?><strong><?php echo htmlspecialchars($comment["user"]); ?></strong></p>
		</div>
		<p><?php echo nl2br(htmlspecialchars($comment["comment"])); ?></p>
	</li>
	<?php
	return ob_get_clean();
}

// actions to set up a new item queue or move the position in the queue
if (isset($_GET["action"])) {
	if (!isset($_SESSION["itemqueue"]) && ($_GET["action"] == "prev" || $_GET["action"] == "next" || $_GET["action"] == "startover" || $_GET["action"] == "comment" || $_GET["action"] == "getcomments"))
		badrequest("no items are in the queue");
	switch ($_GET["action"]) {
		case "getcomments":
			// return HTML list of comments
			$item = getitem($_SESSION["itemqueue"][$_SESSION["itemqueuepos"]]);
			$html = "";
			foreach ($item["comments"] as $comment)
				$html .= rendercomment($comment);
			ok(json_encode(array("html" => $html)), "application/json");
		case "comment":
			// handle post of rating or comment data
			if (!loggedin())
				forbidden();

			if (!isset($_POST["justcomment"]) && !isset($_POST["justrate"]) && !isset($_POST["rateandcomment"]))
				badrequest("didn't get an expected submit action");

			$item = getitem($_SESSION["itemqueue"][$_SESSION["itemqueuepos"]]);
			$comment = null;
			if (isset($_POST["comment"])) {
				$comment = trim($_POST["comment"]);
				if (empty($comment))
					$comment = null;
			}
			$rating = null;
			if (isset($_POST["rating"])) {
				$oldrating = itemrating($item["identifier"]);
				if (!is_null($oldrating))
					badrequest("You have already rated this item since it was last modified");
				if (!is_numeric($_POST["rating"]))
					badrequest("rating must be numeric");
				$rating = intval($_POST["rating"]);
				if ($rating < 0 || $rating > 5)
					badrequest("rating must be an integer from 0 to 5");
			}

			if (is_null($comment) && (isset($_POST["justcomment"]) || isset($_POST["rateandcomment"])))
				badrequest("No comment given");
			if (is_null($rating) && (isset($_POST["justrate"]) || isset($_POST["rateandcomment"])))
				badrequest("No rating given");

			if (!is_null($rating) && $item["user"] == username())
				badrequest("You can't rate your own item");

			if (is_null($rating) && is_null($comment))
				badrequest("nothing to do");

			db()->exec("BEGIN TRANSACTION;");
			if (!is_null($rating))
				if (!@db()->exec("
					INSERT INTO ratings VALUES(
						'" . db()->escapeString(username()) . "',
						'" . db()->escapeString($item["identifier"]) . "',
						$rating,
						" . time() . "
					)
				;"))
					servererror("Sqlite3 error: " . db()->lastErrorMsg());
			if (!is_null($comment))
				if (!@db()->exec("
					INSERT INTO comments VALUES (
						'" . db()->escapeString(username()) . "',
						'" . db()->escapeString($item["identifier"]) . "',
						'" . db()->escapeString($comment) . "',
						" . time() . "
					)
				;"))
					servererror("Sqlite3 error: " . db()->lastErrorMsg());
			if (!db()->exec("COMMIT;"))
				servererror("Sqlite3 error: " . db()->lastErrorMsg());

			if (is_null($comment))
				ok();

			ok(json_encode(array("html" => rendercomment(array(
				"posted"		=>	time(),
				"user"			=>	username(),
				"rating"		=>	$rating,
				"comment"		=>	$comment,
				"userdeleted"	=>	0,
			)))), "application/json");
		case "results":
			// set item queue to current search results
			if (!isset($_SESSION["items"]) || empty($_SESSION["items"]))
				badrequest("no search results");
			$_SESSION["itemqueue"] = $_SESSION["items"];
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "single":
			// set item queue to the single specified item
			if (!isset($_GET["qtiid"]))
				badrequest("no QTI ID specified");
			$_SESSION["itemqueue"] = array($_GET["qtiid"]);
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "shuffle":
			// set item queue to all items in the database in a random order
			$_SESSION["itemqueue"] = array();
			$result = db()->query("SELECT identifier FROM items ORDER BY RANDOM();");
			while ($row = $result->fetchArray(SQLITE3_NUM))
				$_SESSION["itemqueue"][] = $row[0];
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "newest":
			// set item queue to all items in the database from newest to oldest
			$_SESSION["itemqueue"] = array();
			$result = db()->query("SELECT identifier FROM items ORDER BY COALESCE(modified, uploaded) DESC;");
			while ($row = $result->fetchArray(SQLITE3_NUM))
				$_SESSION["itemqueue"][] = $row[0];
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "highestrated":
			// set item queue to all items in the database from highest rated to 
			// lowest
			$_SESSION["itemqueue"] = array();
			$result = db()->query("
				SELECT
					items.identifier,
					AVG(ratings.rating) AS avgrating
				FROM items
				LEFT JOIN ratings
				ON items.identifier=ratings.item
				AND ratings.posted > COALESCE(items.modified, items.uploaded)
				GROUP BY ratings.item
				ORDER BY avgrating DESC
			;");
			while ($row = $result->fetchArray(SQLITE3_NUM))
				$_SESSION["itemqueue"][] = $row[0];
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "unrated":
			// set item queue to all items in the database which haven't been 
			// rated by anyone, from oldest to newest
			$_SESSION["itemqueue"] = array();
			$result = db()->query("
				SELECT items.identifier
				FROM items
				LEFT JOIN ratings
				ON items.identifier=ratings.item
				AND ratings.posted > COALESCE(items.modified, items.uploaded)
				" . (loggedin() ? "WHERE items.user != '" . db()->escapeString(username()) . "'" : "") . "
				GROUP BY ratings.item
				HAVING COUNT(ratings.rating)=0
				ORDER BY COALESCE(items.modified, items.uploaded) ASC
			;");
			while ($row = $result->fetchArray(SQLITE3_NUM))
				$_SESSION["itemqueue"][] = $row[0];
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "unratedbyuser":
			// set item queue to all items in the database which haven't been 
			// rated by anyone, from oldest to newest
			if (!loggedin())
				badrequest("you need to be logged in");
			$_SESSION["itemqueue"] = array();
			$result = db()->query("
				SELECT items.identifier
				FROM items
				LEFT JOIN ratings
				ON items.identifier=ratings.item
				AND ratings.posted > COALESCE(items.modified, items.uploaded)
				AND ratings.user='" . db()->escapeString(username()) . "'
				" . (loggedin() ? "WHERE items.user != '" . db()->escapeString(username()) . "'" : "") . "
				GROUP BY ratings.item
				HAVING COUNT(ratings.rating)=0
				ORDER BY COALESCE(items.modified, items.uploaded) ASC
			;");
			while ($row = $result->fetchArray(SQLITE3_NUM))
				$_SESSION["itemqueue"][] = $row[0];
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "notbyuser":
			// set item queue to all items in the database not uploaded by the 
			// current user
			if (!loggedin())
				badrequest("you need to be logged in");
			$_SESSION["itemqueue"] = array();
			$result = db()->query("SELECT identifier FROM items WHERE user!='" . db()->escapeString(username()) . "' ORDER BY COALESCE(modified, uploaded) DESC;");
			while ($row = $result->fetchArray(SQLITE3_NUM))
				$_SESSION["itemqueue"][] = $row[0];
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "prev":
			// move the item pointer back
			if ($_SESSION["itemqueuepos"] == 0)
				badrequest("already on the first item");
			$_SESSION["itemqueuepos"]--;
			redirect(SITEROOT_WEB . "?page=playItem");
		case "next":
			// move the item pointer on and check if we're finished
			if (++$_SESSION["itemqueuepos"] >= count($_SESSION["itemqueue"])) {
				$title = "Finished";
				include "htmlheader.php";
				?>
				<h1><?php echo htmlspecialchars($title); ?></h1>
				<?php if (count($_SESSION["itemqueue"]) == 1) { ?>
					<p>You've finished the only item in the queue.</p>
				<?php } else { ?>
					<p>You've got to the end of the <?php echo count($_SESSION["itemqueue"]); ?> items in the queue.</p>
				<?php } ?>
				<p>What do you want to do now?</p>
				<ul>
					<li><a href="<?php echo SITEROOT_WEB; ?>">Go back to the main menu</a></li>
					<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=startover">Take <?php echo plural($_SESSION["itemqueue"], "these items", "this item"); ?> again</a></li>
				</ul>
				<?php
				include "htmlfooter.php";
				exit;
			}
			redirect(SITEROOT_WEB . "?page=playItem");
		case "startover":
			// reset the item pointer
			$_SESSION["itemqueuepos"] = 0;
			redirect(SITEROOT_WEB . "?page=playItem");
		default:
			badrequest("unrecognized action");
	}
}

if (!isset($_SESSION["itemqueue"]) || empty($_SESSION["itemqueue"]))
	badrequest("item queue is empty");

// URL to embed in QTIEngine XML
$actionurl = SITEROOT_WEB . "?page=playItem";

// get the current item
$item = getitem($_SESSION["itemqueue"][$_SESSION["itemqueuepos"]]);

// set response to false (to give a message later) if the item doesn't exist 
// (it's been deleted)
if (!$item)
	$response = false;
else {
	// if QTIEngine form submitted post onwards to QTIEngine and display its output
	if (isset($_POST["submit"])) {
		// build request string
		$multipart = new HttpRequestBodyMultipart();
		$multipart->addpart("actionUrl", $actionurl);
		$multipart->addfromarray($_POST);
		$request = $multipart->requeststring();

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL				=>	"http://" . QTIENGINE_HOST . ":" . QTIENGINE_PORT . QTIENGINE_PATH . "rest/playItem/0;jsessionid=" . $_SESSION["qtiengine_session"],
			CURLOPT_POST			=>	true,
			CURLOPT_RETURNTRANSFER	=>	true,
			CURLOPT_HTTPHEADER		=>	array(
				"Content-Type: multipart/form-data; boundary=" . $multipart->boundary(),
				"Expect: ",
			),
			CURLOPT_POSTFIELDS		=>	$multipart->requeststring(),
		));
		$response = curl_exec($curl);
	} else {
		// display a new item

		// upload the QTI to QTIEngine

		// build request string
		$multipart = new HttpRequestBodyMultipart();
		$multipart->addpart("actionUrl", $actionurl);
		$multipart->addpart("uploadedContent", $item["xml"], "application/xml", "qb_" . $item["identifier"] . ".xml");

		// set up curl handle to upload it
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL				=>	"http://" . QTIENGINE_HOST . ":" . QTIENGINE_PORT . QTIENGINE_PATH . "rest/upload",
			CURLOPT_POST			=>	true,
			CURLOPT_RETURNTRANSFER	=>	true,
			CURLOPT_HTTPHEADER		=>	array(
				"Content-Type: multipart/form-data; boundary=" . $multipart->boundary(),
				"Expect: ",
			),
			CURLOPT_POSTFIELDS		=>	$multipart->requeststring(),
			CURLOPT_FOLLOWLOCATION	=>	true,
			CURLOPT_USERAGENT		=>	PROGRAMNAME . "/" . VERSION, // QTIEngine gives a null pointer exception in this case if a User-Agent header isn't given!
		));

		// upload it -- curl will follow the location headers and return the 
		// final response
		$response = curl_exec($curl);

		// get jsessionid from last URL we were directed to
		$responseinfo = curl_getinfo($curl);
		$_SESSION["qtiengine_session"] = preg_replace('%.*;jsessionid=([0-9A-F]+)\?.*%', '\1', $responseinfo["url"]);
	}

	// parse response
	$xml = new SimpleXMLElement($response) or servererror("couldn't parse XML response");

	// inject javascript to header
	ob_start();
	?>
	<script type="text/javascript">
		$j(document).ready(function() {
			$j("#getcommentslink").click(function(e) {
				e.preventDefault();
				$j.ajax({
					type: "GET",
					cache: false,
					dataType: "json",
					error: function(XMLHttpRequest, textStatus, errorThrown) {
						alert(XMLHttpRequest.responseText);
					},
					success: function(data, textStatus) {
						$j("#comments").html(data.html);
					},
					url: "<?php echo SITEROOT_WEB; ?>?page=playItem&action=getcomments"
				});
			});
			$j("#justcomment").click(function(e) {
				e.preventDefault();
				$j.ajax({
					type: "POST",
					cache: false,
					dataType: "json",
					data: { "justcomment": true, "comment": $j("#comment").val() },
					error: function(XMLHttpRequest, textStatus, errorThrown) {
						alert(XMLHttpRequest.responseText);
					},
					success: function(data, textStatus) {
						$j("#comments").append(data.html);
						$j("#comment").val("");
					},
					url: "<?php echo SITEROOT_WEB; ?>?page=playItem&action=comment"
				});
			});
			$j("#rateandcomment").click(function(e) {
				e.preventDefault();
				$j.ajax({
					type: "POST",
					cache: false,
					dataType: "json",
					data: { "rateandcomment": true, "comment": $j("#comment").val(), "rating": $j("#rating").val() },
					error: function(XMLHttpRequest, textStatus, errorThrown) {
						alert(XMLHttpRequest.responseText);
					},
					success: function(data, textStatus) {
						$j("#comments").append(data.html);
					},
					url: "<?php echo SITEROOT_WEB; ?>?page=playItem&action=comment"
				});
			});
			$j("#justrate").click(function(e) {
				e.preventDefault();
				$j.ajax({
					type: "POST",
					cache: false,
					dataType: "text",
					data: { "justrate": true, "rating": $j("#rating").val() },
					error: function(XMLHttpRequest, textStatus, errorThrown) {
						alert(XMLHttpRequest.responseText);
					},
					success: function(data, textStatus) {
						alert("Your rating was recorded");
					},
					url: "<?php echo SITEROOT_WEB; ?>?page=playItem&action=comment"
				});
			});
			$j(".confirmdeleteitem").click(function() {
				if (!confirm("Are you sure you want to delete this item?"))
					return false;
			});
		});
	</script>
	<?php
	$headerextra = qtiengine_header_html($xml->page) . ob_get_clean();
}

include "htmlheader.php";
?>
<h2>Play items</h2>
<div id="playitemstatus">
	<ul class="pagination centre">
		<?php if ($_SESSION["itemqueuepos"] > 0) { ?>
			<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=prev">Previous</a></li>
		<?php } ?>
		<li>Item <?php echo $_SESSION["itemqueuepos"] + 1; ?> of <?php echo count($_SESSION["itemqueue"]); ?></li>
		<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=next"><?php echo $_SESSION["itemqueuepos"] < count($_SESSION["itemqueue"]) - 1 ? "Next" : "Finish"; ?></a></li>
	</ul>
	<ul class="pagination centre">
		<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=startover">Start over</a></li>
	</ul>

	<?php if ($response !== false) { ?>
		<h3>Score</h3>
		<div class="score">
			<?php
			$score = "-";
			if (isset($_POST["submit"])) {
				echo "<!-- Response and outcome variables XML:\n" . simplexml_indented_string($xml->vars) . "\n-->\n";
				foreach ($xml->vars->OutcomeVars->param as $param) {
					if ((string) $param["identifier"] == "SCORE") {
						$score = (string) $param;
						break;
					}
				}
			}
			echo $score;
			?>
		</div>

		<h3>About this item</h3>
		<ul id="aboutlist">
			<li class="hidden">Identifier: <span id="qtiid"><?php echo htmlspecialchars($item["identifier"]); ?></span></li>
			<li>Uploaded by <strong><?php echo htmlspecialchars($item["user"]); ?></strong> <?php echo friendlydate_html($item["uploaded"]); ?></li>
			<li>
				<?php if (is_null($item["modified"])) { ?>
					First edition
				<?php } else { ?>
					Last edited <?php echo friendlydate_html($item["modified"]); ?>
				<?php } ?>
			</li>
			<li>Description: <?php echo htmlspecialchars($item["description"]); ?></li>
			<li>Keywords: <?php echo htmlspecialchars(implode(", ", $item["keywords"])); ?></li>
			<li>
				<?php if ($item["ratingcount"] > 0) { ?>
					Rating (rated <?php echo $item["ratingcount"]; ?> time<?php echo plural($item["ratingcount"]); ?>):
					<div class="stars">
						<div class="on" style="width: <?php echo ($on = 100 * $item["rating"] / 5); ?>%;"></div>
						<div class="off" style="width: <?php echo 100 - $on; ?>%;"></div>
					</div>
				<?php } else { ?>
					Not yet rated
				<?php } ?>
			</li>
		</ul>

		<h3>Actions</h3>
		<ul>
			<li>
				<a href="<?php echo SITEROOT_WEB; ?>?page=toEqiat&amp;qtiid=<?php echo htmlspecialchars($item["identifier"]); ?>&amp;clone=true">
					Clone and edit
				</a>
				<?php if (!authoredineqiat($item["xml"])) { ?>
					<img src="<?php echo SITEROOT_WEB; ?>images/error.png" width="16" height="16" alt="warning sign" title="Item was not authored in Eqiat and so may not be editable">
				<?php } ?>
			</li>
			<?php if (loggedin()) { ?>
				<?php if ($item["user"] == username()) { ?>
					<li>
						<a href="<?php echo SITEROOT_WEB; ?>?page=toEqiat&amp;qtiid=<?php echo htmlspecialchars($item["identifier"]); ?>">
							Edit
						</a>
						<?php if (!authoredineqiat($item["xml"])) { ?>
							<img src="<?php echo SITEROOT_WEB; ?>images/error.png" width="16" height="16" alt="warning sign" title="Item was not authored in Eqiat and so may not be editable">
						<?php } ?>
					</li>
				<?php } ?>
				<?php if ($item["user"] == username() || userhasprivileges()) { ?>
					<li>
						<a class="confirmdeleteitem" href="<?php echo SITEROOT_WEB; ?>?page=deleteItem&amp;qtiid=<?php echo htmlspecialchars($item["identifier"]); ?>">
							Delete
						</a>
					</li>
				<?php } ?>
			<?php } else { ?>
				<p class="smallfaded">If you were to <a href="<?php echo SITEROOT_WEB; ?>?page=login">log in</a> you might have further options</p>
			<?php } ?>
		</ul>
	<?php } ?>
</div>

<h3><?php echo $response !== false ? htmlspecialchars($item["title"]) : "Item doesn't exist"; ?></h3>
<?php if ($response === false) { ?>
	<p>This item doesn't exist in the database. Perhaps it was recently deleted.</p>
	<?php
	include "htmlfooter.php";
	exit;
} else
	echo qtiengine_bodydiv_html($xml->page);
?>

<h3>Comment and rate</h3>
<?php if (!loggedin()) { ?>
	<p>You need to be logged in to rate or comment on this item</p>
	<p>There <?php echo plural($item["comments"], "are", "is"); ?> <?php echo count($item["comments"]); ?> comment<?php echo plural($item["comments"]); ?> on this item</p>
	<ul id="comments">
		<?php if (count($item["comments"]) > 0) { ?>
			<li><a id="getcommentslink" href="#">View existing comments</a></li>
		<?php } ?>
	</ul>
<?php } else { ?>
	<form action="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=comment" method="post">
		<dl>
			<dt>Comment</dt>
			<dd>
				<p>There <?php echo plural($item["comments"], "are", "is"); ?> <?php echo count($item["comments"]); ?> comment<?php echo plural($item["comments"]); ?> on this item</p>
				<ul id="comments">
					<?php if (count($item["comments"]) > 0) { ?>
						<li><a id="getcommentslink" href="#">View existing comments</a></li>
					<?php } ?>
				</ul>
				<div><textarea id="comment" name="comment"></textarea></div>
				<input type="submit" id="justcomment" name="justcomment" value="Just comment">
			</dd>

			<dt>Rating</dt>
			<dd>
				<?php $rating = itemrating($item["identifier"]); ?>
				<?php if ($item["user"] == username()) { ?>
					<p>You can't rate your own item</p>
				<?php } else if (!is_null($rating)) { ?>
					<p>You have already rated this item</p>
					<div class="stars block">
						<div class="on" style="width: <?php echo ($on = 100 * $rating / 5); ?>%;"></div>
						<div class="off" style="width: <?php echo 100 - $on; ?>%;"></div>
					</div>
				<?php } else { ?>
					<div class="stars settable">
						<div class="on" style="width: 0%;"></div>
						<div class="off" style="width: 100%;"></div>
						<input type="hidden" class="rating" name="rating" id="rating" value="0">
					</div>
					<div>
						<input type="submit" id="justrate" name="justrate" value="Rate this item">
						<input type="submit" id="rateandcomment" name="rateandcomment" value="Rate and comment">
					</div>
				<?php } ?>
			</dd>
		</dl>
	</form>
<?php } ?>

<?php
include "htmlfooter.php";
?>
