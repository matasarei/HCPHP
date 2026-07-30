<?php
/**
 * @var Html\Form\Form $form
 */

use core\Template;
use Html\Form\Button;

?>
<form method="<?= Template::escape($form->getMethod()) ?>"
      enctype="multipart/form-data" <?php if($form->getAction() !== null): ?>action="<?= Template::escape($form->getAction()) ?>"<?php endif; ?>>
    <?php if ($form->getSessionKey() !== null): ?>
        <input name="<?= Template::escape($form::KEY_SESSION) ?>" type="hidden" value="<?= Template::escape($form->getSessionKey()) ?>">
    <?php endif; ?>
    <fieldset>
        <?php if ($form->getDescription() !== null): ?>
            <legend><?= Template::escape($form->getDescription()) ?></legend>
        <?php endif; ?>
        <?php foreach ($form->getFields() as $field): ?>
            <div class="mb-3">
                <label for="<?= Template::escape($field->getAttribute('id')) ?>" class="form-label"><?= Template::escape($field->getTitle()) ?></label>
                <?= $field->addAttribute('class', 'form-control')->getHtml() ?>
            </div>
        <?php endforeach; ?>
    </fieldset>
    <fieldset>
        <?php foreach ($form->getButtons() as $button): ?>
            <?php if ($button->getType() === Button::TYPE_SUBMIT): ?>
                <button type="submit" class="btn btn-primary"><?= Template::escape($button->getName()) ?></button>
            <?php else: ?>
                <a href="<?= Template::escape($button->getUrl()) ?>" class="btn btn-warning"><?= Template::escape($button->getName()) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </fieldset>
</form>
