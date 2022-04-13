<?php
/**
 * @var Table $table
 * @var DynamicEntity[] $records
 */

use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use Filter\HtmlFilter;

?>
<table class="table">
    <thead>
    <tr>
        <th scope="col">ID</th>
        <?php foreach ($table->getFields() as $field): ?>
            <th scope="col"><?= $field->getDescription() ?></th>
        <?php endforeach; ?>
        <th scope="col">Created at</th>
        <th scope="col">Modified at</th>
        <th scope="col"></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($records as $record): ?>
        <tr>
            <th scope="row">
                <a href="/records/{{$record->id}}/">{{$record->id}}</a>
            </th>
            <?php foreach ($table->getFields() as $field): ?>
                <td>
                    <?php if ($field->getType() === Field::TYPE_DATETIME): ?>
                        <small>
                            <?= (new DateTime())
                                ->setTimestamp($record->get($field->getName()))
                                ->format('d.m.Y H:i:s'); ?>
                        </small>
                    <?php elseif ($field->getType() === Field::TYPE_BOOLEAN): ?>
                        <?php if ($record->get($field->getName())): ?>
                            {{lang|'yes'}}
                        <?php else: ?>
                            {{lang|'no'}}
                        <?php endif; ?>
                    <?php elseif ($field->getType() === Field::TYPE_FILE): ?>
                        <?php if (!empty($record->get($field->getName()))): ?>
                            <a href="/records/<?= $record->getId() ?>/download/<?= $field->getName() ?>/"
                               class="btn btn-outline-secondary btn-sm">
                                {{lang|'Get'}}
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    <?php elseif ($field->getType() === Field::TYPE_JSON): ?>
                        <?php if (!empty($record->get($field->getName()))): ?>
                            <a href="/records/<?= $record->getId() ?>/" class="btn btn-outline-secondary btn-sm">
                                {{lang|'View'}}
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (mb_strlen($record->get($field->getName())) > 16): ?>
                            <a href="/records/<?= $record->getId() ?>/" class="btn btn-outline-secondary btn-sm">
                                {{lang|'View'}}
                            </a>
                        <?php else: ?>
                            <?= (new HtmlFilter())->filter($record->get($field->getName())); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
            <td>
                <small>
                    <?= (new DateTime())->setTimestamp($record->get('timecreated'))->format('d.m.Y H:i:s'); ?>
                </small>
            </td>
            <td>
                <small>
                    <?= (new DateTime())->setTimestamp($record->get('timemodified'))->format('d.m.Y H:i:s'); ?>
                </small>
            </td>
            <td class="btn-group btn-group-sm" role="group" aria-label="actions">
                <a href="/records/{{$record->id}}/edit/" class="btn btn-outline-secondary">{{lang|'Edit'}}</a>
                <a href="/records/{{$record->id}}/delete/" class="btn btn-outline-danger">{{lang|'Delete'}}</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>