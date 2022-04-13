<?php
/**
 * @var Html\Form\Form $form
 */

use Html\Form\Button;

?>
<form method="<?= $form->getMethod() ?>"
      enctype="multipart/form-data" <?php if($form->getAction() !== null): ?>action="<?= $form->getAction() ?>"<?php endif; ?>>
    <?php if ($form->getSessionKey() !== null): ?>
        <input name="<?= $form::KEY_SESSION ?>" type="hidden" value="<?= $form->getSessionKey() ?>">
    <?php endif; ?>
    <fieldset>
        <?php if ($form->getDescription() !== null): ?>
            <legend><?= $form->getDescription() ?></legend>
        <?php endif; ?>
        <?php foreach ($form->getFields() as $field): ?>
            <div class="mb-3">
                <label for="<?= $field->getAttribute('id') ?>" class="form-label"><?= $field->getTitle() ?></label>
                <?= $field->addAttribute('class', 'form-control')->getHtml() ?>
            </div>
        <?php endforeach; ?>
    </fieldset>
    <fieldset>
        <?php foreach ($form->getButtons() as $button): ?>
            <?php if ($button->getType() === Button::TYPE_SUBMIT): ?>
                <button type="submit" class="btn btn-primary"><?= $button->getName() ?></button>
            <?php else: ?>
                <a href="<?= $button->getUrl() ?>" class="btn btn-warning"><?= $button->getName() ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </fieldset>
</form>