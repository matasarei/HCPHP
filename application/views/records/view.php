<?php
/**
 * @var DynamicEntity $entity
 * @var Table $table
 */

use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\File;
use DynamicDB\Entity\Table;

?>
<div class="container mt-5">
    <div class="btn-group" role="group" aria-label="Basic outlined example">
        <a href="/user/" class="btn btn-outline-primary">All items</a>
        <a href="/records/{{$entity->id}}/edit" class="btn btn-outline-secondary">Edit</a>
        <a href="/records/{{$entity->id}}/delete" class="btn btn-outline-danger">Delete</a>
    </div>

    <div class="row">
        <ul class="list-group list-group-flush">
        <?php foreach($table->getFields() as $field): ?>
            <li class="list-group-item">
                <b><?= $field->getDescription(); ?></b>:
                <?php if ($entity->get($field->getName()) instanceof File): ?>
                    <a href="/records/{{$entity->id}}/download/{{$field->getName()}}/">
                        <?= $entity->get($field->getName()); ?>
                    </a>
                <?php else: ?>
                    <?= $entity->get($field->getName()); ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>
