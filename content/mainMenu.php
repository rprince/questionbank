<?php

/*
 * Question Bank
 */

/*------------------------------------------------------------------------------
(c) 2010 JISC-funded EASiHE project, University of Southampton
Licensed under the Creative Commons 'Attribution non-commercial share alike' 
licence -- see the LICENCE file for more details
------------------------------------------------------------------------------*/

include "htmlheader.php";
?>

<dl id="mainmenu">
	<dt>Item list</dt>
	<dd>
		A filterable list of all items currently in <?php echo htmlspecialchars(SITE_TITLE); ?>
		<ul>
			<li><a href="<?php echo Uri::construct(SITEROOT_WEB . "?page=itemList", true)->addvars("clear", "true")->geturi(); ?>">All items</a> (or start a new search)</li>
			<?php if (isset($_SESSION["search"])) { ?>
				<li><a href="<?php echo Uri::construct(SITEROOT_WEB . "?page=itemList", true)->geturi(); ?>">Previous search results</a>
			<?php } ?>
			<?php if (loggedin()) { ?>
				<li><a href="<?php echo Uri::construct(SITEROOT_WEB . "?page=itemList", true)->addvars("user", username())->geturi(); ?>">Your items</a>
			<?php } ?>
		</ul>
	</dd>
	<dt>Play items</dt>
	<dd>
		<?php if (isset($_SESSION["itemqueue"]) && !empty($_SESSION["itemqueue"])) { ?>
			<ul>
				<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem">Return to current set of items</a> (<?php echo count($_SESSION["itemqueue"]); ?> item<?php echo plural($_SESSION["itemqueue"]); ?>)</li>
			</ul>
		<?php } ?>
		Play all items...
		<ul>
			<?php if (isset($_SESSION["search"]) && count($_SESSION["items"]) > 0 && (!isset($_SESSION["itemqueue"]) || $_SESSION["items"] != $_SESSION["itemqueue"])) { ?>
				<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=results">in the current search results</a> (<?php echo count($_SESSION["items"]); ?> item<?php echo plural($_SESSION["items"]); ?>)</li>
			<?php } ?>
			<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=shuffle">at random</a></li>
			<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=newest">from newest to oldest</a></li>
			<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=highestrated">from highest rated to lowest</a></li>
			<?php if (loggedin()) { ?>
				<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=unratedbyuser">you haven't yet rated, oldest first</a></li>
			<?php } ?>
			<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=unrated">which haven't been rated by anyone, oldest first</a></li>
			<?php if (loggedin()) { ?>
				<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=notbyuser">you didn't upload</a></li>
			<?php } ?>
		</ul>
		You can build other queues of items by searching the item list
	</dd>
</dl>

<?php include "htmlfooter.php"; ?>
