<h2>Login</h2>

<?php if (isset($error)): ?>
	<p class='error'><?php echo $error ?></p>
<?php endif ?>

<form method="post">
    <fieldset>
        <div class='field' id='username'>
            <label class='field-name'>Username / email:</label>
            <input name='username' value='<?php if(isset($logindata->username)) echo $logindata->username; ?>' />
        </div>
        <div class='field' id='password'>
            <label class='field-name'>Password:</label>
            <input name='password' />
        </div>
        <div id='buttons'>
            <label class='field-name'></label>
            <button type="submit">Submit</button>
        </div>
    </fieldset>
</form>