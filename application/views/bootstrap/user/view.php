<?php if (!empty($valid) && $valid): ?>
<h1 class="page-header" style="display: inline-block"><?php echo $user['username']; ?>'s Profile</h1>

<div class="pull-right" style="display: inline-block">
<?php if (CheckAcl::can('forceLogout')): ?>
<form action="<?php echo Url::format('/user/forceLogout'); ?>" method="post" style="display: inline">
	<input type="hidden" name="username" value="<?php echo $user['username']; ?>" />
<?php if (apc_exists('user_' . $user['username'])): ?>
	<input type="submit" value="Logout" class="btn btn-danger" />
<?php endif; ?>
</form>
<?php endif; ?>
<a class="thumbnail pull-right" style="display: inline-block">
	<img style="display: inline-block" src="http://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($user['email']))); ?>?s=120" />
</a><br />
</div><br />
<?php
if ($onSite) {
	echo '<i class="icon-ok"></i><i>Online!</i>';
} else {
	echo '<i class="icon-remove"></i><i>Offline</i>';
}
echo '&nbsp;&nbsp;';
if (empty($onIrc)) {
	echo '<i class="icon-remove"></i><i>Not on IRC.</i>';
} else {
	echo '<i class="icon-ok"></i><i>On IRC as:  </i>' . implode(', ', $onIrc);
}
?><br />
<em><?php 
echo ($user['hideEmail'] ? '(Email Hidden)' : htmlentities($user['email'], ENT_QUOTES, '', false)); 
?></em>
<hr />
<div class="row">
	<div class="span5"><div class="well">
		<h3>Completed Missions</h3>
<?php if (empty($user['missions'])): ?>
			<em>No missions completed.</em>
<?php else: ?>
			<dl class="dl-horizontal">
<?php foreach ($user['missions'] as $category => $missions): ?>
				<dt><?php echo ucwords($category); ?></dt>
				<dd>
<?php
	$missionsDone = array();
	foreach ($missions as $id => $done) {
		array_push($missionsDone, '<a href="' . Url::format('/missions/' . $category . '/' . $id) . '">' . $id . '</a>');
	}
	
	echo implode(', ', $missionsDone);
?>
</dd>
<?php endforeach; ?>
			</dl>
<?php endif; ?>
	</div></div>
	
	<div class="span4">
		<div class="span4"><div class="well">
			<h3>Articles</h3>
<?php if (empty($articles)): ?>
			<em>No articles written.</em>
<?php else: ?>

<?php endif; ?>
		</div></div>
		
		<div class="span4"><div class="well">
			<h3>Lectures</h3>
<?php if (empty($lectures)): ?>
			<em>No lectures given.</em>
<?php else: ?>

<?php endif; ?>
		</div></div>
	</div>
</div>

<?php if (CheckAcl::can('adminUsers')): ?>
<legend>Admin Panel</legend>

<div class="row">
	<div class="span2">
		<form class="form-vertical well" action="<?php echo Url::format('/user/admin/'); ?>" method="post">
			<input type="hidden" name="userId" value="<?php echo $user['_id']; ?>" />
			<label class="checkbox">
				<input type="checkbox" name="status" value="locked" <?php echo ($user['status'] == users::ACCT_LOCKED ? ' checked="checked" ' : ''); ?>/>
				&nbsp;Locked
			</label>
			<select name="group" class="input-small">
<?php foreach (acl::$acls as $acl): ?>
				<option value="<?php echo $acl; ?>"<?php echo ($acl == $user['group'] ? 'selected="selected"' : ''); ?>><?php echo ucwords($acl); ?></option>
<?php endforeach; ?>
			</select><br />
			<center><input type="submit" value="Save" class="btn btn-danger" /></center>
		</form>
	</div>
	<div class="span7">
		<div style="width: 100%;height: 100px;overflow: auto;margin-bottom: 10px;">
<?php if (empty($user['notes'])): ?>
			<center><em>There are no notes on this user!  (Is that good or bad?)</em></center>
<?php else: foreach ($user['notes'] as $note): ?>
<p style="margin: 0 0 0 10px;padding: 0;text-indent: -10px;"><?php echo Date::dayFormat($note['date']); ?>&nbsp;
<?php echo $note['user']['username']; ?>&nbsp;-&nbsp;
<?php echo $note['text']; ?></p>
<?php endforeach; endif; ?>
		</div>
<?php if (CheckAcl::can('postNotes')): ?>
		<form class="form-search" action="<?php echo Url::format('/user/notes/'); ?>" method="post">
			<input type="hidden" name="userId" value="<?php echo $user['_id']; ?>" />
			<input class="span6" type="text" name="note" placeholder="Something here to help you keep track of who is who." />
			<input type="submit" value="Save" class="btn btn-primary" />
		</form>
<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<hr />
<?php
echo Partial::render('comment', array('id' => $user['_id'], 'page' => 1));
?>
<?php
endif;